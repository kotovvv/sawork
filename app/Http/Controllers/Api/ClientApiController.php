<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogOrder;
use App\Http\Controllers\Api\importBLController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class ClientApiController extends Controller
{
    private $importBLController;

    public function __construct()
    {
        // Используем обычный экземпляр importBLController
        $this->importBLController = new importBLController();
    }

    /**
     * Validate API key and warehouse access (config, settings, api_keys)
     */
    private function validateApiKey(Request $request, $IDWarehouse = null)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');

        // Check in settings table (encrypted tokens)
        $settings = DB::table('settings')
            ->where('key', 'api_token')
            ->get();
        foreach ($settings as $setting) {
            try {
                $decrypted = Crypt::decryptString($setting->value);
                if ($decrypted === $apiKey) {
                    if ($IDWarehouse !== null) {
                        return intval($setting->warehouse_id) === intval($IDWarehouse);
                    }
                    return true;
                }
            } catch (\Exception $e) {
                // ignore decryption errors
            }
        }
    }


    /**
     * Get orders from the system
     */
    public function getOrders(Request $request)
    {

        $client = $request->attributes->get('api_client');
        $warehouseId = $client['warehouse_id'];

        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|string',
            'order_id' => 'nullable|string',
            'limit' => 'nullable|integer|max:100',
            'offset' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        try {
            $query = DB::table('Orders')
                ->leftJoin('OrderStatus', 'Orders.IDOrderStatus', '=', 'OrderStatus.IDOrderStatus')
                ->leftJoin('Kontrahent', 'Orders.IDAccount', '=', 'Kontrahent.IDKontrahenta')
                ->where('Orders.IDWarehouse', $warehouseId)
                ->select([
                    'Orders.IDOrder',
                    'Orders.Number',
                    'Orders.Created',
                    'Orders.Modified',
                    'Orders.Remarks',
                    //'Orders._OrdersTempDecimal2 as baselinker_order_id',
                    'Orders._OrdersTempString1 as invoice_number',
                    'Orders._OrdersTempString7 as order_sources',
                    'Orders._OrdersTempString8 as external_order_id',
                    'Orders._OrdersTempString9 as user_login',
                    'OrderStatus.Name as status',
                    'Kontrahent.Nazwa as customer_name',
                    'Kontrahent.Email as customer_email'
                ]);      // Apply filters
            if ($request->date_from) {
                $query->where('Orders.Created', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->where('Orders.Created', '<=', $request->date_to);
            }

            if ($request->status) {
                $query->where('OrderStatus.Name', 'like', '%' . $request->status . '%');
            }

            if ($request->order_id) {
                $query->where('Orders._OrdersTempString8', $request->order_id);
            }

            $limit = $request->limit ?? 50;
            $offset = $request->offset ?? 0;

            $orders = $query->limit($limit)->offset($offset)->get();

            // Get order details for each order
            foreach ($orders as &$order) {
                $order->details = DB::connection('second_mysql')
                    ->table('order_details')
                    ->where('order_id', $order->IDOrder)
                    ->where('IDWarehouse', $warehouseId)
                    ->first();

                $order->products = DB::table('OrderLines')
                    ->leftJoin('Towar', 'OrderLines.IDItem', '=', 'Towar.IDTowaru')
                    ->where('OrderLines.IDOrder', $order->IDOrder)
                    ->select([
                        'OrderLines.IDOrderLine',
                        'OrderLines.Quantity',
                        'OrderLines.PriceGross',
                        'OrderLines.Remarks',
                        'Towar.Nazwa as product_name',
                        'Towar.KodKreskowy as ean'
                    ])
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $orders,
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($orders)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('API getOrders failed', [
                'client_id' => $client->id,
                'warehouse_id' => $warehouseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to retrieve orders'
            ], 500);
        }
    }

    /**
     * Create or update order via API (upsert by external_order_id)
     */
    public function upsertOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_order_id' => 'required|string',
            'customer' => 'sometimes|array',
            'customer.name' => 'sometimes|string',
            'customer.email' => 'sometimes|email',
            'customer.phone' => 'sometimes|string',
            'delivery' => 'sometimes|array',
            'delivery.fullname' => 'sometimes|string',
            'delivery.address' => 'sometimes|string',
            'delivery.city' => 'sometimes|string',
            'delivery.postcode' => 'sometimes|string',
            'delivery.country_code' => 'sometimes|string|size:2',
            'delivery.method' => 'sometimes|string',
            'products' => 'sometimes|array|min:1',
            'products.*.ean' => 'sometimes|string',
            'products.*.quantity' => 'sometimes|numeric|min:0.01',
            'products.*.price_brutto' => 'nullable|numeric',
            'products.*.tax_rate' => 'nullable|numeric',
            'payment_method' => 'nullable|string',
            'delivery_price' => 'nullable|numeric|min:0',
            'user_comments' => 'nullable|string',
            'admin_comments' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        $warehouseId = \App\Services\WarehouseApiKeyService::getWarehouseByApiKey($request);
        if (!$warehouseId) {
            return response()->json([
                'error' => 'Invalid API key or warehouse not found'
            ], 401);
        }

        try {
            DB::beginTransaction();

            // Check if order exists by external_order_id and warehouse
            $existingOrder = DB::table('Orders')
                ->where('IDWarehouse', $warehouseId)
                ->where('_OrdersTempString8', $request->external_order_id)
                ->first();

            $orderData = $this->transformApiOrderToBaseLinkerFormat($request->all());
            $customerId = $this->importBLController->findOrCreateKontrahent($orderData, $warehouseId);
            $uwagi = 'API Order - External ID: ' . $request->external_order_id . ' ' . ($request->user_comments ?: $request->admin_comments ?: '');
            $orderDate = now()->format('Y-m-d H:i:s');
            $orderStatus = 'W realizacji';
            $paymentType = DB::table('PaymentTypes')->where('Name', $request->payment_method ?? 'Przelew')->value('IDPaymentType');
            $idTransport = DB::table('RodzajTransportu')->where('Nazwa', $request->delivery['method'])->value('IDRodzajuTransportu');
            if (!$idTransport) {
                DB::table('RodzajTransportu')->insert([
                    'Nazwa' => $request->delivery['method'],
                    'Utworzono' => now(),
                    'Zmodyfikowano' => now(),
                ]);
                $idTransport = DB::table('RodzajTransportu')->where('Nazwa', $request->delivery['method'])->value('IDRodzajuTransportu');
            }

            if ($existingOrder) {
                // Проверяем статус заказа
                $allowedStatuses = [
                    'Anulowany',
                    'Nie wysyłać',
                    'Nowe zamówienia',
                    'W realizacji'
                ];
                $currentStatus = DB::table('OrderStatus')->where('IDOrderStatus', $existingOrder->IDOrderStatus)->value('Name');
                if (!in_array($currentStatus, $allowedStatuses)) {
                    return response()->json([
                        'error' => 'Order cannot be updated in current status',
                        'order_id' => $existingOrder->IDOrder,
                        'current_status' => $currentStatus
                    ], 409);
                }
                // Update existing order
                DB::table('Orders')->where('IDOrder', $existingOrder->IDOrder)->update([
                    'IDAccount' => $customerId,
                    'Modified' => $orderDate,
                    'IDOrderStatus' => DB::table('OrderStatus')->where('Name', $orderStatus)->value('IDOrderStatus'),
                    '_OrdersTempString7' => 'API_' . $warehouseId,
                    '_OrdersTempString8' => $request->external_order_id,
                    '_OrdersTempString9' => 'API_CLIENT_' . $warehouseId
                ]);
                // Update order details and products
                $this->importBLController->saveOrderDetails($orderData, $existingOrder->IDOrder, $warehouseId);
                $this->importBLController->writeProductsOrder($orderData, $existingOrder->IDOrder, $warehouseId, $uwagi);
                DB::commit();
                LogOrder::create([
                    'IDWarehouse' => $warehouseId,
                    'number' => $existingOrder->IDOrder,
                    'type' => 16,
                    'message' => "API Order updated by client: API_{$warehouseId}, external_order_id: {$request->external_order_id}"
                ]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $existingOrder->IDOrder,
                        'order_number' => $existingOrder->Number,
                        'external_order_id' => $request->external_order_id,
                        'status' => $orderStatus,
                        'updated' => true
                    ]
                ], 200);
            } else {
                // Create new order
                $Number = $this->importBLController->lastNumber('ZO', $warehouseId);
                DB::statement(
                    "EXEC CreateOrder
                  @CustomerId = ?,
                  @IdZamowiena = ?,
                  @IdMagazynu = ?,
                  @Uwagi = ?,
                  @OrderDate = ?,
                  @OrdertStatus = ?,
                  @PaymentType = ?,
                  @IDTransport = ?",
                    [
                        $customerId,
                        $Number,
                        $warehouseId,
                        $uwagi,
                        $orderDate,
                        $orderStatus,
                        $paymentType,
                        $idTransport
                    ]
                );
                $IDOrder = DB::table('Orders')->where('Number', $Number)->value('IDOrder');
                $this->importBLController->saveOrderDetails($orderData, $IDOrder, $warehouseId);
                $this->importBLController->writeProductsOrder($orderData, $IDOrder, $warehouseId, $uwagi);
                DB::table('Orders')->where('IDOrder', $IDOrder)->update([
                    '_OrdersTempString7' => 'API_' . $warehouseId,
                    '_OrdersTempString8' => $request->external_order_id,
                    '_OrdersTempString9' => 'API_CLIENT_' . $warehouseId
                ]);
                DB::commit();
                LogOrder::create([
                    'IDWarehouse' => $warehouseId,
                    'number' => $IDOrder,
                    'type' => 1,
                    'message' => "API Order created by client: API_{$warehouseId}, external_order_id: {$request->external_order_id}"
                ]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $IDOrder,
                        'order_number' => $Number,
                        'external_order_id' => $request->external_order_id,
                        'status' => $orderStatus,
                        'created' => true
                    ]
                ], 201);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API upsertOrder failed', [
                'client_id' => 'API_' . $warehouseId,
                'warehouse_id' => $warehouseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to upsert order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order returns/refunds
     */
    public function getOrderReturns(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer',
            'order_id' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        $client = $request->api_client;
        $warehouseId = $request->warehouse_id;

        if (!$client->canAccessWarehouse($warehouseId)) {
            return response()->json([
                'error' => 'Access denied to warehouse'
            ], 403);
        }

        try {
            // This would need to be implemented based on your returns table structure
            // For now, returning a placeholder
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Returns functionality to be implemented'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to retrieve returns'
            ], 500);
        }
    }

    /**
     * Transform API order format to BaseLinker format for compatibility
     */
    private function transformApiOrderToBaseLinkerFormat(array $apiData)
    {
        return [
            'order_id' => 'API_' . uniqid(),
            'date_add' => now()->timestamp,
            'date_confirmed' => now()->timestamp,
            'order_status_id' => 23, // W realizacji
            'currency' => 'PLN',
            'payment_method' => $apiData['payment_method'] ?? 'Przelew',
            'payment_method_cod' => false,
            'payment_done' => '0',
            'delivery_method' => $apiData['delivery']['method'],
            'delivery_price' => $apiData['delivery_price'] ?? 0,
            'delivery_fullname' => $apiData['delivery']['fullname'],
            'delivery_company' => $apiData['delivery']['company'] ?? '',
            'delivery_address' => $apiData['delivery']['address'],
            'delivery_city' => $apiData['delivery']['city'],
            'delivery_postcode' => $apiData['delivery']['postcode'],
            'delivery_country_code' => $apiData['delivery']['country_code'],
            'delivery_country' => $this->getCountryName($apiData['delivery']['country_code']),
            'invoice_fullname' => $apiData['customer']['name'],
            'invoice_company' => $apiData['customer']['company'] ?? '',
            'invoice_address' => $apiData['delivery']['address'], // Use delivery address if invoice not provided
            'invoice_city' => $apiData['delivery']['city'],
            'invoice_postcode' => $apiData['delivery']['postcode'],
            'invoice_country_code' => $apiData['delivery']['country_code'],
            'invoice_country' => $this->getCountryName($apiData['delivery']['country_code']),
            'email' => $apiData['customer']['email'],
            'phone' => $apiData['customer']['phone'] ?? '',
            'user_comments' => $apiData['user_comments'] ?? '',
            'admin_comments' => $apiData['admin_comments'] ?? '',
            'user_login' => 'API',
            'external_order_id' => $apiData['external_order_id'] ?? '',
            'order_source' => 'API',
            'order_source_id' => 0,
            'products' => $this->transformApiProducts($apiData['products'])
        ];
    }

    private function transformApiProducts(array $apiProducts)
    {
        return array_map(function ($product) {
            return [
                'name' => $product['name'] ?? ($product['ean'] ?? ''),
                'ean' => $product['ean'],
                'quantity' => $product['quantity'],
                'price_brutto' => $product['price_brutto'],
                'tax_rate' => $product['tax_rate'] ?? 23
            ];
        }, $apiProducts);
    }

    private function getCountryName($countryCode)
    {
        $countries = [
            'PL' => 'Polska',
            'DE' => 'Niemcy',
            'CZ' => 'Czechy',
            'SK' => 'Słowacja',
            'UA' => 'Ukraina'
        ];

        return $countries[$countryCode] ?? $countryCode;
    }
}
