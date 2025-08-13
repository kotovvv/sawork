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
            'order_id' => 'required|string',
            'status' => 'nullable|string',
            'date_confirmed' => 'nullable|date',
            'customer' => 'required|array',
            'customer.name' => 'required|string',
            'customer.email' => 'required|email',
            'customer.phone' => 'nullable|string',
            'customer.NIP' => 'nullable|string',
            'delivery' => 'required|array',
            'delivery.fullname' => 'required|string',
            'delivery.company' => 'nullable|string',
            'delivery.country_code' => 'required|string|size:2',
            'delivery.country' => 'nullable|string',
            'delivery.postcode' => 'required|string',
            'delivery.state' => 'nullable|string',
            'delivery.city' => 'required|string',
            'delivery.street' => 'required|string',
            'delivery.price' => 'nullable|numeric|min:0',
            'delivery.method' => 'required|string',
            'delivery.method_id' => 'nullable|integer',
            'delivery_point' => 'nullable|array',
            'delivery_point.name' => 'nullable|string',
            'delivery_point.id' => 'nullable|string',
            'delivery_point.address' => 'nullable|string',
            'delivery_point.postcode' => 'nullable|string',
            'delivery_point.city' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.ean' => 'required|string',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.price_brutto' => 'required|numeric|min:0',
            'products.*.Remarks' => 'nullable|string',
            'order_source' => 'nullable|string',
            'order_source_id' => 'nullable|string',
            'currency' => 'nullable|string',
            'currency_rate' => 'nullable|numeric|min:0',
            'payment_method_cod' => 'nullable|string',
            'user_comments' => 'nullable|string'
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
                ->where('_OrdersTempString8', $request->order_id)
                ->first();

            $orderData = $this->transformApiOrderToBaseLinkerFormat($request->all());
            $customerId = $this->importBLController->findOrCreateKontrahent($orderData, $warehouseId);
            $uwagi = 'API Order - External ID: ' . $request->order_id . ' ' . ($request->user_comments ?: $request->admin_comments ?: '');
            $orderDate = now()->format('Y-m-d H:i:s');
            $orderStatus = $request->status ?? '';
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
                // Удаляем все товары из заказа
                DB::table('OrderLines')->where('IDOrder', $existingOrder->IDOrder)->delete();
                // Update existing order
                DB::table('Orders')->where('IDOrder', $existingOrder->IDOrder)->update([
                    'IDAccount' => $customerId,
                    'Modified' => $orderDate,
                    'IDOrderStatus' => $orderStatus !== '' ? DB::table('OrderStatus')->where('Name', $orderStatus)->value('IDOrderStatus') : $existingOrder->IDOrderStatus,
                    '_OrdersTempString7' => 'API_' . $warehouseId,
                    '_OrdersTempString8' => $request->order_id,
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
                    'message' => "API Order updated by client: API_{$warehouseId}, external_order_id: {$request->order_id}"
                ]);
                if ($orderStatus != $currentStatus) {
                    LogOrder::create([
                        'IDWarehouse' => $warehouseId,
                        'number' => $existingOrder->IDOrder,
                        'type' => 18,
                        'object_id' => $orderStatus,
                        'message' => "API Order status changed by client: API_{$warehouseId}, external_order_id: {$request->order_id}, from: {$currentStatus}, to: {$orderStatus}"
                    ]);
                }
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $existingOrder->IDOrder,
                        'order_number' => $existingOrder->Number,
                        'external_order_id' => $request->order_id,
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
                    '_OrdersTempString8' => $request->order_id,
                    '_OrdersTempString9' => 'API_CLIENT_' . $warehouseId
                ]);
                DB::commit();
                LogOrder::create([
                    'IDWarehouse' => $warehouseId,
                    'number' => $IDOrder,
                    'type' => 1,
                    'message' => "API Order created by client: API_{$warehouseId}, external_order_id: {$request->order_id}"
                ]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $IDOrder,
                        'order_number' => $Number,
                        'external_order_id' => $request->order_id,
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
        $statusID = [
            "Anulowany" => "33",
            "Nie wysyłać" => "29",
            "Nowe zamówienia" => "16",
            "W realizacji" => "23",
            "Kompletowanie" => "42",
            "Do wysłania" => "17",
            "Wysłane" => "25"
        ];
        return [
            'order_status_id' => $statusID[$apiData['status']],
            'date_confirmed' => $apiData['date_confirmed'] ?? null,
            'currency' =>  $apiData['currency'],
            'currency_rate' =>  $apiData['currency_rate'] ?? 1,
            'name' => $apiData['customer']['name'],
            'email' => $apiData['customer']['email'],
            'phone' => $apiData['customer']['phone'] ?? '',

            'delivery_method' => $apiData['delivery']['method'],
            'delivery_method_id' => $apiData['delivery']['method_id'] ?? '',

            'delivery_price' => $apiData['delivery']['price'] ?? 0,
            'delivery_fullname' => $apiData['delivery']['fullname'],
            'delivery_company' => $apiData['delivery']['company'] ?? '',
            'delivery_address' => $apiData['delivery']['street'] ?? '',
            'delivery_city' => $apiData['delivery']['city'],
            'delivery_postcode' => $apiData['delivery']['postcode'],
            'delivery_country_code' => $apiData['delivery']['country_code'],

            'delivery_point_id' => $apiData['delivery_point']['id'] ?? null,
            'delivery_point_name' => $apiData['delivery_point']['name'] ?? null,
            'delivery_point_address' => $apiData['delivery_point']['address'] ?? null,
            'delivery_point_postcode' => $apiData['delivery_point']['postcode'] ?? null,
            'delivery_point_city' => $apiData['delivery_point']['city'] ?? null,

            'invoice_nip' => $apiData['customer']['NIP'] ?? '',
            'invoice_fullname' => $apiData['customer']['name'],
            'invoice_company' => $apiData['customer']['company'] ?? '',
            'invoice_address' => $apiData['delivery']['address'] ?? '', // Use delivery address if invoice not provided
            'invoice_city' => $apiData['delivery']['city'],
            'invoice_postcode' => $apiData['delivery']['postcode'],
            'invoice_country_code' => $apiData['delivery']['country_code'],

            'payment_method_cod' => $apiData['payment_method_cod'] ?? 0,

            'user_comments' => $apiData['user_comments'] ?? '',
            'admin_comments' => $apiData['admin_comments'] ?? '',
            'user_login' => 'API',
            'external_order_id' => $apiData['order_id'] ?? '',
            'order_source' => 'API',
            'order_source_id' => $apiData['order_source_id'] ?? '',
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
                'Remarks' => $product['Remarks'] ?? '',
                'tax_rate' => $product['tax_rate'] ?? 23 // безопасно, по умолчанию 23
            ];
        }, $apiProducts);
    }

    /**
     * Get journal list (event log) for orders, similar to BaseLinker getJournalList
     * Endpoint: POST /api/orders/journal
     * Request params:
     *   - last_log_id (optional, int)
     *   - order_id (optional, string)
     *   - type (optional, int) 18 status change

     */
    public function getJournalList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|array',
            'last_log_id' => 'nullable|integer',
            'order_id' => 'nullable|string'
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
        $offset = $request->offset ?? 0;

        try {
            $query = LogOrder::query()
                ->where('IDWarehouse', $warehouseId);


            if ($request->order_id) {
                $query->where('number', $request->order_id);
            } else {
                if ($request->type) {
                    $query->where('type', $request->type);
                }
                if ($request->last_log_id && is_numeric($request->last_log_id)) {
                    $query->where('id', '>', $request->last_log_id);
                } else {
                    $query->where('created_at', '>=', Carbon::now()->subDays(3));
                }
            }

            $total = $query->count();
            $logs = $query->orderByDesc('created_at')

                ->get();

            $result = [];
            foreach ($logs as $log) {
                $result[] = [
                    'last_log_id' => $log->id,
                    'order_id' => $log->number,
                    'type' => $log->type,
                    'message' => $log->message,
                    'created_at' => $log->created_at,
                    'object_id' => $log->object_id
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'meta' => [

                    'count' => count($result),
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('API getJournalList failed', [
                'IDWarehouse' => $warehouseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to retrieve journal list'
            ], 500);
        }
    }
}
