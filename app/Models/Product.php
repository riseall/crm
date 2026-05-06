<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mst_products';

    protected $fillable = [
        'pt_ph',
        'id_coverage',
    ];

    public function coverage()
    {
        return $this->belongsTo(Mapping::class, 'id_coverage', 'id_coverage');
    }
}
