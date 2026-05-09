<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MCustDist extends Model
{
    use HasFactory;

    protected $table = 'v_cust_dist';

    public $incrementing = false;
    public $timestamps = false;
}
