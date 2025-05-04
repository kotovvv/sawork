<?php

namespace Tests\Feature;


use Tests\TestCase;
use App\Http\Controllers\Api\importBLController;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BaseLinkerControllerTest extends TestCase
{

    public function testGetAllWarehouses()
    {
        $controller = new importBLController();
        $warehouses = $controller->getAllWarehouses();
        $this->assertNotEmpty($warehouses);
    }
    /*
    private $token = '5006735-5023428-IRU08C6YBD28XVJ33KY0NKOFMHFG3Y4M0QDL0K1T836KM8NLZKKKTS50KUNU2YV4';
    private $statuses = [];
    private $invoices = [];
    private $orderSources = [];

    public function testGetOrders()
    {
        $BL = new BaseLinkerController($this->token);
        $this->statuses = $BL->statuses;
        $w_realizacji_id = $BL->getStatusId('W realizacji');
        $this->orderSources = $BL->getOrderSources();

        $parameters = [
            // 'order_id' => 12017786,
            // 'get_unconfirmed_orders' => true,
            // 'include_custom_extra_fields' => true,
            'status_id' => $w_realizacji_id,
        ];

        $response = $BL->getOrders($parameters);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('orders', $response);

        foreach ($response['orders'] as $order) {
            $this->invoices = $BL->getInvoices(['order_id' => $order['order_id']]);
            if (!isset($this->invoices['status']) || $this->invoices['status'] != "SUCCESS") continue;
            // уже был этот заказ импортирован интегратором
            $wasImported = DB::table('IntegratorTransactions')->where('transId', $order['order_id'])->first();
            if ($wasImported) {
                continue; // Skip if the transaction already exists
            }
            $this->importOrder($order);
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

    public function importOrder(array $orderData)
    {
        if ($orderData['delivery_fullname'] == null) {
            return;
        }

        $idMagazynu = 21; // Пример ID склада, замените на актуальный
        try {
            // Начало транзакции
            DB::beginTransaction();

            $CustomerId = $this->findOrCreateKontrahent($orderData);
            // Подготовка данных для процедуры
            $uwagi = 'Nr zamówienia w BaseLinker: ' . $orderData['order_id'] . ' ' . $orderData['user_comments'] ?: $orderData['admin_comments'] ?: '';
            $orderDate = Carbon::parse($orderData['date_add'])->format('Y-m-d H:i:s');
            $orderStatus = collect($this->statuses)->firstWhere('id', $orderData['order_status_id'])['name'] ?? null;
            $paymentType = DB::table('PaymentTypes')->where('Name', $orderData['payment_method'])->value('IDPaymentType');
            $idTransport = DB::table('RodzajTransportu')->where('Nazwa', $orderData['delivery_method'])->value('IDRodzajuTransportu');
            $invoice_number = collect($this->invoices['invoices'])->firstWhere('order_id', $orderData['order_id'])['number'];
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
                throw new \Exception("Error executing CreateOrder: {$orderData['order_id']}. Details: " . $e->getMessage());
            }

            $IDOrder = DB::table('Orders')->where('Number', $Number)->value('IDOrder');
            // DB::enableQueryLog();
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
            echo 'Order imported successfully: ' . $orderData['order_id'] . PHP_EOL;
            // return $orderId;
        } catch (\Exception $e) {
            // Откат транзакции в случае ошибки
            DB::rollBack();
            throw $e;
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

    // public function testSetOrderStatus()
    // {
    //     $controller = new BaseLinkerController($this->token);
    //     $parameters = [
    //         'order_id' => 10340564,
    //         'status_id' => 143145,
    //     ];
    //     $response = $controller->setOrderStatus($parameters);

    //     $this->assertIsArray($response);
    //     $this->assertArrayHasKey('status', $response);
    //     $this->assertEquals('SUCCESS', $response['status']);
    // }
    */
}
