<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mst_outlets';

    protected $fillable = [
        'id_outlet',
        'id_coverage',
        'needs_replacement',
    ];

    protected $casts = [
        'needs_replacement' => 'boolean',
    ];

    public function coverage()
    {
        return $this->belongsTo(Mapping::class, 'id_coverage', 'id_coverage');
    }

    // Scope: outlet yang butuh pengganti
    public function scopeNeedsReplacement($query)
    {
        return $query->where('needs_replacement', true);
    }
}
