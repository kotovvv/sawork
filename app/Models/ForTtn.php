<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForTtn extends Model
{
    protected $connection = 'second_mysql';
    protected $table = 'for_ttn';

    protected $fillable = [
        'api_service_id',
        'id_warehouse',
        'delivery_method',
        'order_source',
        'order_source_id',
        'order_source_name',
        'courier_code',
        'account_id',
        'info_account',
    ];

    protected $casts = [
        'info_account' => 'array',
    ];
}
