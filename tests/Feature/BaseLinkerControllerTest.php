<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Http\Controllers\Api\BaseLinkerController;

class BaseLinkerControllerTest extends TestCase
{
    public function testgetOrderExtraFields()
    {
        $controller = new BaseLinkerController();
        $response = $controller->getOrderExtraFields();

        $this->assertIsArray($response);
        // $this->assertArrayHasKey('extra_fields', $response);
        // $this->assertEquals('SUCCESS', $response['status']);
    }
    public function testGetOrderStatusList()
    {
        $controller = new BaseLinkerController();
        $response = $controller->getOrderStatusList();

        $this->assertIsArray($response);
        // $this->assertArrayHasKey('statuses', $response);
        // $this->assertEquals('SUCCESS', $response['status']);
    }

    // public function testsetOrderFields()
    // {
    //     $controller = new BaseLinkerController();
    //     $parameters = [
    //         'order_id' => 10340564,
    //         'custom_extra_fields' => ['77321' => '4'],

    //     ];
    //     $response = $controller->setOrderFields($parameters);

    //     $this->assertIsArray($response);
    //     $this->assertArrayHasKey('status', $response);
    //     // $this->assertEquals('SUCCESS', $response['status']);
    // }
    // public function testGetOrders()
    // {
    //     $controller = new BaseLinkerController();
    //     $parameters = [
    //         'order_id' => 10340564,
    //         'get_unconfirmed_orders' => true,
    //         'include_custom_extra_fields' => true,
    //     ];
    //     $response = $controller->getOrders($parameters);

    //     $this->assertIsArray($response);
    //     $this->assertArrayHasKey('orders', $response);
    // }

    // public function testSetOrderStatus()
    // {
    //     $controller = new BaseLinkerController();
    //     $parameters = [
    //         'order_id' => 10340564,
    //         'status_id' => 143145,
    //     ];
    //     $response = $controller->setOrderStatus($parameters);

    //     $this->assertIsArray($response);
    //     $this->assertArrayHasKey('status', $response);
    //     $this->assertEquals('SUCCESS', $response['status']);
    // }
}
