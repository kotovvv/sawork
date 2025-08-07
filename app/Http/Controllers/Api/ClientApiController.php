<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\OrderSource;
use App\Models\LogOrder;
use App\Http\Controllers\Api\importBLController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientApiController extends Controller
{
  private $importBLController;

  public function __construct()
  {
    // Initialize without parameters for API usage
    $this->importBLController = new class extends importBLController {
      public function __construct()
      {
        // Empty constructor to avoid automatic processing
      }
    };
  }

  /**
   * Get orders from the system
   */
  public function getOrders(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'warehouse_id' => 'required|integer',
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

    $client = $request->api_client;
    $warehouseId = $request->warehouse_id;

    // Check warehouse access
    if (!$client->canAccessWarehouse($warehouseId)) {
      return response()->json([
        'error' => 'Access denied to warehouse',
        'warehouse_id' => $warehouseId
      ], 403);
    }

    try {
      $query = DB::table('Orders')
        ->leftJoin('OrderStatus', 'Orders.IDOrderStatus', '=', 'OrderStatus.IDOrderStatus')
        ->leftJoin('Kontrahent', 'Orders.IDAccount', '=', 'Kontrahent.IDKontrahenta')
        ->leftJoin('order_sources', function ($join) use ($warehouseId) {
          $join->on('Orders.IDOrder', '=', 'order_sources.order_id')
            ->where('order_sources.warehouse_id', '=', $warehouseId);
        })
        ->where('Orders.IDWarehouse', $warehouseId)
        ->select([
          'Orders.IDOrder',
          'Orders.Number',
          'Orders.Created',
          'Orders.Modified',
          'Orders.Remarks',
          'Orders._OrdersTempDecimal2 as baselinker_order_id',
          'Orders._OrdersTempString1 as invoice_number',
          'Orders._OrdersTempString7 as order_sources',
          'Orders._OrdersTempString8 as external_order_id',
          'Orders._OrdersTempString9 as user_login',
          'OrderStatus.Name as status',
          'Kontrahent.Nazwa as customer_name',
          'Kontrahent.Email as customer_email',
          'order_sources.source_type',
          'order_sources.api_client_id',
          'order_sources.external_order_id as source_external_id'
        ]);

      // Apply filters
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
        $query->where(function ($q) use ($request) {
          $q->where('Orders.IDOrder', $request->order_id)
            ->orWhere('Orders.Number', 'like', '%' . $request->order_id . '%')
            ->orWhere('Orders._OrdersTempDecimal2', $request->order_id)
            ->orWhere('order_sources.external_order_id', $request->order_id);
        });
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
            'Towar.KodKreskowy as ean',
            'Towar.KodProducenta as manufacturer_code'
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
   * Create new order via API
   */
  public function createOrder(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'warehouse_id' => 'required|integer',
      'external_order_id' => 'nullable|string',
      'customer' => 'required|array',
      'customer.name' => 'required|string',
      'customer.email' => 'required|email',
      'customer.phone' => 'nullable|string',
      'delivery' => 'required|array',
      'delivery.fullname' => 'required|string',
      'delivery.address' => 'required|string',
      'delivery.city' => 'required|string',
      'delivery.postcode' => 'required|string',
      'delivery.country_code' => 'required|string|size:2',
      'delivery.method' => 'required|string',
      'products' => 'required|array|min:1',
      'products.*.ean' => 'required|string',
      'products.*.name' => 'required|string',
      'products.*.quantity' => 'required|numeric|min:0.01',
      'products.*.price_brutto' => 'required|numeric|min:0',
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

    $client = $request->api_client;
    $warehouseId = $request->warehouse_id;

    // Check warehouse access
    if (!$client->canAccessWarehouse($warehouseId)) {
      return response()->json([
        'error' => 'Access denied to warehouse',
        'warehouse_id' => $warehouseId
      ], 403);
    }

    try {
      DB::beginTransaction();

      // Transform API request to BaseLinker format
      $orderData = $this->transformApiOrderToBaseLinkerFormat($request->all());

      // Use the existing import logic but mark as API source
      $customerId = $this->importBLController->findOrCreateKontrahent($orderData, $warehouseId);

      $uwagi = 'API Order - External ID: ' . ($request->external_order_id ?? 'none') . ' ' .
        ($request->user_comments ?: $request->admin_comments ?: '');

      $orderDate = now()->format('Y-m-d H:i:s');
      $orderStatus = 'W realizacji'; // Default status for API orders
      $paymentType = DB::table('PaymentTypes')->where('Name', $request->payment_method ?? 'Przelew')->value('IDPaymentType');

      // Handle delivery method
      $idTransport = DB::table('RodzajTransportu')->where('Nazwa', $request->delivery['method'])->value('IDRodzajuTransportu');
      if (!$idTransport) {
        DB::table('RodzajTransportu')->insert([
          'Nazwa' => $request->delivery['method'],
          'Utworzono' => now(),
          'Zmodyfikowano' => now(),
        ]);
        $idTransport = DB::table('RodzajTransportu')->where('Nazwa', $request->delivery['method'])->value('IDRodzajuTransportu');
      }

      $Number = $this->importBLController->lastNumber('ZO', $warehouseId);

      // Create order using stored procedure
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

      // Save order details
      $this->importBLController->saveOrderDetails($orderData, $IDOrder, $warehouseId);

      // Save products
      $this->importBLController->writeProductsOrder($orderData, $IDOrder, $warehouseId, $uwagi);

      // Mark order as API source
      OrderSource::createForApi(
        $IDOrder,
        $warehouseId,
        $client->id,
        $request->external_order_id,
        [
          'api_version' => 'v1',
          'created_via' => 'client_api',
          'original_data' => $request->all()
        ]
      );

      // Update order with API-specific fields
      DB::table('Orders')->where('IDOrder', $IDOrder)->update([
        '_OrdersTempString7' => 'API_' . $client->name,
        '_OrdersTempString8' => $request->external_order_id,
        '_OrdersTempString9' => 'API_CLIENT_' . $client->id
      ]);

      DB::commit();

      LogOrder::create([
        'IDWarehouse' => $warehouseId,
        'number' => $request->external_order_id ?? $IDOrder,
        'type' => 3,
        'message' => "API Order created by client: {$client->name}"
      ]);

      return response()->json([
        'success' => true,
        'data' => [
          'order_id' => $IDOrder,
          'order_number' => $Number,
          'external_order_id' => $request->external_order_id,
          'status' => $orderStatus
        ]
      ], 201);
    } catch (\Exception $e) {
      DB::rollBack();

      Log::error('API createOrder failed', [
        'client_id' => $client->id,
        'warehouse_id' => $warehouseId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'error' => 'Internal server error',
        'message' => 'Failed to create order: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Update order status
   */
  public function updateOrderStatus(Request $request, $orderId)
  {
    $validator = Validator::make($request->all(), [
      'warehouse_id' => 'required|integer',
      'status' => 'required|string'
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
        'error' => 'Access denied to warehouse',
        'warehouse_id' => $warehouseId
      ], 403);
    }

    try {
      // Find order
      $order = DB::table('Orders')
        ->where('IDWarehouse', $warehouseId)
        ->where(function ($query) use ($orderId) {
          $query->where('IDOrder', $orderId)
            ->orWhere('Number', $orderId)
            ->orWhere('_OrdersTempString8', $orderId); // external_order_id
        })
        ->first();

      if (!$order) {
        return response()->json([
          'error' => 'Order not found',
          'order_id' => $orderId
        ], 404);
      }

      // Check if order came from API (can only update API orders)
      $orderSource = OrderSource::where('order_id', $order->IDOrder)
        ->where('warehouse_id', $warehouseId)
        ->first();

      if ($orderSource && $orderSource->source_type !== OrderSource::SOURCE_API) {
        return response()->json([
          'error' => 'Cannot update non-API order',
          'source_type' => $orderSource->source_type
        ], 403);
      }

      // Get status ID
      $statusId = DB::table('OrderStatus')->where('Name', $request->status)->value('IDOrderStatus');
      if (!$statusId) {
        return response()->json([
          'error' => 'Invalid status',
          'status' => $request->status
        ], 400);
      }

      // Update order status
      DB::table('Orders')
        ->where('IDOrder', $order->IDOrder)
        ->update([
          'IDOrderStatus' => $statusId,
          'Modified' => now()
        ]);

      LogOrder::create([
        'IDWarehouse' => $warehouseId,
        'number' => $order->_OrdersTempString8 ?? $order->IDOrder,
        'type' => 18,
        'message' => "Status changed to: {$request->status} via API by client: {$client->name}"
      ]);

      return response()->json([
        'success' => true,
        'data' => [
          'order_id' => $order->IDOrder,
          'status' => $request->status,
          'updated_at' => now()->toISOString()
        ]
      ]);
    } catch (\Exception $e) {
      Log::error('API updateOrderStatus failed', [
        'client_id' => $client->id,
        'warehouse_id' => $warehouseId,
        'order_id' => $orderId,
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'error' => 'Internal server error',
        'message' => 'Failed to update order status'
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
        'name' => $product['name'],
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
