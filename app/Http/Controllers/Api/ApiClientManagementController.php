<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class ApiClientManagementController extends Controller
{
  /**
   * Get all API clients
   */
  public function index()
  {
    $clients = ApiClient::select([
      'id',
      'name',
      'api_key',
      'warehouse_ids',
      'permissions',
      'is_active',
      'rate_limit',
      'last_used_at',
      'ip_whitelist',
      'webhook_url',
      'created_at'
    ])->get();

    return response()->json([
      'success' => true,
      'data' => $clients
    ]);
  }

  /**
   * Create new API client
   */
  public function store(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'name' => 'required|string|max:255',
      'warehouse_ids' => 'required|array',
      'warehouse_ids.*' => 'integer',
      'permissions' => 'required|array',
      'permissions.*' => 'string|in:orders.read,orders.create,orders.update,returns.read,returns.create,deliveries.read,deliveries.update',
      'rate_limit' => 'nullable|integer|min:1|max:10000',
      'ip_whitelist' => 'nullable|array',
      'ip_whitelist.*' => 'ip',
      'webhook_url' => 'nullable|url'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'error' => 'Validation failed',
        'details' => $validator->errors()
      ], 400);
    }

    $client = ApiClient::create([
      'name' => $request->name,
      'api_key' => ApiClient::generateApiKey(),
      'api_secret' => ApiClient::generateApiSecret(),
      'warehouse_ids' => $request->warehouse_ids,
      'permissions' => $request->permissions,
      'rate_limit' => $request->rate_limit ?? 1000,
      'ip_whitelist' => $request->ip_whitelist,
      'webhook_url' => $request->webhook_url,
      'created_by' => auth()->id()
    ]);

    return response()->json([
      'success' => true,
      'data' => [
        'id' => $client->id,
        'name' => $client->name,
        'api_key' => $client->api_key,
        'api_secret' => $client->api_secret, // Only show on creation
        'warehouse_ids' => $client->warehouse_ids,
        'permissions' => $client->permissions,
        'rate_limit' => $client->rate_limit
      ]
    ], 201);
  }

  /**
   * Get specific API client
   */
  public function show($id)
  {
    $client = ApiClient::find($id);

    if (!$client) {
      return response()->json([
        'error' => 'API client not found'
      ], 404);
    }

    return response()->json([
      'success' => true,
      'data' => $client
    ]);
  }

  /**
   * Update API client
   */
  public function update(Request $request, $id)
  {
    $client = ApiClient::find($id);

    if (!$client) {
      return response()->json([
        'error' => 'API client not found'
      ], 404);
    }

    $validator = Validator::make($request->all(), [
      'name' => 'sometimes|string|max:255',
      'warehouse_ids' => 'sometimes|array',
      'warehouse_ids.*' => 'integer',
      'permissions' => 'sometimes|array',
      'permissions.*' => 'string|in:orders.read,orders.create,orders.update,returns.read,returns.create,deliveries.read,deliveries.update',
      'is_active' => 'sometimes|boolean',
      'rate_limit' => 'sometimes|integer|min:1|max:10000',
      'ip_whitelist' => 'sometimes|nullable|array',
      'ip_whitelist.*' => 'ip',
      'webhook_url' => 'sometimes|nullable|url'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'error' => 'Validation failed',
        'details' => $validator->errors()
      ], 400);
    }

    $client->update($request->only([
      'name',
      'warehouse_ids',
      'permissions',
      'is_active',
      'rate_limit',
      'ip_whitelist',
      'webhook_url'
    ]));

    return response()->json([
      'success' => true,
      'data' => $client
    ]);
  }

  /**
   * Regenerate API credentials
   */
  public function regenerateCredentials($id)
  {
    $client = ApiClient::find($id);

    if (!$client) {
      return response()->json([
        'error' => 'API client not found'
      ], 404);
    }

    $client->update([
      'api_key' => ApiClient::generateApiKey(),
      'api_secret' => ApiClient::generateApiSecret()
    ]);

    return response()->json([
      'success' => true,
      'data' => [
        'api_key' => $client->api_key,
        'api_secret' => $client->api_secret
      ]
    ]);
  }

  /**
   * Delete API client
   */
  public function destroy($id)
  {
    $client = ApiClient::find($id);

    if (!$client) {
      return response()->json([
        'error' => 'API client not found'
      ], 404);
    }

    $client->delete();

    return response()->json([
      'success' => true,
      'message' => 'API client deleted successfully'
    ]);
  }

  /**
   * Get API usage statistics
   */
  public function getUsageStats($id = null)
  {
    $query = ApiClient::select([
      'id',
      'name',
      'last_used_at',
      'is_active',
      'rate_limit'
    ]);

    if ($id) {
      $query->where('id', $id);
    }

    $clients = $query->get();

    foreach ($clients as $client) {
      // Add usage statistics (would need to implement rate limiting tracking)
      $client->usage_today = 0; // Placeholder
      $client->usage_this_month = 0; // Placeholder
    }

    return response()->json([
      'success' => true,
      'data' => $id ? $clients->first() : $clients
    ]);
  }
}
