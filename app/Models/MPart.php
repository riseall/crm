<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MPart extends Model
{
    use HasFactory;

    protected $connection = 'db_dwh';
    protected $table = 'v_pt_mstr_dist';

    public $incrementing = false;
    public $timestamps = false;

    public function products()
    {
        return $this->hasMany(Product::class, 'pt_ph', 'pt_ph');
    }

    public function productInternal()
    {
        return $this->belongsTo(Product::class, 'pt_ph', 'pt_ph');
    }
}
