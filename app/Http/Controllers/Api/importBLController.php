<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseLinkerController;
use App\Models\LogOrder;
use App\Models\ForTtn;
use App\Models\Collect;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class importBLController extends Controller
{
    // Deadlock retry configuration
    const MAX_DEADLOCK_RETRIES = 3;
    const DEADLOCK_RETRY_BASE_DELAY_MS = 100;

    private $warehouses;
    private $statuses = [];
    private $invoices = [];
    private $orderSources = [];
    private $BL;
    private $warehouse = '';




    public function __construct($warehouseId = null, $orderId = null)
    {

        if ($warehouseId && $orderId) {

            $this->importSingleOrder($warehouseId, $orderId);
        } else {
            $this->runAll();
        }
    }

    public function importSingleOrder($warehouseId, $orderId)
    {
        // Получаем sklad_token только для нужного склада
        $sklad_token = DB::table('settings')
            ->where('obj_name', 'sklad_token')
            ->where('for_obj', $warehouseId)
            ->value('value');

        $this->BL = new BaseLinkerController($sklad_token);

        // Импортируем только один заказ
        $this->GetOrders($warehouseId, $orderId);
        $number = DB::table('orders')
            ->where('_OrdersTempDecimal2', $orderId)
            ->where('IDWarehouse', $warehouseId)
            ->value('Number');

        if ($number) {
            return $number;
        } else {
            return 'Error import order';
        }
    }

    private function runAll()
    {
        /* Get all warehouse for loop */
        $this->warehouses = $this->getAllWarehouses();

        foreach ($this->warehouses as $a_warehouse) {
            if ($this->shouldExecute($a_warehouse)) {
                $this->BL = new BaseLinkerController($a_warehouse->sklad_token);
                $this->GetOrders($a_warehouse->warehouse_id);
                $this->getJournalList($a_warehouse);
                $this->updateLastExecuted($a_warehouse->warehouse_id);
            }
        }
    }

    private function getJournalList($a_warehouse)
    {
        $last_log_id = $a_warehouse->last_log_id;

        $parameters = [
            /*
            Event type:
            1 - Order creation
            2 - DOF download (order confirmation)
            3 - Payment of the order
            4 - Removal of order/invoice/receipt
            5 - Merging the orders
            6 - Splitting the order
            7 - Issuing an invoice
            8 - Issuing a receipt
            9 - Package creation
            10 - Deleting a package
            11 - Editing delivery data
            12 - Adding a product to an order
            13 - Editing the product in the order
            14 - Removing the product from the order
            15 - Adding a buyer to a blacklist
            16 - Editing order data
            17 - Copying an order
            18 - Order status change
            19 - Invoice deletion
            20 - Receipt deletion
            21 - Editing invoice data

            object_id:
            Additional information, depending on the event type:
            5 - ID of the merged order
            6 - ID of the new order created by the order separation
            7 - Invoice ID
            9 - Created parcel ID
            10 - Deleted parcel ID
            14 - Deleted product ID
            17 - Created order ID
            18 - Order status ID
            */
            'logs_types' => [7, 9, 11, 12, 13, 14, 16, 18]
        ];

        if ($last_log_id > 0) {
            $parameters['last_log_id'] = $last_log_id;
        }

        $response = $this->BL->getJournalList($parameters);

        if (!isset($response['status']) || $response['status'] != "SUCCESS") {
            return;
        }

        foreach ($response['logs'] as $log) {
            // Log::info("Processing log_id: " . $log['log_id'] . " for order_id: " . $log['order_id'] . " in warehouse: " . $a_warehouse->warehouse_id);
            // Log:
            // info("Log details: " . $a_warehouse->last_log_id);
            $last_log_id =  $log['log_id'];
            if ($a_warehouse->last_log_id == $log['log_id']) continue;
            switch ($log['log_type']) {
                case 7:
                    $this->changeInvoiceOrder([
                        'a_log' => $log,
                        'a_warehouse' => $a_warehouse,
                    ]);
                    break;
                case 9:
                    $this->changePackageOrder([
                        'a_log' => $log,
                        'a_warehouse' => $a_warehouse,
                    ]);
                case 11:
                    $this->changeDeliveryDataOrder([
                        'a_log' => $log,
                        'a_warehouse' => $a_warehouse,
                    ]);
                    break;
                case 12:
                case 13:
                case 14:
                    $this->changeProductsOrder([
                        'a_log' => $log,
                        'a_warehouse' => $a_warehouse,
                    ]);
                    break;
                case 16:
                    $this->changeDataOrder([
                        'a_log' => $log,
                        'a_warehouse' => $a_warehouse,
                    ]);
                    break;
                case 18:
                    $this->changeStatusOrder([
                        'a_log' => $log,
                        'a_warehouse' => $a_warehouse,
                    ]);
                    break;
                default:
                    $this->writeLog([
                        'a_log' => $log,
                        'a_warehouse' => $a_warehouse,
                    ]);
                    break;
            }
            /* { "log_id": 456269, "log_type": 13, "order_id": 6911942, "object_id": 0, "date": 1516369287 }, */
        }
        $a_warehouse->last_log_id = $last_log_id;
        // Log::info("Updating last_log_id for warehouse: " . $a_warehouse->warehouse_id . " to " . $last_log_id);
        DB::table('settings')
            ->updateOrInsert(
                ['obj_name' => 'last_log_id', 'for_obj' => $a_warehouse->warehouse_id, 'key' => $a_warehouse->warehouse_id],
                ['value' => $last_log_id]
            );
        /* { "status": "SUCCESS", "logs": [ { "log_id": 82280791, "log_type": 12, "order_id": 12017786, "object_id": 0, "date": 1745384688 }, { "log_id": 82280827, "log_type": 13, "order_id": 12017786, "object_id": 0, "date": 1745384739 } ] } */
    }

    private function writeLog($param)
    {
        if (DB::table('Orders')->where('_OrdersTempDecimal2', $param['a_log']['order_id'])->where('IDWarehouse', $param['a_warehouse']->warehouse_id)->exists()) {
            $log_type_name = $this->BL->log_type[$param['a_log']['log_type']];
            $object_id = 0;
            if ($param['a_log']['object_id'] > 0 && $param['a_log']['object_id'] < 19) {
                $object_id = $this->BL->object_id[$param['a_log']['object_id']];
            }
            $date = Carbon::createFromTimestamp($param['a_log']['date'])->format('Y-m-d H:i:s');
            if (DB::table('Orders')->where('_OrdersTempDecimal2', $param['a_log']['order_id'])->where('IDWarehouse', $param['a_warehouse']->warehouse_id)->where('IDOrderStatus', 23)->exists()) {
                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $param['a_log']['order_id'],
                    'type' => $param['a_log']['log_type'],
                    'message' => "Log type: {$log_type_name},  Date: {$date}, Log ID: {$param['a_log']['log_id']}, Object ID: {$object_id}"
                ]);
                $this->changeProductsOrder($param);
            } else {
                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $param['a_log']['order_id'],
                    'type' => $param['a_log']['log_type'],
                    'message' => "Log type: {$log_type_name},  Date: {$date}, Object ID: {$object_id}"
                ]);
            }
        }
    }

    private function changeDataOrder($param)
    {
        $o_orderBL = $this->BL->getOrders(['order_id' => $param['a_log']['order_id']]);
        if (!isset($o_orderBL['status']) || $o_orderBL['status'] != "SUCCESS") {
            return;
        }
        $orderData = collect($o_orderBL['orders'])->firstWhere('order_id', $param['a_log']['order_id']);
        if (!$orderData) {

            //TO DONE:message start "Nie znaleziono zamówienia o" status  in BL ['W realizacji', ' NIE WYSYŁAJ', 'Nie wysyłać', 'Nowe zamówienia', 'NIE WYSYŁAJ'] in order field "get_unconfirmed_orders": false
            //create order in LM
            $o_orderBL = $this->BL->getOrders(['get_unconfirmed_orders' => false, 'order_id' => $param['a_log']['order_id']]);
            if (!isset($o_orderBL['status']) || $o_orderBL['status'] != "SUCCESS") {
                return;
            }
            $orderData = collect($o_orderBL['orders'])->firstWhere('order_id', $param['a_log']['order_id']);
            if (!$orderData) {
                return;
            }
            if (in_array($this->BL->getStatusName($orderData['order_status_id']), ['W realizacji', ' NIE WYSYŁAJ', 'Nie wysyłać', 'Nowe zamówienia', 'NIE WYSYŁAJ'])) {
                $this->importOrder($orderData, $param['a_warehouse']->warehouse_id);
                return;
            } else {
                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $param['a_log']['order_id'],
                    'type' => 16,
                    'message' => 'Nie znaleziono zamówienia o ID: ' . $param['a_log']['order_id']
                ]);
                return;
            }
        }
        $LM_order = DB::table('Orders')
            ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
            ->where('IDWarehouse', $param['a_warehouse']->warehouse_id);

        $OrderStatusLMName =  $LM_order
            ->leftJoin('OrderStatus', 'Orders.IDOrderStatus', '=', 'OrderStatus.IDOrderStatus')
            ->value('OrderStatus.Name');
        $orderSources = $orderData['order_source'] . '_' .
            (isset($this->orderSources['sources'][$orderData['order_source']])
                ? collect($this->orderSources['sources'][$orderData['order_source']])->get($orderData['order_source_id'])
                : null);
        //IDAccount
        $CustomerId =  $this->findOrCreateKontrahent($orderData, $param['a_warehouse']->warehouse_id);
        if (in_array($OrderStatusLMName, ['W realizacji', 'Anulowane', ' NIE WYSYŁAJ', 'Nie wysyłać', 'Anulowany', 'Nowe zamówienia', 'NIE WYSYŁAJ'])) {
            $this->saveOrderDetails($orderData, $param['a_log']['order_id'],  $param['a_warehouse']->warehouse_id);

            $this->executeWithRetry(function () use ($param, $CustomerId, $orderSources, $orderData) {
                $o_order = DB::table('Orders')
                    ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
                    ->where('IDWarehouse', $param['a_warehouse']->warehouse_id);

                $o_order->update([
                    'IDAccount' => $CustomerId,
                    '_OrdersTempString7' => $orderSources,
                    '_OrdersTempString8' => $orderData['external_order_id'],
                    '_OrdersTempString9' => $orderData['user_login']
                ]);
                $IDOrder = DB::table('Orders')
                    ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
                    ->where('IDWarehouse', $param['a_warehouse']->warehouse_id)->value('IDOrder');
                DB::connection('second_mysql')->table('order_details')->updateOrInsert(
                    ['IDWarehouse' => $param['a_warehouse']->warehouse_id, 'order_id' => $IDOrder],
                    [
                        'currency' => $orderData['currency'] ?? null,
                        'order_source' => $orderData['order_source'] ?? null,
                        'order_source_id' => $orderData['order_source_id'] ?? null,
                        'payment_method' => $orderData['payment_method'] ?? null,
                        'payment_method_cod' => $orderData['payment_method_cod'] ?? null,
                        'payment_done' => $orderData['payment_done'] ?? null,
                        'delivery_method' => $orderData['delivery_method'] ?? null,
                        'delivery_price' => $orderData['delivery_price'] ?? null,
                        'delivery_package_module' => $orderData['delivery_package_module'] ?? null,
                        'delivery_package_nr' => $orderData['delivery_package_nr'] ?? null,
                        'delivery_fullname' => $orderData['delivery_fullname'] ?? null,
                        'delivery_company' => $orderData['delivery_company'] ?? null,
                        'delivery_address' => $orderData['delivery_address'] ?? null,
                        'delivery_city' => $orderData['delivery_city'] ?? null,
                        'delivery_state' => $orderData['delivery_state'] ?? null,
                        'delivery_postcode' => $orderData['delivery_postcode'] ?? null,
                        'delivery_country_code' => $orderData['delivery_country_code'] ?? null,
                        'delivery_point_id' => $orderData['delivery_point_id'] ?? null,
                        'delivery_point_name' => $orderData['delivery_point_name'] ?? null,
                        'delivery_point_address' => $orderData['delivery_point_address'] ?? null,
                        'delivery_point_postcode' => $orderData['delivery_point_postcode'] ?? null,
                        'delivery_point_city' => $orderData['delivery_point_city'] ?? null,
                        'invoice_fullname' => $orderData['invoice_fullname'] ?? null,
                        'invoice_company' => $orderData['invoice_company'] ?? null,
                        'invoice_nip' => $orderData['invoice_nip'] ?? null,
                        'invoice_address' => $orderData['invoice_address'] ?? null,
                        'invoice_city' => $orderData['invoice_city'] ?? null,
                        'invoice_state' => $orderData['invoice_state'] ?? null,
                        'invoice_postcode' => $orderData['invoice_postcode'] ?? null,
                        'invoice_country_code' => $orderData['invoice_country_code'] ?? null,
                        'delivery_country' => $orderData['delivery_country'] ?? null,
                        'invoice_country' => $orderData['invoice_country'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $this->checkOrder($o_order->value('IDOrder'), $param['a_warehouse']->warehouse_id);
            });
        } else {
            // Get current values from the database
            $currentData = $LM_order->first([
                'IDAccount',
                '_OrdersTempString7',
                '_OrdersTempString8',
                '_OrdersTempString9'
            ]);

            // Only update if data is different
            if (
                !$currentData ||
                $currentData->IDAccount != $CustomerId ||
                $currentData->_OrdersTempString7 != $orderSources ||
                $currentData->_OrdersTempString8 != $orderData['external_order_id'] ||
                $currentData->_OrdersTempString9 != $orderData['user_login']
            ) {
                $body = 'Zamówienie: ' . $param['a_log']['order_id'] . ' ma status: ' . $OrderStatusLMName . ' i nie można zmienić danych zamówienia.';
                Mail::raw($body, function ($message) use ($param) {
                    $message->to('khanenko.igor@gmail.com')
                        ->subject($this->getWarehouseSymbol($param['a_warehouse']->warehouse_id) . ' Zamówienie: ' . $param['a_log']['order_id'] . ' i nie można zmienić danych zamówienia dataOrder.');
                });
            }
        }
        LogOrder::create([
            'IDWarehouse' => $param['a_warehouse']->warehouse_id,
            'number' => $param['a_log']['order_id'],
            'type' => 16,
            'message' => 'Zmieniono dane zamówienia: ' . $param['a_log']['order_id']
        ]);
    }

    private function changeDeliveryDataOrder($param)
    {
        $o_orderBL = $this->BL->getOrders(['order_id' => $param['a_log']['order_id']]);
        if (!isset($o_orderBL['status']) || $o_orderBL['status'] != "SUCCESS") {
            return;
        }
        $orderData = collect($o_orderBL['orders'])->firstWhere('order_id', $param['a_log']['order_id']);
        LogOrder::create([
            'IDWarehouse' => $param['a_warehouse']->warehouse_id,
            'number' => $param['a_log']['order_id'],
            'type' => 11,
            'message' => 'Zmieniono dane dostawy dla zamówienia: ' . $param['a_log']['order_id']
        ]);
        $LM_order = DB::table('Orders')
            ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
            ->where('IDWarehouse', $param['a_warehouse']->warehouse_id);
        $idTransportLM = $LM_order->value('IDTransport');

        $OrderStatusLMName =  $LM_order
            ->leftJoin('OrderStatus', 'Orders.IDOrderStatus', '=', 'OrderStatus.IDOrderStatus')
            ->value('OrderStatus.Name');
        $idTransportBL = null;
        if (isset($orderData['delivery_method']) && $orderData['delivery_method'] !== null) {
            $idTransportBL = DB::table('RodzajTransportu')->where('Nazwa', $orderData['delivery_method'])->value('IDRodzajuTransportu');
        }
        if ($idTransportLM != $idTransportBL) {


            if (in_array($OrderStatusLMName, ['W realizacji', 'Anulowane', ' NIE WYSYŁAJ', 'Nie wysyłać', 'Anulowany', 'Nowe zamówienia', 'NIE WYSYŁAJ'])) {
                $this->saveOrderDetails($orderData, $LM_order->value('IDOrder'),  $param['a_warehouse']->warehouse_id);
                if (!$idTransportBL) {
                    DB::table('RodzajTransportu')->insert([
                        'Nazwa' => $orderData['delivery_method'],
                        'Utworzono' => now(),
                        'Zmodyfikowano' => now(),
                    ]);
                    $idTransportBL = DB::table('RodzajTransportu')->where('Nazwa', $orderData['delivery_method'])->value('IDRodzajuTransportu');
                }

                $this->executeWithRetry(function () use ($param, $idTransportBL) {
                    DB::table('Orders')
                        ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
                        ->where('IDWarehouse', $param['a_warehouse']->warehouse_id)
                        ->update([
                            'IDTransport' => $idTransportBL,
                            'Modified' => now(),
                        ]);
                });
            } else {
                $body = 'Zamówienie: ' . $param['a_log']['order_id'] . ' ma status: ' . $OrderStatusLMName . ' i nie można zmienić danych dostawy.';
                Mail::raw($body, function ($message) use ($param) {
                    $message->to('khanenko.igor@gmail.com')
                        ->subject($this->getWarehouseSymbol($param['a_warehouse']->warehouse_id) . ' Zamówienie: ' . $param['a_log']['order_id'] . ' i nie można zmienić danych dostawy.');
                });
            }
        }
    }

    private function changePackageOrder($param)
    {
        $createdParcelID = $param['a_log']['object_id'];
        $OrderPackages = $this->BL->getOrderPackages(['order_id' => $param['a_log']['order_id']]);
        if (!isset($OrderPackages['status']) || $OrderPackages['status'] != "SUCCESS") {
            return;
        }
        $o_order = DB::table('Orders')
            ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
            ->where('IDWarehouse', $param['a_warehouse']->warehouse_id);
        $package = collect($OrderPackages['packages'])->firstWhere('parcel_id', $createdParcelID);
        if (!$package) {
            // retrieve the bill of lading from the BL
            $package = $this->BL->getOrderPackages(['order_id' => $param['a_log']['order_id']]);
            if (!isset($package['status']) || $package['status'] != "SUCCESS") {
                return;
            }
            $package = collect($package['packages'])->firstWhere('package_id', $createdParcelID);
            if (!$package) {
                return;
            }
            $this->executeWithRetry(function () use ($package, $o_order) {
                $o_order->update(['_OrdersTempString2' => $package['courier_package_nr'], 'Modified' => now()]);
            });
        }
        $orderStatus = $o_order->leftJoin('OrderStatus as os', 'Orders.IDOrderStatus', 'os.IDOrderStatus')->value('os.Name');
        if (in_array($orderStatus, ['Do wysłania', 'Kompletowanie'])) {
            $this->executeWithRetry(function () use ($package, $o_order) {
                $o_order->update(['_OrdersTempString2' => $package['courier_package_nr'], 'Modified' => now()]);
            });
            LogOrder::create([
                'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                'number' => $param['a_log']['order_id'],
                'type' => 9,
                'message' => 'Zmieniono numer paczki na: ' . $package['courier_package_nr'] . ' dla zamówienia: ' . $param['a_log']['order_id']
            ]);
        } else {
            // check for return
            $package = $this->BL->getOrderReturns(['order_id' => $param['a_log']['order_id']]);
            if (!isset($package['status']) || $package['status'] != "SUCCESS") {
                return;
            }
            foreach (collect($package['returns']) as $return) {
                $this->executeWithRetry(function () use ($return, $o_order) {
                    $o_order
                        ->update([
                            '_OrdersTempString4' => $return['delivery_package_nr'], //Nr. Zwrotny BL
                            '_OrdersTempString10' => $return['reference_number'], //Numer Zwrotu
                            'Modified' => now()
                        ]);
                });
                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $param['a_log']['order_id'],
                    'type' => 9,
                    'message' => 'Zmieniono numer paczki na: ' . $return['delivery_package_nr'] . ' dla zamówienia: ' . $param['a_log']['order_id']
                ]);
            }
        }
    }

    private function changeInvoiceOrder($param)
    {
        $newOrderInviceBL = $param['a_log']['object_id'];
        $this->invoices = $this->BL->getInvoices(['order_id' => $param['a_log']['order_id']]);
        $invoice_number = collect($this->invoices['invoices'])->firstWhere('order_id', $param['a_log']['order_id'])['number'] ?? null;
        $order = DB::table('Orders')
            ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
            ->where('IDWarehouse', $param['a_warehouse']->warehouse_id);
        $OrderStatusLMName =  $order
            ->leftJoin('OrderStatus', 'Orders.IDOrderStatus', '=', 'OrderStatus.IDOrderStatus')
            ->value('OrderStatus.Name');
        if (in_array($OrderStatusLMName, ['W realizacji', 'Anulowane', ' NIE WYSYŁAJ', 'Nie wysyłać', 'Anulowany', 'Nowe zamówienia', 'NIE WYSYŁAJ'])) {
            $this->executeWithRetry(function () use ($param, $invoice_number) {
                DB::table('Orders')
                    ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
                    ->where('IDWarehouse', $param['a_warehouse']->warehouse_id)
                    ->where('_OrdersTempString1', '!=', $invoice_number)
                    ->update([
                        '_OrdersTempString1' =>  $invoice_number,
                        'Modified' => now(),
                    ]);
            });
        }

        LogOrder::create([
            'IDWarehouse' => $param['a_warehouse']->warehouse_id,
            'number' => $param['a_log']['order_id'],
            'type' => 7,
            'message' => 'Zmieniono numer faktury na: ' . $invoice_number . ' dla zamówienia: ' . $param['a_log']['order_id']
        ]);
    }



    private function changeStatusOrder($param)
    {

        $newOrderStatusBL = $param['a_log']['object_id'];
        $newOrderStatusBLName = $this->BL->getStatusName($newOrderStatusBL);

        // Use a fresh query builder for each attempt to avoid stale data
        $order = DB::table('Orders')
            ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
            ->where('IDWarehouse', $param['a_warehouse']->warehouse_id);

        $OrderStatusLMName = $order
            ->leftJoin('OrderStatus', 'Orders.IDOrderStatus', '=', 'OrderStatus.IDOrderStatus')
            ->value('OrderStatus.Name');

        $newOrderStatusLMID = DB::table('OrderStatus')->where('Name', $newOrderStatusBLName)->value('IDOrderStatus');

        if ($newOrderStatusBLName != $OrderStatusLMName) {
            if ((in_array($newOrderStatusBLName, ['W realizacji', 'Anulowane', 'Nie wysyłać']) && in_array($OrderStatusLMName, ['W realizacji', 'Anulowane', ' NIE WYSYŁAJ', 'Nie wysyłać', 'Anulowany', 'Nowe zamówienia', 'NIE WYSYŁAJ']))
                ||
                (($newOrderStatusBLName == 'Kompletowanie') && in_array($OrderStatusLMName, ['W realizacji', 'Kompletowanie']))
                ||
                (in_array($newOrderStatusBLName, ['Do wysłania', 'Wysłane', 'Wysłany', 'Do odbioru', 'Odebrane']) && in_array($OrderStatusLMName, ['Kompletowanie', 'Do wysłania', 'Wysłane', 'Do odbioru', 'Odebrane', 'Wysłany']))
            ) {
                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $param['a_log']['order_id'],
                    'type' => 18,
                    'message' => 'Status zmieniony na: ' . $newOrderStatusBLName . ' od statusu w Lomag: ' . $OrderStatusLMName . ' dla zamówienia: ' . $param['a_log']['order_id']
                ]);

                // Create a fresh query builder for the update to avoid deadlocks
                $order
                    ->update(['IDOrderStatus' => $newOrderStatusLMID]);
            } else {
                $body = 'Status zamówienia w BaseLinker: ' . $newOrderStatusBLName . ' nie jest zgodny ze statusem zamówienia w Panel: ' . $OrderStatusLMName . ' dla zamówienia: ' . $param['a_log']['order_id'];
                Mail::raw($body, function ($message) use ($param) {
                    $message->to('khanenko.igor@gmail.com')
                        ->subject($this->getWarehouseSymbol($param['a_warehouse']->warehouse_id) . ' Status zmiany zamówienia w BaseLinker' . $param['a_log']['object_id']);
                });

                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $param['a_log']['order_id'],
                    'type' => 91118,
                    'message' => 'Status zamówienia w BaseLinker: ' . $newOrderStatusBLName . ' nie jest zgodny ze statusem zamówienia w Lomag: ' . $OrderStatusLMName . ' dla zamówienia: ' . $param['a_log']['order_id']
                ]);
            }
        }
    }


    private function changeProductsOrder($param)
    {

        $parameters = [
            'order_id' => $param['a_log']['order_id'],
        ];

        $response = $this->BL->getOrders($parameters);
        if (!isset($response['status']) || $response['status'] != "SUCCESS") {
            return;
        }
        $orderPN = DB::table('Orders')
            ->where('_OrdersTempDecimal2', $param['a_log']['order_id'])
            ->where('IDWarehouse', $param['a_warehouse']->warehouse_id);
        $OrderStatusLMName = $orderPN
            ->leftJoin('OrderStatus', 'Orders.IDOrderStatus', '=', 'OrderStatus.IDOrderStatus')
            ->value('OrderStatus.Name');

        foreach ($response['orders'] as $order) {
            if (in_array($OrderStatusLMName, ['W realizacji', 'Anulowane', ' NIE WYSYŁAJ', 'Nie wysyłać', 'Anulowany', 'Nowe zamówienia', 'NIE WYSYŁAJ'])) {

                /*
                23|W realizacji - zamiana
                Sprawdź z nami to zamówienie w tych statusach:
                16|Nowe zamówienia
                19|Anulowane
                27| NIE WYSYŁAJ
                29|Nie wysyłać
                32|Zawieszony
                33|Anulowany
                43|NIE WYSYŁAJ
                następnie zmienić status na 23 i towary

                W przeciwnym razie zapisujemy dziennik i nic nie zmieniamy

                */

                // this order has already been imported by the integrator
                $a_order = $orderPN->first();
                if ($a_order) {

                    LogOrder::create([
                        'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                        'number' => $order['order_id'],
                        'type' => $param['a_log']['log_type'],
                        'message' => "Change products: {$order['order_id']}"
                    ]);


                    $uwagi = 'Nr zamówienia w BaseLinker: ' . $order['order_id'] . ' Zmiana zamówienia w BaseLinker ' . $order['user_comments'] ?: $order['admin_comments'] ?: '';

                    try {
                        $this->executeWithRetry(function () use ($a_order, $order, $param, $uwagi) {
                            DB::beginTransaction();
                            DB::table('OrderLines')->where('IDOrder', $a_order->IDOrder)->delete();
                            $this->writeProductsOrder($order, $a_order->IDOrder, $param['a_warehouse']->warehouse_id, $uwagi);

                            DB::table('Orders')->where('IDOrder', $a_order->IDOrder)->update([
                                'Modified' => now(),
                                '_OrdersTempString5' => '',
                                'Remarks' => DB::raw("ISNULL(Remarks, '') + ' Products chenged'"),
                            ]);

                            DB::commit();
                        });
                    } catch (\Throwable $th) {
                        DB::rollBack();
                        throw $th;
                    }
                }
            } else {
                $body = "Change products Order: {$order['order_id']}";
                Mail::raw($body, function ($message) use ($param) {
                    $message->to('khanenko.igor@gmail.com')
                        ->subject($this->getWarehouseSymbol($param['a_warehouse']->warehouse_id) . " Change products Order: {$param['a_log']['order_id']}");
                });

                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $order['order_id'],
                    'type' =>  (int)('911' . $param['a_log']['log_type']),
                    'message' => "Change products Order: {$param['a_log']['order_id']}"
                ]);
            }
        }
    }

    /**
     * Check if the task should be executed based on the interval and last execution time.
     *
     * @param object $a_warehouse
     * @return bool
     */
    private function shouldExecute($a_warehouse)
    {
        if ($a_warehouse->interval_minutes < 1) {
            return false; // If the interval is not specified or is 0, do not execute
        }

        if ($a_warehouse->last_executed_at == 0 || $a_warehouse->last_executed_at == '') {
            return true; // If the task has never been performed, run
        }

        $nextExecutionTime = Carbon::parse($a_warehouse->last_executed_at)->addMinutes($a_warehouse->interval_minutes);
        return Carbon::now()->greaterThanOrEqualTo($nextExecutionTime);
    }

    private function updateLastExecuted($warehouseId)
    {
        DB::table('settings')
            ->updateOrInsert(
                ['obj_name' => 'last_executed_at', 'for_obj' => $warehouseId, 'key' => $warehouseId],
                ['value' => now()]
            );
    }

    public function getAllWarehouses()
    {
        return DB::select("SELECT
    COALESCE(for_obj, '') AS warehouse_id,
    MAX(CASE WHEN obj_name = 'sklad_token' THEN value ELSE '' END) AS sklad_token,
    COALESCE(MAX(CASE WHEN obj_name = 'last_log_id' THEN value END), 0) AS last_log_id,
    COALESCE(MAX(CASE WHEN obj_name = 'interval_minutes' THEN value END), 0) AS interval_minutes,
    COALESCE(MAX(CASE WHEN obj_name = 'last_executed_at' THEN value END), '') AS last_executed_at
FROM
    settings
WHERE
    obj_name IN ('sklad_token', 'last_log_id', 'interval_minutes', 'last_executed_at')
GROUP BY
    for_obj
HAVING
    MAX(CASE WHEN obj_name = 'sklad_token' THEN value ELSE '' END) != ''");
    }

    public function GetOrders($idMagazynu, $order_id = null)
    {
        $this->statuses = $this->BL->statuses;
        $w_realizacji_id = $this->BL->getStatusId('W realizacji');
        $this->orderSources = $this->BL->getOrderSources();


        if ($order_id) {
            $parameters['order_id'] = $order_id;
        } else {
            $parameters = [
                // 'order_id' => 12017786,
                // 'get_unconfirmed_orders' => true,
                // 'include_custom_extra_fields' => true,
                'status_id' => $w_realizacji_id,
            ];
        }

        $response = $this->BL->getOrders($parameters);

        if (count($response['orders']) == 100) {

            $cacheKey = "max_date_confirmed_{$idMagazynu}";

            if (cache()->has($cacheKey)) {
                $maxDateConfirmed = cache()->get($cacheKey);
            } else {
                $dateConfirmedArray = array_column($response['orders'], 'date_confirmed');
                if (!empty($dateConfirmedArray)) {
                    $maxDateConfirmed = max($dateConfirmedArray);
                    cache()->put($cacheKey, $maxDateConfirmed, 900);
                }
            }

            $parameters = [
                'date_confirmed_from' => $maxDateConfirmed + 1, // +1 to get orders after the last confirmed order
                'status_id' => $w_realizacji_id,
            ];

            $response = $this->BL->getOrders($parameters);
            $dateConfirmedArray = array_column($response['orders'], 'date_confirmed');
            if (!empty($dateConfirmedArray)) {
                $maxDateConfirmed = max($dateConfirmedArray);
                cache()->put($cacheKey, $maxDateConfirmed, 900);
            }
        }
        //$ordersToProcess = array_slice($response['orders'], 0, 80); // Process only the first 80 orders
        $i = 80;

        if (!is_array($response) || !isset($response['orders']) || !is_array($response['orders'])) {
            Log::error("Error in GetOrders: " . json_encode($response));
            throw new \Exception("Invalid response data. Expected an array with 'orders'.");
        }

        foreach ($response['orders'] as $order) {
            // this order has already been imported by the integrator
            if ($i < 0) {
                break;
            }
            if (is_array($order) && isset($order['order_id'])) {
                // Check if transaction exists for this order_id and warehouse
                $existingOrder = DB::table('IntegratorTransactions')
                    ->where('transId', $order['order_id'])
                    ->first();

                if ($existingOrder) {
                    // If IDWarehouse is null, update it
                    if (is_null($existingOrder->IDWarehouse)) {
                        DB::table('IntegratorTransactions')
                            ->where('transId', $order['order_id'])
                            ->update(['IDWarehouse' => $idMagazynu]);
                    }
                    // Now check if transaction exists for this order_id and warehouse
                    $wasImported = DB::table('IntegratorTransactions')
                        ->where('transId', $order['order_id'])
                        ->where('IDWarehouse', $idMagazynu)
                        ->exists();
                } else {
                    $wasImported = false;
                }
            } else {
                Log::error("Invalid order data encountered.", ['order' => $order]);
                throw new \Exception("Invalid order data. Expected an array with 'order_id'.");
            }
            if ($wasImported) {
                // \Log::info("Order already imported", ['order_id' => $order['order_id'], 'warehouse_id' => $idMagazynu,'$i'=> $i]);
                continue;
            }
            DB::table('IntegratorTransactions')->insert([
                'transId' => $order['order_id'],
                'IDWarehouse' => $idMagazynu,
                'typ' => 3,
            ]);
            $this->invoices = $this->BL->getInvoices(['order_id' => $order['order_id']]);
            if (!isset($this->invoices['status']) || $this->invoices['status'] != "SUCCESS") continue;
            $this->importOrder($order, $idMagazynu);
            $i--;
        }
    }

    public function lastNumber($doc, $IDWarehouse)
    {
        $symbol = DB::table('Magazyn')->where('IDMagazynu', $IDWarehouse)->value('Symbol');

        $year = Carbon::now()->format('y');
        $pattern =  $doc . '%/' . $year . ' - ' . $symbol;
        $patternIndex = strlen($doc);
        $patternToEndLen = strlen($symbol) + 6; // 6 characters: " - " + year (2 characters) + "/"

        $res = DB::table('Orders')
            ->select(DB::raw('MAX(CAST(SUBSTRING(Number, ' . ($patternIndex + 1) . ', LEN(Number) - ' . ($patternToEndLen + $patternIndex) . ') AS INT)) as max_number'))
            ->whereRaw('RTRIM(Number) LIKE ?', [$pattern])
            ->whereRaw('ISNUMERIC(SUBSTRING(Number, ' . ($patternIndex + 1) . ', LEN(Number) - ' . ($patternToEndLen + $patternIndex) . ')) <> 0')
            ->value('max_number');

        if ($res === null) {
            return str_replace('%', '1', $pattern);
        }
        return str_replace('%', $res + 1, $pattern);
    }

    public function importOrder(array $orderData, $idMagazynu)
    {
        try {
            DB::beginTransaction();

            $CustomerId = $this->findOrCreateKontrahent($orderData, $idMagazynu);

            $uwagi = 'Nr zamówienia w BaseLinker: ' . $orderData['order_id'] . ' ' . $orderData['user_comments'] ?: $orderData['admin_comments'] ?: '';
            $orderDate = Carbon::parse($orderData['date_add'])->addHours(2)->format('Y-m-d H:i:s');
            $orderStatus = collect($this->statuses)->firstWhere('id', $orderData['order_status_id'])['name'] ?? null;
            $paymentType = DB::table('PaymentTypes')->where('Name', $orderData['payment_method'])->value('IDPaymentType');
            $idTransport = DB::table('RodzajTransportu')->where('Nazwa', $orderData['delivery_method'])->value('IDRodzajuTransportu');
            if (!$idTransport) {
                DB::table('RodzajTransportu')->insert([
                    'Nazwa' => $orderData['delivery_method'],
                    'Utworzono' => now(),
                    'Zmodyfikowano' => now(),
                ]);
                $idTransport = DB::table('RodzajTransportu')->where('Nazwa', $orderData['delivery_method'])->value('IDRodzajuTransportu');
            }

            $invoice_number = collect($this->invoices['invoices'])->firstWhere('order_id', $orderData['order_id'])['number'] ?? null;
            $orderSources = $orderData['order_source'] . '_' .
                (isset($this->orderSources['sources'][$orderData['order_source']])
                    ? collect($this->orderSources['sources'][$orderData['order_source']])->get($orderData['order_source_id'])
                    : null);

            $Number =  $this->lastNumber('ZO', $idMagazynu);
            try {
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
                        $CustomerId, // CustomerId
                        $Number, // IdZamowiena
                        $idMagazynu, // IdMagazynu
                        $uwagi, // Uwagi
                        $orderDate, // OrderDate
                        $orderStatus, // OrdertStatus
                        $paymentType, // PaymentType
                        $idTransport // IDTransport
                    ]
                );
            } catch (\Exception $e) {
                if (class_exists(LogOrder::class)) {
                    DB::table('IntegratorTransactions')->where([
                        'transId' => $orderData['order_id'],
                        'IDWarehouse' => $idMagazynu,
                        'typ' => 3,
                    ])->delete();
                    LogOrder::create([
                        'IDWarehouse' => $idMagazynu,
                        'number' => $orderData['order_id'],
                        'type' => 911,
                        'message' => "Error executing CreateOrder: {$orderData['order_id']}. Details: " . $e->getMessage()
                    ]);
                } else {
                    throw new \Exception("LogOrder model does not exist. Please ensure it is defined.");
                }
                throw new \Exception("Error executing CreateOrder: {$orderData['order_id']}. Details: " . $e->getMessage());
            }

            $IDOrder = DB::table('Orders')->where('Number', $Number)->value('IDOrder');
            $this->saveOrderDetails($orderData, $IDOrder, $idMagazynu);
            $this->saveOrderForTtn($orderData, $IDOrder, $idMagazynu);
            // DB::enableQueryLog();
            $this->writeProductsOrder($orderData, $IDOrder, $idMagazynu, $uwagi);


            $this->executeWithRetry(function () use ($Number, $idMagazynu, $invoice_number, $orderData, $orderSources) {
                DB::table('Orders')->where('Number', $Number)->where('IDWarehouse', $idMagazynu)->update([
                    '_OrdersTempString1' => $invoice_number,
                    '_OrdersTempDecimal2' => $orderData['order_id'],
                    '_OrdersTempString7' => $orderSources,
                    '_OrdersTempString8' => $orderData['external_order_id'],
                    '_OrdersTempString9' => $orderData['user_login']
                ]);
            });

            DB::commit();

            LogOrder::create([
                'IDWarehouse' => $idMagazynu,
                'number' => $orderData['order_id'],
                'type' => 3,
                'message' => "CreateOrder: {$orderData['order_id']}."
            ]);
            // проверить на наличие ошибок в заказа
            //если заказ в статусе реализация и не заполнено поле deliveryMethod - меняем статус невысылач с uvagi
            $this->checkOrder($IDOrder, $idMagazynu);
        } catch (\Exception $e) {
            DB::table('IntegratorTransactions')->where([
                'transId' => $orderData['order_id'],
                'IDWarehouse' => $idMagazynu,
                'typ' => 3,
            ])->delete();
            DB::rollBack();
            throw $e;
        }
    }

    public function checkOrder($IDOrder, $idMagazynu)
    {
        try {
            $order = DB::table('Orders')->where('IDOrder', $IDOrder)->where('IDWarehouse', $idMagazynu)->first();
            $deliveryMethod = DB::connection('second_mysql')->table('order_details')
                ->where('order_id', $IDOrder)->value('delivery_method');
            if ($order && $order->IDOrderStatus == 23 && empty($deliveryMethod)) {
                DB::table('Orders')->where('IDOrder', $IDOrder)->where('IDWarehouse', $idMagazynu)->update([
                    'Remarks' => DB::raw("COALESCE(Remarks, '') + ' Zamówienie nie wysyłane z powodu braku metody dostawy.'"),
                    'Modified' => now(),
                ]);
                $body = "";
                Mail::raw($body, function ($message) use ($idMagazynu, $order) {
                    $message->to('khanenko.igor@gmail.com')
                        ->subject($this->getWarehouseSymbol($idMagazynu) . ' Zamówienie: ' . $order->_OrdersTempDecimal2 . ' nie wysyłane z powodu braku metody dostawy.');
                });
            }
        } catch (\Exception $e) {
            Log::error('Check Order failed', [
                'order_id' => $IDOrder,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function saveOrderForTtn(array $orderData, $IDOrder, $idMagazynu)
    {
        try {
            $forttn = [
                'id_warehouse' => $idMagazynu,
                'delivery_method' => $orderData['delivery_method'] ?? '',
                'order_source' => $orderData['order_source'] ?? '',
                'order_source_id' => $orderData['order_source_id'] ?? 0,
                'order_source_name' => collect($this->orderSources['sources'][$orderData['order_source']] ?? [])->get($orderData['order_source_id']) ?? '',
                'api_service_id' => 0,
                'courier_code' => '',
                'account_id' => 0,
                'info_account' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert if not exists
            $exists = ForTtn::where('id_warehouse', $forttn['id_warehouse'])
                ->where('order_source', $forttn['order_source'])
                ->where('order_source_id', $forttn['order_source_id'])
                ->where('order_source_name', $forttn['order_source_name'])
                ->where('delivery_method', $forttn['delivery_method'])
                ->exists();

            if (!$exists) {
                ForTtn::insert($forttn);
            }
        } catch (\Exception $e) {
            Log::error('ForTtn Insert failed', [
                'order_id' => $IDOrder,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Save order details to the database.
     *
     * @param array $orderData
     * @return void
     */
    public function saveOrderDetails(array $orderData, $IDOrder, $idMagazynu)
    {
        try {
            DB::connection('second_mysql')->table('order_details')->updateOrInsert(
                ['IDWarehouse' => $idMagazynu, 'order_id' => $IDOrder],
                [
                    'currency' => $orderData['currency'] ?? null,
                    'order_source' => $orderData['order_source'] ?? null,
                    'order_source_id' => $orderData['order_source_id'] ?? null,
                    'payment_method' => $orderData['payment_method'] ?? null,
                    'payment_method_cod' => $orderData['payment_method_cod'] ?? null,
                    'payment_done' => $orderData['payment_done'] ?? null,
                    'delivery_method' => $orderData['delivery_method'] ?? null,
                    'delivery_price' => $orderData['delivery_price'] ?? null,
                    'delivery_package_module' => $orderData['delivery_package_module'] ?? null,
                    'delivery_package_nr' => $orderData['delivery_package_nr'] ?? null,
                    'delivery_fullname' => $orderData['delivery_fullname'] ?? null,
                    'delivery_company' => $orderData['delivery_company'] ?? null,
                    'delivery_address' => $orderData['delivery_address'] ?? null,
                    'delivery_city' => $orderData['delivery_city'] ?? null,
                    'delivery_state' => $orderData['delivery_state'] ?? null,
                    'delivery_postcode' => $orderData['delivery_postcode'] ?? null,
                    'delivery_country_code' => $orderData['delivery_country_code'] ?? null,
                    'delivery_point_id' => $orderData['delivery_point_id'] ?? null,
                    'delivery_point_name' => $orderData['delivery_point_name'] ?? null,
                    'delivery_point_address' => $orderData['delivery_point_address'] ?? null,
                    'delivery_point_postcode' => $orderData['delivery_point_postcode'] ?? null,
                    'delivery_point_city' => $orderData['delivery_point_city'] ?? null,
                    'invoice_fullname' => $orderData['invoice_fullname'] ?? null,
                    'invoice_company' => $orderData['invoice_company'] ?? null,
                    'invoice_nip' => $orderData['invoice_nip'] ?? null,
                    'invoice_address' => $orderData['invoice_address'] ?? null,
                    'invoice_city' => $orderData['invoice_city'] ?? null,
                    'invoice_state' => $orderData['invoice_state'] ?? null,
                    'invoice_postcode' => $orderData['invoice_postcode'] ?? null,
                    'invoice_country_code' => $orderData['invoice_country_code'] ?? null,
                    'delivery_country' => $orderData['delivery_country'] ?? null,
                    'invoice_country' => $orderData['invoice_country'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('OrderDetails updateOrInsert failed', [
                'order_id' => $IDOrder,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function writeProductsOrder($orderData, $IDOrder, $idMagazynu, $uwagi)
    {
        foreach ($orderData['products'] as $product) {

            try {
                DB::statement(
                    "EXEC CreateOrderLine
               @IDOrder = ?,
               @ProductName = ?,
               @TargetColumnName = ?,
               @TargetColumnValue = ?,
               @PriceBruttoService = ?,
               @Quantity = ?,
               @IdMagazynu = ?,
               @BoughtDate = ?,
               @Remarks = ?,
               @TaxRate = ?
               ",
                    [
                        $IDOrder, // IDOrder
                        $product['name'], // ProductName
                        'KodKreskowy',
                        $product['ean'], // TargetColumnValue
                        $product['price_brutto'], // PriceBrutto
                        $product['quantity'], // Quantity
                        $idMagazynu, // StorageId
                        Carbon::parse($orderData['date_confirmed'])->format('Y-m-d H:i:s'), // BoughtDate
                        $uwagi, // Remarks
                        $product['tax_rate'], // TaxRate
                    ]
                );
            } catch (\Exception $e) {
                throw new \Exception("Error executing CreateOrderLine for product: {$product['name']}. Details: " . $e->getMessage());
            }
            // If the product is not recognized, we change the order status to not ship in both systems and comment in BC

            // Check the availability of goods in the database
            $productExists = DB::table('Towar')->where('KodKreskowy', $product['ean'])->where('KodKreskowy', '!=', '')->where('IDMagazynu', $idMagazynu)->exists();
            if (!$productExists) {
                // If the product does not exist, add it to the database
                try {
                    if (env('APP_ENV') != 'local') {
                        $parameters = [
                            'order_id' => $orderData['order_id'],
                            'status_id' => $this->BL->status_id_Nie_wysylac,
                        ];
                        $this->BL->setOrderStatus($parameters);
                        $parameters = [
                            'order_id' => $orderData['order_id'],
                            'admin_comments' => 'Тowar ' . $product['ean'] . ' nie istnieje w bazie danych. Proszę o dodanie towaru do bazy danych.',
                        ];
                        $this->BL->setOrderFields($parameters);
                    }
                    $this->executeWithRetry(function () use ($IDOrder, $product) {
                        DB::table('Orders')->where('IDOrder',  $IDOrder)->update([
                            'IDOrderStatus' => 29, //Nie wysyłać
                            'Remarks' => 'Тowar ' . $product['ean'] . ' nie istnieje w bazie danych. Proszę o dodanie towaru do bazy danych.',
                        ]);
                    });
                } catch (\Exception $e) {
                    throw new \Exception("Error inserting product data: " . $e->getMessage());
                }
            }
        }
        if ($orderData['delivery_price'] > 0) {
            $id_delivery = DB::table('Towar')->where('Nazwa', 'Koszty transportu')->where('IDMagazynu', $idMagazynu)->value('IDTowaru');
            try {
                if (!$id_delivery) {
                    DB::table('Towar')->insert([
                        'Nazwa' => 'Koszty transportu',
                        'IDMagazynu' => $idMagazynu,
                        'Usluga' => 1,
                        'IDJednostkiMiary' => 1,
                        'IDGrupyTowarow' => DB::table('GrupyTowarow')->where('Nazwa', 'Wszystkie')->where('IDMagazynu', $idMagazynu)->value('IDGrupyTowarow'),

                    ]);
                    $id_delivery = DB::table('Towar')->where('Nazwa', 'Koszty transportu')->where('IDMagazynu', $idMagazynu)->value('IDTowaru');
                }
                DB::table('OrderLines')->insert([
                    'IDOrder' => $IDOrder,
                    'IDItem' => $id_delivery,
                    'PriceGross' => $orderData['delivery_price'],
                    'Quantity' => 1,
                    'Remarks' => $uwagi
                ]);
            } catch (\Exception $e) {
                throw new \Exception("Error inserting delivery data: " . $e->getMessage());
            }
        }
    }

    public function findOrCreateKontrahent(array $orderData, $idMagazynu)
    {
        // Extracting counterparty data from an order
        $contractorData = [
            'Nazwa' => $orderData['delivery_fullname'],
            'Email' => $orderData['email'],
            'Telefon' => $orderData['phone'],
            'KodPocztowy' => $orderData['delivery_postcode'],
            'Miejscowosc' => $orderData['delivery_city'],
            'UlicaLokal' => $orderData['delivery_address'],
            'IDKraju' => DB::table('Kraj')->where(
                'Kod',
                $orderData['delivery_country_code']
            )->value('IDKraju'),
            'NIP' => $orderData['invoice_nip'],
        ];

        // Checking the availability of the counterparty in the database
        $existingContractor = DB::table('Kontrahent')
            ->where('Nazwa', $contractorData['Nazwa'])
            ->where('Email', $contractorData['Email'])
            ->where('Telefon', $contractorData['Telefon'])
            ->where('KodPocztowy', $contractorData['KodPocztowy'])
            ->where('Miejscowosc', $contractorData['Miejscowosc'])
            ->where('UlicaLokal', $contractorData['UlicaLokal'])

            ->first();

        if ($existingContractor) {
            // If the counterparty is found, return its ID
            return $existingContractor->IDKontrahenta;
        }

        // If the counterparty is not found, add it to the database

        try {
            DB::table('Kontrahent')->insert([
                'Nazwa' => $contractorData['Nazwa'],
                'OsobaKontaktowa' => $contractorData['Nazwa'],
                'OsobaKontaktowaDostawy' => $contractorData['Nazwa'],
                'NazwaAdresuDostawy' => $contractorData['Nazwa'],
                'Email' => $contractorData['Email'],
                'Telefon' => $contractorData['Telefon'],
                'TelefonDostawy' => $contractorData['Telefon'],
                'KodPocztowy' => $contractorData['KodPocztowy'],
                'KodPocztowyDostawy' => $contractorData['KodPocztowy'],
                'Miejscowosc' => $contractorData['Miejscowosc'],
                'SupplyCity' => $contractorData['Miejscowosc'],
                'UlicaLokal' => $contractorData['UlicaLokal'],
                'UlicaDostawy' => $contractorData['UlicaLokal'],
                'IDKraju' => $contractorData['IDKraju'],
                'Utworzono' => now(),
                'Zmodyfikowano' => now(),
                'Odbiorca' => 1,
                'Dostawca' => 0,
                'IDWarehouse' => $idMagazynu,
            ]);

            // Return the ID of a new counterparty
            $existingContractor = DB::table('Kontrahent')
                ->where('Nazwa', $contractorData['Nazwa'])
                ->where('Email', $contractorData['Email'])
                ->where('Telefon', $contractorData['Telefon'])
                ->where('IDWarehouse', $idMagazynu)
                ->first();
            return $existingContractor->IDKontrahenta;
        } catch (\Exception $e) {
            throw new \Exception("Error inserting Kontrahent data: " . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Execute a database operation with retry logic for deadlock handling
     *
     * @param callable $operation
     * @param int $maxRetries
     * @return mixed
     * @throws \Exception
     */
    private function executeWithRetry(callable $operation, $maxRetries = null)
    {
        $maxRetries = $maxRetries ?? self::MAX_DEADLOCK_RETRIES;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $attempt++;

                // Check if it's a deadlock error
                if (strpos($e->getMessage(), 'deadlock') !== false || strpos($e->getMessage(), 'Deadlock') !== false) {
                    if ($attempt >= $maxRetries) {
                        Log::error('Maximum retry attempts reached for deadlock', [
                            'attempt' => $attempt,
                            'error' => $e->getMessage()
                        ]);
                        throw $e;
                    }

                    // Exponential backoff using the configured base delay
                    $waitTime = self::DEADLOCK_RETRY_BASE_DELAY_MS * pow(2, $attempt - 1);
                    Log::warning('Deadlock detected, retrying', [
                        'attempt' => $attempt,
                        'wait_time_ms' => $waitTime
                    ]);

                    usleep($waitTime * 1000); // Convert to microseconds
                    continue;
                }

                // If it's not a deadlock, throw the exception immediately
                throw $e;
            }
        }

        throw new \Exception('Maximum retry attempts reached');
    }

    private function getWarehouseSymbol($idMagazynu)
    {
        $Symbol = DB::table('Magazyn')->where('IDMagazynu', $idMagazynu)->value('Symbol');
        return $Symbol;
    }
}
