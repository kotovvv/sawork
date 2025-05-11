<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collect extends Model
{
    protected $connection = 'second_mysql';
    // Table name
    protected $table = 'collect';

    // Primary key
    protected $primaryKey = 'IDOrder';

    // Disable auto-incrementing as the primary key is not auto-incremented
    public $incrementing = false;

    // Specify the data type of the primary key
    protected $keyType = 'int';

    // Timestamps (disable if not used in the table)
    public $timestamps = false;

    // Fillable fields for mass assignment
    protected $fillable = [
        'IDUzytkownika',
        'IDOrder',
        'Date',
        'status',
        'created_doc',
        'IDsElementuRuchuMagazynowego',
    ];

    // Cast attributes to specific data types
    protected $casts = [
        'Date' => 'datetime',
        'IDsElementuRuchuMagazynowego' => 'array', // JSON field
    ];
}
