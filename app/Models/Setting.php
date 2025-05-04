<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $connection = 'second_mysql';
    // Specify the table name
    protected $table = 'settings';

    // Specify the primary key
    protected $primaryKey = 'id';

    // Disable auto-incrementing if the primary key is not auto-incremented
    public $incrementing = true;

    // Specify the data type of the primary key
    protected $keyType = 'int';

    // Disable timestamps if the table does not have `created_at` and `updated_at` columns
    public $timestamps = false;

    // Specify the fillable fields for mass assignment
    protected $fillable = [
        'obj_name',
        'for_obj',
        'key',
        'value',
    ];
}
