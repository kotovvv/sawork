<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ApiAuthentication
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'X-API-Key header is required'
            ], 401);
        }

        // Check API key from WarehouseApiKeyService
        $warehouseId = \App\Services\WarehouseApiKeyService::getWarehouseByApiKey($request);
        if (!$warehouseId) {
            return response()->json([
                'error' => 'Invalid API key'
            ], 401);
        }

        // Rate limiting
        $rateLimitKey = 'api_rate_limit:' . $apiKey;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1000)) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => RateLimiter::availableIn($rateLimitKey)
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 3600); // 1 hour window

        // Add client info to request
        $request->attributes->set('api_client', [
            'api_key' => $apiKey,
            'warehouse_id' => $warehouseId
        ]);

        return $next($request);
    }

    /**
     * Validate API key against .env configuration
     */
    private function validateApiKey($apiKey)
    {
        // Deprecated: now handled by WarehouseApiKeyService
        return null;
    }
}
