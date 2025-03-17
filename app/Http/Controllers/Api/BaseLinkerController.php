<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class BaseLinkerController extends Controller
{
    private $token;

    public $statuses = [];
    public $ExtraFields = [];
    public $status_id_Kompletowanie = '';
    public $status_id_Nie_wysylac = '';
    public $id_exfield_stan = '';

    public function __construct($token)
    {
        $this->token = $token;
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
        \Log::info('getOrderStatusList response:', $response['statuses']);
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
        // \Log::info('getOrderExtraFields response:', $response['extra_fields']);
        return $response['extra_fields'];
    }

    public function getOrders($parameters)
    {
        $response = $this->sendRequest('getOrders', $parameters);
        //\Log::info('getOrders response:', $response);
        return $response;
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
        \Log::info('setOrderStatus:', ['status' => $response]);
        return $response;
    }

    public function setOrderFields($parameters)
    {
        $response = $this->sendRequest('setOrderFields', $parameters);
        // \Log::info('setOrderFields response:', $response);
        return $response;
    }
}
