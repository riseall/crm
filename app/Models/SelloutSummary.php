<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelloutSummary extends Model
{
    protected $connection = 'db_dwh';
    protected $table = 'crm_sellout_summary';
    protected $guarded = [];
    public $timestamps = true;

    protected $casts = [
        'tgl_faktur' => 'date:Y-m-d',
    ];
}
