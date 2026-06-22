<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'db_dwh';
    protected $table = 'crm_mst_products';

    protected $fillable = [
        'pt_ph',
        'id_coverage',
        'needs_replacement',
    ];

    protected $casts = [
        'needs_replacement' => 'boolean',
    ];

    public function mpart()
    {
        return $this->belongsTo(MPart::class, 'pt_ph', 'pt_ph');
    }

    public function coverage()
    {
        return $this->belongsTo(Mapping::class, 'id_coverage', 'id_coverage')
            ->where('is_active', true);
    }
}
