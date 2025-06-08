<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseLinkerController;
use App\Models\LogOrder; // Ensure this model exists in the App\Models namespace
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class importBLController extends Controller
{
    private $warehouses;
    private $statuses = [];
    private $invoices = [];
    private $orderSources = [];
    private $BL;

    /* Get all mwarehouse for loop */
    public function __construct()
    {
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
            // 'order_id' => 12017786,
            'logs_types' => [12, 13, 14, 18]
        ];

        if ($last_log_id > 0) {
            $parameters['last_log_id'] = $last_log_id;
        }

        $response = $this->BL->getJournalList($parameters);

        if (!isset($response['status']) || $response['status'] != "SUCCESS") {
            return;
        }

        $logsToProcess = array_slice($response['logs'], 0, 80); // Process only the first 80 logs
        foreach ($logsToProcess as $log) {

            $last_log_id = max($last_log_id, $log['log_id']);
            if ($a_warehouse->last_log_id == $log['log_id']) continue;
            switch ($log['log_type']) {
                case '18':
                    $this->changeProductsOrder([
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
        DB::table('settings')
            ->updateOrInsert(
                ['obj_name' => 'last_log_id', 'for_obj' => $a_warehouse->warehouse_id, 'key' => $a_warehouse->warehouse_id],
                ['value' => $last_log_id]
            );
        /* { "status": "SUCCESS", "logs": [ { "log_id": 82280791, "log_type": 12, "order_id": 12017786, "object_id": 0, "date": 1745384688 }, { "log_id": 82280827, "log_type": 13, "order_id": 12017786, "object_id": 0, "date": 1745384739 } ] } */
    }

    private function writeLog($param)
    {
        if (DB::table('Orders')->where('_OrdersTempDecimal2', $param['a_log']['order_id'])->exists()) {
            $log_type_name = $this->BL->log_type[$param['a_log']['log_type']];
            $object_id = 0;
            if ($param['a_log']['object_id'] > 0 && $param['a_log']['object_id'] < 19) {
                $object_id = $this->BL->object_id[$param['a_log']['object_id']];
            }
            $date = Carbon::createFromTimestamp($param['a_log']['date'])->format('Y-m-d H:i:s');
            if (DB::table('Orders')->where('_OrdersTempDecimal2', $param['a_log']['order_id'])->where('IDOrderStatus', 23)->exists()) {
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

    private function changeProductsOrder($param)
    {

        $w_realizacji_id = $this->BL->getStatusId('W realizacji');

        $parameters = [
            'order_id' => $param['a_log']['order_id'],
        ];

        $response = $this->BL->getOrders($parameters);
        if (!isset($response['status']) || $response['status'] != "SUCCESS") {
            return;
        }
        foreach ($response['orders'] as $order) {
            if ($order['order_status_id'] == $w_realizacji_id) {

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
                $a_order = DB::table('Orders')->where('_OrdersTempDecimal2', $order['order_id'])->first();
                if ($a_order) {
                    if (!in_array($a_order->IDOrderStatus, [16, 19, 23, 27, 29, 32, 33, 43])) {
                        LogOrder::create([
                            'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                            'number' => $order['order_id'],
                            'type' => $param['a_log']['log_type'],
                            'message' => "Atention!!! Change status Order: {$order['order_id']}. Details: " . $this->BL->getStatusName($order['order_status_id'])
                        ]);
                        return;
                    }
                    if (in_array($a_order->IDOrderStatus, [16, 19, 27, 29, 32, 33, 43])) {
                        DB::table('Orders')
                            ->where('_OrdersTempDecimal2', $order['order_id'])
                            ->update(['IDOrderStatus' => 23]);
                    }
                    $uwagi = 'Nr zamówienia w BaseLinker: ' . $order['order_id'] . ' Zmiana zamówienia w BaseLinker ' . $order['user_comments'] ?: $order['admin_comments'] ?: '';

                    try {
                        DB::beginTransaction();
                        DB::table('OrderLines')->where('IDOrder', $a_order->IDOrder)->delete();
                        $this->writeProductsOrder($order, $a_order->IDOrder, $param['a_warehouse']->warehouse_id, $uwagi);
                        DB::table('Orders')->where('IDOrder', $a_order->IDOrder)->update([
                            'Modified' => now(),
                        ]);

                        DB::commit();
                    } catch (\Throwable $th) {
                        DB::rollBack();
                        throw $th;
                    }
                }
            } else {
                LogOrder::create([
                    'IDWarehouse' => $param['a_warehouse']->warehouse_id,
                    'number' => $order['order_id'],
                    'type' => 911,
                    'message' => "Change status Order: {$order['order_id']}. Details: " . $this->BL->getStatusName($order['order_status_id'])
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

    public function GetOrders($idMagazynu)
    {
        $this->statuses = $this->BL->statuses;
        $w_realizacji_id = $this->BL->getStatusId('W realizacji');
        $this->orderSources = $this->BL->getOrderSources();

        $parameters = [
            // 'order_id' => 12017786,
            // 'get_unconfirmed_orders' => true,
            // 'include_custom_extra_fields' => true,
            'status_id' => $w_realizacji_id,
        ];

        $response = $this->BL->getOrders($parameters);

        //$ordersToProcess = array_slice($response['orders'], 0, 80); // Process only the first 80 orders
        $i = 80;
        if (!is_array($response) || !isset($response['orders']) || !is_array($response['orders'])) {
            \Log::error("Error in GetOrders: " . $e->getMessage(), ['exception' => $e]);
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
                \Log::error("Invalid order data encountered.", ['order' => $order]);
                throw new \Exception("Invalid order data. Expected an array with 'order_id'.");
            }
            if ($wasImported) {
                continue;
            }

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
        \Log::info('Importing order', ['order_id' => $orderData['order_id']]);
        try {
            DB::beginTransaction();

            $CustomerId = $this->findOrCreateKontrahent($orderData);

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
            $this->saveOrderDetails($orderData, $IDOrder);
            // DB::enableQueryLog();
            $this->writeProductsOrder($orderData, $IDOrder, $idMagazynu, $uwagi);


            DB::table('Orders')->where('Number', $Number)->update([
                '_OrdersTempString1' => $invoice_number,
                '_OrdersTempDecimal2' => $orderData['order_id'],
                '_OrdersTempString7' => $orderSources,
                '_OrdersTempString8' => $orderData['external_order_id'],
                '_OrdersTempString9' => $orderData['user_login']
            ]);

            DB::commit();
            DB::table('IntegratorTransactions')->insert([
                'transId' => $orderData['order_id'],
                'IDWarehouse' => $idMagazynu,
                'typ' => 3,
            ]);
            LogOrder::create([
                'IDWarehouse' => $idMagazynu,
                'number' => $orderData['order_id'],
                'type' => 3,
                'message' => "CreateOrder: {$orderData['order_id']}."
            ]);
        } catch (\Exception $e) {

            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Save order details to the database.
     *
     * @param array $orderData
     * @return void
     */
    public function saveOrderDetails(array $orderData, $IDOrder)
    {
        try {
            DB::connection('second_mysql')->table('order_details')->updateOrInsert(
                ['order_id' => $IDOrder],
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
            \Log::error('OrderDetails updateOrInsert failed', [
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
                    DB::table('Orders')->where('IDOrder',  $IDOrder)->update([
                        'IDOrderStatus' => 29, //Nie wysyłać
                        'Remarks' => 'Тowar ' . $product['ean'] . ' nie istnieje w bazie danych. Proszę o dodanie towaru do bazy danych.',
                    ]);
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

    public function findOrCreateKontrahent(array $orderData)
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
            ]);

            // Return the ID of a new counterparty
            $existingContractor = DB::table('Kontrahent')
                ->where('Nazwa', $contractorData['Nazwa'])
                ->where('Email', $contractorData['Email'])
                ->where('Telefon', $contractorData['Telefon'])
                ->first();
            return $existingContractor->IDKontrahenta;
        } catch (\Exception $e) {
            throw new \Exception("Error inserting Kontrahent data: " . $e->getMessage());
            throw $e;
        }
    }
}
