<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\CollectController;

class CollectControllerTest extends TestCase
{
    public function testGetTransportData()
    {
        // Создаем объект Request с параметром user
        $request = new Request();
        $request->user = (object) ['IDUzytkownika' => 4];

        // Вызываем метод контроллера
        $controller = new CollectController();
        $response = $controller->getTransportData($request);

        // Проверяем, что ответ является JSON
        $this->assertJson($response->getContent());

        // Выводим результат для проверки
        dump(json_decode($response->getContent(), true));
    }
}
