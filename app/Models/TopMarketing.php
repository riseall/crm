<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopMarketing extends Model
{
    protected $connection = 'db_dwh';
    protected $table = 'crm_top_marketing';
    protected $guarded = [];
}
