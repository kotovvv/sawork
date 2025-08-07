<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Database\Seeder;

class ApiClientSeeder extends Seeder
{
  public function run()
  {
    // Create a demo API client
    ApiClient::on('second_mysql')->create([
      'name' => 'Demo Client',
      'api_key' => 'demo_api_key_12345678901234567890',
      'api_secret' => 'demo_api_secret_1234567890123456789012345678901234567890123456789012',
      'warehouse_ids' => [1, 2],
      'permissions' => ['orders.read', 'orders.create', 'orders.update', 'returns.read'],
      'rate_limit' => 1000,
      'is_active' => true,
      'ip_whitelist' => null,
      'webhook_url' => null
    ]);

    $this->command->info('Demo API client created with key: demo_api_key_12345678901234567890');
  }
}
