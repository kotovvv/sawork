<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;


class BaseLinkerController extends Controller
{
    private $token;

    public $statuses = [];
    public $ExtraFields = [];
    public $status_id_Kompletowanie = '';
    public $status_id_Nie_wysylac = '';
    public $id_exfield_stan = '';
    public $log_type = [
        1 => "Order creation",
        2 => "DOF download (order confirmation)",
        3 => "Payment of the order",
        4 => "Removal of order/invoice/receipt",
        5 => "Merging the orders",
        6 => "Splitting the order",
        7 => "Issuing an invoice",
        8 => "Issuing a receipt",
        9 => "Package creation",
        10 => "Deleting a package",
        11 => "Editing delivery data",
        12 => "Adding a product to an order",
        13 => "Editing the product in the order",
        14 => "Removing the product from the order",
        15 => "Adding a buyer to a blacklist",
        16 => "Editing order data",
        17 => "Copying an order",
        18 => "Order status change",
        19 => "Invoice deletion",
        20 => "Receipt deletion",
        21 => "Editing invoice data"
    ];
    public  $object_id = [
        5 => "ID of the merged order",
        6 => "ID of the new order created by the order separation",
        7 => "Invoice ID",
        9 => "Created parcel ID",
        10 => "Deleted parcel ID",
        14 => "Deleted product ID",
        17 => "Created order ID",
        18 => "Order status ID",
    ];

    public function __construct($token)
    {
        $this->token = Crypt::decryptString($token);;
        $this->statuses = $this->getOrderStatusList();
        $this->ExtraFields = $this->getOrderExtraFields();
    }

    public function sendRequest($method, $parameters = [])
    {
        $response = Http::withHeaders([
            'X-BLToken' =>  $this->token,
        ])->asForm()->post('https://api.baselinker.com/connector.php', [
            'method' => $method,
            'parameters' => json_encode($parameters),
        ]);
        return $response->json();
    }

    public function getOrderStatusList()
    {
        $response = $this->sendRequest('getOrderStatusList');
        foreach ($response['statuses'] as $statusItem) {
            // if (strpos(strtolower($statusItem['name']), 'komp') === 0) {
            if ($statusItem['name'] == 'Kompletowanie') {
                $this->status_id_Kompletowanie = $statusItem['id'];
            }
            if ($statusItem['name'] == 'Nie wysyłać') {
                $this->status_id_Nie_wysylac = $statusItem['id'];
            }
        }
        //\Log::info('getOrderStatusList response:', $response['statuses']);
        return $response['statuses'];
    }
    public function getOrderExtraFields()
    {
        $response = $this->sendRequest('getOrderExtraFields');
        foreach ($response['extra_fields'] as $exfield) {
            if (strpos(strtolower($exfield['name']), 'stan') === 0) {
                $this->id_exfield_stan = $exfield['extra_field_id'];
                break;
            }
        }

        return $response['extra_fields'];
    }

    public function getOrders($parameters)
    {
        $response = $this->sendRequest('getOrders', $parameters);

        return $response;
    }


    public function getStatusId($name_status)
    {
        $status_id = '';
        foreach ($this->statuses as $statusItem) {
            if ($statusItem['name'] == $name_status) {
                $status_id = $statusItem['id'];
                break;
            }
        }
        return $status_id;
    }

    public function getStatusName($status_id)
    {
        $status_name = '';
        foreach ($this->statuses as $statusItem) {
            if ($statusItem['id'] == $status_id) {
                $status_name = $statusItem['name'];
                break;
            }
        }
        return $status_name;
    }

    public function inRealizacji($parameters)
    {
        $a_orders = $this->sendRequest('getOrders', $parameters);
        $o_status_id = $a_orders['orders'][0];
        $order_status_id = $o_status_id['order_status_id'];
        // \Log::info('order_status_id:', ['order_status_id' => $order_status_id]);
        $status = '';
        foreach ($this->statuses as $statusItem) {
            if ($statusItem['id'] == $order_status_id) {
                $status = $statusItem['name'];
                break;
            }
        }
        //\Log::info('getOrders status:', ['status' => $status == 'W realizacji' ? 'true' : 'false']);
        return $status == 'W realizacji' ? true : false; //W realizacji
    }

    public function setOrderStatus($parameters)
    {
        $response = $this->sendRequest('setOrderStatus', $parameters);
        //\Log::info('setOrderStatus:', ['status' => $response]);
        return $response;
    }

    public function setOrderFields($parameters)
    {
        $response = $this->sendRequest('setOrderFields', $parameters);
        // \Log::info('setOrderFields response:', $response);
        return $response;
    }

    public function getInvoices($parameters)
    {
        $response = $this->sendRequest('getInvoices', $parameters);
        return $response;
    }

    public function getOrderSources()
    {
        $response = $this->sendRequest('getOrderSources');
        return $response;
    }

    public function getJournalList($parameters)
    {
        $response = $this->sendRequest('getJournalList', $parameters);
        return $response;
    }
}
