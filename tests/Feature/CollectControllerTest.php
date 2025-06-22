<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Http\Controllers\Api\CollectController;
use Illuminate\Http\Request;

class CollectControllerTest extends TestCase
{
    public function test_getLockedOrderIds_returns_locked_orders_for_other_users()
    {
        // Arrange
        $controller = new CollectController();
        $user = (object)['IDUzytkownika' => 1];

        // Simulate cache keys
        $cacheKeys = [
            'order_lock_1001',
            'order_lock_1002',
            'order_lock_1003',
        ];

        // Simulate cache values
        $cacheValues = [
            'order_lock_1001' => ['user_id' => 2],
            'order_lock_1002' => ['user_id' => 1], // same user, should not be returned
            'order_lock_1003' => ['user_id' => 3],
        ];

        // Mock Cache facade
        Cache::shouldReceive('getMemcached->getAllKeys')
            ->once()
            ->andReturn($cacheKeys);

        foreach ($cacheValues as $key => $value) {
            Cache::shouldReceive('get')
                ->with($key)
                ->andReturn($value);
        }

        // Act
        $locked = $controller->getLockedOrderIds($user);

        // Assert
        $this->assertContains('1001', $locked);
        $this->assertContains('1003', $locked);
        $this->assertNotContains('1002', $locked);
    }

    public function test_lockOrders_locks_and_returns_status()
    {
        // Arrange
        $controller = new CollectController();
        $user = (object)['IDUzytkownika' => 1];
        $orders = [1001, 1002, 1003];

        // Simulate cache: 1002 is already locked by another user
        Cache::shouldReceive('get')
            ->with('order_lock_1001')->andReturn(null);
        Cache::shouldReceive('get')
            ->with('order_lock_1002')->andReturn(['user_id' => 2]);
        Cache::shouldReceive('get')
            ->with('order_lock_1003')->andReturn(null);

        // Simulate cache put
        Cache::shouldReceive('put')
            ->with('order_lock_1001', ['user_id' => 1], \Mockery::any())
            ->once();
        Cache::shouldReceive('put')
            ->with('order_lock_1003', ['user_id' => 1], \Mockery::any())
            ->once();

        // Act
        $response = $controller->lockOrders($orders, $user);
        $data = $response->getData(true);

        // Assert
        $this->assertEqualsCanonicalizing([1001, 1003], $data['locked']);
        $this->assertEquals([1002], $data['unavailable']);
    }
}
