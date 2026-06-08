<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockPhInGpj extends Model
{
    protected $connection = 'db_dwh';
    protected $table = 'stock_ph_in_gpj';
    protected $guarded = [];
    public $timestamps = true;

    protected $casts = [
        'stock' => 'decimal:4',
        'ptprice' => 'decimal:4',
        'umconv' => 'decimal:4',
        'rharga' => 'decimal:4',
        'expdt' => 'date:Y-m-d',
    ];
}
