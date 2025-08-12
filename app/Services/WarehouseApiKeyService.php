<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class WarehouseApiKeyService
{
    /**
     * Get warehouse ID by API key (по таблице settings)
     */
    public static function getWarehouseByApiKey(Request $request)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');
        if (empty($apiKey)) {
            Log::warning('No API key provided in request');
            return null;
        }

        // Поиск по settings с расшифровкой api_token
        $settings = \Illuminate\Support\Facades\Cache::remember('warehouse_api_keys', 86400, function () {
            return DB::table('settings')
                ->select('value', 'for_obj')
                ->where('obj_name', 'sklad_token')
                ->get();
        });
        foreach ($settings as $setting) {
            try {
                $decrypted = Crypt::decryptString($setting->value);
                if ($decrypted === $apiKey) {
                    return intval($setting->for_obj);
                }
            } catch (\Exception $e) {
                // ignore decryption errors
            }
        }
        return null;
    }
}
