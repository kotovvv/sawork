<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierForms extends Model
{
    protected $connection = 'second_mysql';
    protected $table = 'courier_forms';
    protected $primaryKey = 'courier_code';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'form' => 'array',
    ];

    protected $fillable = [
        'courier_code',
        'form',
    ];
}
