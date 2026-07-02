<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockSummary extends Model
{
    use HasFactory;

    protected $connection = 'db_dwh';
    protected $table = 'crm_stock_summary';
    protected $guarded = [];

    protected $casts = [
        'stock'    => 'float',
        'ptprice'  => 'float',
        'value'    => 'float',
        'exp_date' => 'date:Y-m-d',
    ];
}