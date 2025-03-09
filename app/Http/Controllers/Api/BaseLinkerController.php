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
        \Log::info('getOrderStatusList response:', $response['statuses']);
        return $response['statuses'];
    }
    public function getOrderExtraFields()
    {
        $response = $this->sendRequest('getOrderExtraFields');
        \Log::info('getOrderExtraFields response:', $response['extra_fields']);
        return $response['extra_fields'];
    }

    public function getOrders($parameters)
    {
        $response = $this->sendRequest('getOrders', $parameters);
        //        \Log::info('getOrders response:', $response);
        return $response;
    }

    public function setOrderStatus($parameters)
    {
        $response = $this->sendRequest('setOrderStatus', $parameters);
        return $response;
    }

    public function setOrderFields($parameters)
    {
        $response = $this->sendRequest('setOrderFields', $parameters);
        \Log::info('getOrderExtraFields response:', $response);
        return $response;
    }
}
