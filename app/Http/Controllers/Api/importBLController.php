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
        $this->statuses = $this->BL->statuses;
        $w_realizacji_id = $this->BL->getStatusId('W realizacji');
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

        foreach ($response['logs'] as $log) {
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
            if ($param['a_log']['object_id'] > 0) {
                $object_id = $this->BL->object_id[$param['a_log']['object_id']];
            }
            $date = Carbon::createFromTimestamp($param['a_log']['date'])->format('Y-m-d H:i:s');
            if (DB::table('Orders')->where('_OrdersTempDecimal2', $param['a_log']['order_id'])->where('IDOrderStatus', 23)->exists()) {
                LogOrder::create([
                    'number' => $param['a_log']['order_id'],
                    'type' => $param['a_log']['log_type'],
                    'message' => "Log type: {$log_type_name},  Date: {$date}, Log ID: {$param['a_log']['log_id']}, Object ID: {$object_id}"
                ]);
                $this->changeProductsOrder($param);
            } else {
                LogOrder::create([
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
                // уже был этот заказ импортирован интегратором
                $a_order = DB::table('Orders')->where('_OrdersTempDecimal2', $order['order_id'])->first();
                if ($a_order) {
                    $uwagi = 'Nr zamówienia w BaseLinker: ' . $order['order_id'] . ' Zmiana zamówienia w BaseLinker ' . $order['user_comments'] ?: $order['admin_comments'] ?: '';
                    try {
                        DB::beginTransaction();
                        DB::table('OrderLines')->where('IDOrder', $a_order->IDOrder)->delete();
                        $this->writeProductsOrder($order, $a_order->IDOrder, $param['a_warehouse']->warehouse_id, $uwagi);
                        DB::table('Orders')->where('IDOrder', $a_order->IDOrder)->update([
                            'Modified' => now(),
                        ]);
                        LogOrder::create([
                            'number' => $param['a_log']['order_id'],
                            'type' => 18,
                            'message' => "Log type: {$this->BL->log_type[$param['a_log']['log_type']]}, "
                                . "Date: " . Carbon::createFromTimestamp($param['a_log']['date'])->format('Y-m-d H:i:s') . ", "
                                . "Log ID: {$param['a_log']['log_id']}, "
                                . "Object ID: {$param['a_log']['object_id']}"

                        ]);
                        DB::commit();
                    } catch (\Throwable $th) {
                        DB::rollBack();
                        throw $th;
                    }
                }
            } else {
                LogOrder::create([
                    'number' => $order['order_id'],
                    'type' => 911,
                    'message' => "Change status Order: {$order['order_id']}. Details: " . $this->BL->getStatusName($order['order_status_id'])
                ]);
            }
        }
    }

    private function setOrderFields($parameters)
    {
        $this->BL = new BaseLinkerController($parameters['token']);
        $this->statuses = $this->BL->statuses;
        $w_realizacji_id = $this->BL->getStatusId('W realizacji');
        $this->orderSources = $this->BL->getOrderSources();

        $response = $this->BL->setOrderFields($parameters);
    }
    private function shouldExecute($a_warehouse)
    {
        if ($a_warehouse->interval_minutes == 0) {
            return false; // Если интервал не задан или равен 0, не выполнять
        }

        if ($a_warehouse->last_executed_at == 0 || $a_warehouse->last_executed_at == '') {
            return true; // Если задача никогда не выполнялась, запускать
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

        // $cacheKey = 'mwarehouses';
        // return cache()->remember($cacheKey, 3600, function () {
        //     return DB::table('settings')
        //         ->where('obj_name', 'sklad_token')

        //         ->whereNotNull('value')
        //         ->where('value', '!=', '')
        //         ->get();
        // });
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

        foreach ($response['orders'] as $order) {
            // уже был этот заказ импортирован интегратором
            $wasImported = DB::table('IntegratorTransactions')->where('transId', $order['order_id'])->first();
            if ($wasImported) {
                continue; // Skip if the transaction already exists
            }
            $this->invoices = $this->BL->getInvoices(['order_id' => $order['order_id']]);
            if (!isset($this->invoices['status']) || $this->invoices['status'] != "SUCCESS") continue;
            $this->importOrder($order, $idMagazynu);
        }
    }

    public function lastNumber($doc, $IDWarehouse)
    {
        $symbol = DB::table('Magazyn')->where('IDMagazynu', $IDWarehouse)->value('Symbol');

        $year = Carbon::now()->format('y');
        $pattern =  $doc . '%/' . $year . ' - ' . $symbol;
        $patternIndex = strlen($doc);
        $patternToEndLen = strlen($symbol) + 6; // 6 символов: " - " + год (2 символа) + "/"

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

            $CustomerId = $this->findOrCreateKontrahent($orderData);
            // Подготовка данных для процедуры
            $uwagi = 'Nr zamówienia w BaseLinker: ' . $orderData['order_id'] . ' ' . $orderData['user_comments'] ?: $orderData['admin_comments'] ?: '';
            $orderDate = Carbon::parse($orderData['date_add'])->format('Y-m-d H:i:s');
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
            // DB::enableQueryLog();
            $this->writeProductsOrder($orderData, $IDOrder, $idMagazynu, $uwagi);

            // dd(DB::getQueryLog());
            DB::table('Orders')->where('Number', $Number)->update([
                '_OrdersTempString1' => $invoice_number,
                '_OrdersTempDecimal2' => $orderData['order_id'],
                '_OrdersTempString7' => $orderSources,
                '_OrdersTempString8' => $orderData['external_order_id'],
                '_OrdersTempString9' => $orderData['user_login']
            ]);

            // Фиксация транзакции
            DB::commit();
            DB::table('IntegratorTransactions')->insert([
                'transId' => $orderData['order_id'],
                'typ' => 3,
            ]);
            LogOrder::create([
                'number' => $orderData['order_id'],
                'type' => 3,
                'message' => "CreateOrder: {$orderData['order_id']}."
            ]);
            //echo 'Order imported successfully: ' . $orderData['order_id'] . PHP_EOL;
            // return $orderId;
        } catch (\Exception $e) {
            // Откат транзакции в случае ошибки
            DB::rollBack();
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
        }
    }

    public function findOrCreateKontrahent(array $orderData)
    {
        // Извлечение данных контрагента из заказа
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

        // Проверка наличия контрагента в базе
        $existingContractor = DB::table('Kontrahent')
            ->where('Nazwa', $contractorData['Nazwa'])
            ->where('Email', $contractorData['Email'])
            ->where('Telefon', $contractorData['Telefon'])
            ->where('KodPocztowy', $contractorData['KodPocztowy'])
            ->where('Miejscowosc', $contractorData['Miejscowosc'])
            ->where('UlicaLokal', $contractorData['UlicaLokal'])
            ->first();

        if ($existingContractor) {
            // Если контрагент найден, возвращаем его ID
            return $existingContractor->IDKontrahenta;
        }

        // Если контрагент не найден, добавляем его в базу

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
            //print_r($orderData);

            // Возвращаем ID нового контрагента
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
/*
Добавление заказа:

Процедура fulstor.CreateOrder отвечает за создание нового заказа. Она может быть использована для добавления основного объекта заказа в таблицу Orders.
Добавление товаров в заказ:

Процедура fulstor.CreateOrderLine предназначена для добавления строк товаров в заказ. Она может быть использована для добавления каждого товара из объекта заказа в таблицу OrderLines.
Добавление контрагента:

Процедура fulstor.DodajTowar или fulstor.DodajTowar2 может быть использована для добавления информации о товарах в таблицу Towar.
Для добавления контрагента, возможно, потребуется создать отдельную процедуру, если она отсутствует в дампе. Однако, таблица Kontrahent упоминается в других частях дампа, что может указывать на существующие механизмы работы с контрагентами.
Копирование связанных данных:

Процедура fulstor.CopyDedicatedFields может быть полезна для копирования связанных данных между таблицами, например, при создании связей между заказом и товарами.
Пример последовательности действий:
Используйте fulstor.CreateOrder для создания записи заказа.
Для каждого товара в заказе используйте fulstor.CreateOrderLine для добавления строки товара.
Если контрагент отсутствует в базе, добавьте его вручную или создайте процедуру для работы с таблицей Kontrahent.
*/
