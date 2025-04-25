<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogOrder extends Model
{
    protected $connection = 'second_mysql';

    protected $table = 'log_orders';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'number',
        'message',
        'type',
    ];
}
