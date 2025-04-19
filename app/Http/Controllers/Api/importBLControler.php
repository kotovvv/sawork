<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class importBLControler extends Controller
{
    public function getOrdersBL()
    {
        $IDMagazynu = 21; // blconnect
        $token = $this->getToken($IDMagazynu);
        $token = Crypt::decryptString($token);
        if ($token) {
            $BL = new BaseLinkerController($token);
        } else {
            return;
        }
        $params = [
            'date_confirmed_from' => '2023-10-01 00:00:00',
            'date_from' => '2023-10-01 00:00:00',
            'id_from' => 0,
            'get_unconfirmed_orders' => 0,
            'include_custom_extra_fields' => 1,
        ];
        $params = [];
        $orders = $BL->getOrders($params);
        return response()->json($orders);
    }

    public function importOrder(array $orderData)
    {
        try {
            // Начало транзакции
            DB::beginTransaction();

            // Вызов процедуры для создания заказа
            $orderId = DB::select(
                'EXEC fulstor.CreateOrder
                @OrderID = ?,
                @ShopOrderID = ?,
                @ExternalOrderID = ?,
                @OrderSource = ?,
                @OrderStatusID = ?,
                @Confirmed = ?,
                @DateConfirmed = ?,
                @DateAdd = ?,
                @UserLogin = ?,
                @Phone = ?,
                @Email = ?,
                @Currency = ?,
                @PaymentMethod = ?,
                @PaymentDone = ?,
                @DeliveryMethod = ?,
                @DeliveryPrice = ?,
                @DeliveryFullname = ?,
                @DeliveryAddress = ?,
                @DeliveryCity = ?,
                @DeliveryPostcode = ?,
                @DeliveryCountry = ?,
                @InvoiceFullname = ?,
                @InvoiceAddress = ?,
                @InvoiceCity = ?,
                @InvoicePostcode = ?,
                @InvoiceCountry = ?',
                [
                    $orderData['order_id'],
                    $orderData['shop_order_id'],
                    $orderData['external_order_id'],
                    $orderData['order_source'],
                    $orderData['order_status_id'],
                    $orderData['confirmed'],
                    date('Y-m-d H:i:s', $orderData['date_confirmed']),
                    date('Y-m-d H:i:s', $orderData['date_add']),
                    $orderData['user_login'],
                    $orderData['phone'],
                    $orderData['email'],
                    $orderData['currency'],
                    $orderData['payment_method'],
                    $orderData['payment_done'],
                    $orderData['delivery_method'],
                    $orderData['delivery_price'],
                    $orderData['delivery_fullname'],
                    $orderData['delivery_address'],
                    $orderData['delivery_city'],
                    $orderData['delivery_postcode'],
                    $orderData['delivery_country'],
                    $orderData['invoice_fullname'],
                    $orderData['invoice_address'],
                    $orderData['invoice_city'],
                    $orderData['invoice_postcode'],
                    $orderData['invoice_country']
                ]
            );

            // Фиксация транзакции
            DB::commit();

            return $orderId;
        } catch (\Exception $e) {
            // Откат транзакции в случае ошибки
            DB::rollBack();
            throw $e;
        }
    }

    private function getToken($IDMagazynu)
    {
        return DB::table('settings')->where('obj_name', 'sklad_token')->where('key', $IDMagazynu)->value('value');
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
