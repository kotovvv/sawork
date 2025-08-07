<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class ApiAuthentication
{
  public function handle(Request $request, Closure $next, ...$permissions)
  {
    $apiKey = $request->header('X-API-Key');
    $apiSecret = $request->header('X-API-Secret');

    if (!$apiKey || !$apiSecret) {
      return response()->json([
        'error' => 'API credentials required',
        'message' => 'X-API-Key and X-API-Secret headers are required'
      ], 401);
    }

    // Cache API client lookup for performance
    $cacheKey = "api_client_{$apiKey}";
    $client = Cache::remember($cacheKey, 300, function () use ($apiKey) {
      return ApiClient::where('api_key', $apiKey)
        ->where('is_active', 1) // Используем 1 вместо true для MSSQL
        ->first();
    });

    if (!$client || !hash_equals($client->api_secret, $apiSecret)) {
      return response()->json([
        'error' => 'Invalid API credentials'
      ], 401);
    }

    // Check IP whitelist if configured
    if ($client->ip_whitelist && !empty($client->ip_whitelist)) {
      $clientIp = $request->ip();
      if (!in_array($clientIp, $client->ip_whitelist)) {
        return response()->json([
          'error' => 'IP address not whitelisted',
          'ip' => $clientIp
        ], 403);
      }
    }

    // Check rate limiting
    $rateLimitKey = "api_rate_limit_{$client->id}";
    if (RateLimiter::tooManyAttempts($rateLimitKey, $client->rate_limit)) {
      $retryAfter = RateLimiter::availableIn($rateLimitKey);
      return response()->json([
        'error' => 'Rate limit exceeded',
        'retry_after' => $retryAfter
      ], 429);
    }

    RateLimiter::hit($rateLimitKey, 3600); // 1 hour window

    // Check permissions
    if (!empty($permissions)) {
      foreach ($permissions as $permission) {
        if (!$client->hasPermission($permission)) {
          return response()->json([
            'error' => 'Insufficient permissions',
            'required_permission' => $permission
          ], 403);
        }
      }
    }

    // Update last used timestamp
    $client->updateLastUsed();

    // Add client to request for use in controllers
    $request->merge(['api_client' => $client]);

    return $next($request);
  }
}
