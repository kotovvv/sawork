<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSource extends Model
{
    use HasFactory;

    // Используем основное соединение (MSSQL) вместо second_mysql
    // protected $connection = 'second_mysql';

    const SOURCE_BASELINKER = 'baselinker';
    const SOURCE_API = 'api';
    const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'order_id',
        'warehouse_id',
        'source_type',
        'source_reference',
        'api_client_id',
        'external_order_id',
        'source_data',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'source_data' => 'array'
    ];

    // Аксессоры и мутаторы для работы с JSON в MSSQL
    public function getSourceDataAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }

    public function setSourceDataAttribute($value)
    {
        $this->attributes['source_data'] = $value ? json_encode($value) : null;
    }

    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }

    public static function createForBaselinker($orderId, $warehouseId, $externalOrderId = null)
    {
        return self::create([
            'order_id' => $orderId,
            'warehouse_id' => $warehouseId,
            'source_type' => self::SOURCE_BASELINKER,
            'source_reference' => $externalOrderId,
            'external_order_id' => $externalOrderId
        ]);
    }

    public static function createForApi($orderId, $warehouseId, $apiClientId, $externalOrderId = null, $sourceData = null)
    {
        return self::create([
            'order_id' => $orderId,
            'warehouse_id' => $warehouseId,
            'source_type' => self::SOURCE_API,
            'api_client_id' => $apiClientId,
            'external_order_id' => $externalOrderId,
            'source_data' => $sourceData
        ]);
    }
}
