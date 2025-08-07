<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;

class ManageApiClient extends Command
{
  protected $signature = 'api:client {action} {--name=} {--id=} {--warehouses=} {--permissions=}';

  protected $description = 'Manage API clients';

  public function handle()
  {
    $action = $this->argument('action');

    switch ($action) {
      case 'create':
        $this->createClient();
        break;
      case 'list':
        $this->listClients();
        break;
      case 'show':
        $this->showClient();
        break;
      case 'regenerate':
        $this->regenerateCredentials();
        break;
      case 'disable':
        $this->disableClient();
        break;
      case 'enable':
        $this->enableClient();
        break;
      default:
        $this->error('Unknown action. Use: create, list, show, regenerate, disable, enable');
    }
  }

  private function createClient()
  {
    $name = $this->option('name') ?: $this->ask('Client name');
    $warehousesInput = $this->option('warehouses') ?: $this->ask('Warehouse IDs (comma separated)');
    $permissionsInput = $this->option('permissions') ?: $this->ask('Permissions (comma separated)', 'orders.read,orders.create,orders.update');

    $warehouses = array_map('intval', explode(',', $warehousesInput));
    $permissions = explode(',', $permissionsInput);

    $client = ApiClient::create([
      'name' => $name,
      'api_key' => ApiClient::generateApiKey(),
      'api_secret' => ApiClient::generateApiSecret(),
      'warehouse_ids' => $warehouses,
      'permissions' => $permissions,
      'rate_limit' => 1000,
      'is_active' => true
    ]);

    $this->info('API Client created successfully!');
    $this->table(['Field', 'Value'], [
      ['ID', $client->id],
      ['Name', $client->name],
      ['API Key', $client->api_key],
      ['API Secret', $client->api_secret],
      ['Warehouses', implode(',', $client->warehouse_ids)],
      ['Permissions', implode(',', $client->permissions)]
    ]);
  }

  private function listClients()
  {
    $clients = ApiClient::all(['id', 'name', 'warehouse_ids', 'is_active', 'last_used_at', 'created_at']);

    $data = $clients->map(function ($client) {
      return [
        $client->id,
        $client->name,
        implode(',', $client->warehouse_ids ?? []),
        $client->is_active ? 'Active' : 'Inactive',
        $client->last_used_at ? $client->last_used_at->format('Y-m-d H:i:s') : 'Never',
        $client->created_at->format('Y-m-d H:i:s')
      ];
    });

    $this->table(['ID', 'Name', 'Warehouses', 'Status', 'Last Used', 'Created'], $data);
  }

  private function showClient()
  {
    $id = $this->option('id') ?: $this->ask('Client ID');
    $client = ApiClient::find($id);

    if (!$client) {
      $this->error('Client not found');
      return;
    }

    $this->table(['Field', 'Value'], [
      ['ID', $client->id],
      ['Name', $client->name],
      ['API Key', $client->api_key],
      ['Warehouses', implode(',', $client->warehouse_ids ?? [])],
      ['Permissions', implode(',', $client->permissions ?? [])],
      ['Rate Limit', $client->rate_limit],
      ['Active', $client->is_active ? 'Yes' : 'No'],
      ['IP Whitelist', implode(',', $client->ip_whitelist ?? [])],
      ['Webhook URL', $client->webhook_url ?? 'Not set'],
      ['Last Used', $client->last_used_at ? $client->last_used_at->format('Y-m-d H:i:s') : 'Never'],
      ['Created', $client->created_at->format('Y-m-d H:i:s')]
    ]);
  }

  private function regenerateCredentials()
  {
    $id = $this->option('id') ?: $this->ask('Client ID');
    $client = ApiClient::find($id);

    if (!$client) {
      $this->error('Client not found');
      return;
    }

    $client->update([
      'api_key' => ApiClient::generateApiKey(),
      'api_secret' => ApiClient::generateApiSecret()
    ]);

    $this->info('Credentials regenerated successfully!');
    $this->table(['Field', 'Value'], [
      ['API Key', $client->api_key],
      ['API Secret', $client->api_secret]
    ]);
  }

  private function disableClient()
  {
    $id = $this->option('id') ?: $this->ask('Client ID');
    $client = ApiClient::find($id);

    if (!$client) {
      $this->error('Client not found');
      return;
    }

    $client->update(['is_active' => false]);
    $this->info("Client '{$client->name}' disabled successfully");
  }

  private function enableClient()
  {
    $id = $this->option('id') ?: $this->ask('Client ID');
    $client = ApiClient::find($id);

    if (!$client) {
      $this->error('Client not found');
      return;
    }

    $client->update(['is_active' => true]);
    $this->info("Client '{$client->name}' enabled successfully");
  }
}
