<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    use HasFactory;

    // Используем основное соединение (MSSQL) вместо second_mysql
    // protected $connection = 'second_mysql';

    protected $fillable = [
        'name',
        'api_key',
        'api_secret',
        'warehouse_ids',
        'permissions',
        'is_active',
        'rate_limit',
        'last_used_at',
        'ip_whitelist',
        'webhook_url',
        'created_by'
    ];

    protected $casts = [
        'warehouse_ids' => 'array',
        'permissions' => 'array',
        'ip_whitelist' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime'
    ];

    // Аксессоры и мутаторы для работы с JSON в MSSQL
    public function getWarehouseIdsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setWarehouseIdsAttribute($value)
    {
        $this->attributes['warehouse_ids'] = json_encode($value);
    }

    public function getPermissionsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setPermissionsAttribute($value)
    {
        $this->attributes['permissions'] = json_encode($value);
    }

    public function getIpWhitelistAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }

    public function setIpWhitelistAttribute($value)
    {
        $this->attributes['ip_whitelist'] = $value ? json_encode($value) : null;
    }

    protected $hidden = [
        'api_secret'
    ];

    public static function generateApiKey()
    {
        return Str::random(32);
    }

    public static function generateApiSecret()
    {
        return Str::random(64);
    }

    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function canAccessWarehouse($warehouseId)
    {
        return in_array($warehouseId, $this->warehouse_ids ?? []);
    }

    public function updateLastUsed()
    {
        $this->last_used_at = now();
        $this->save();
    }
}
