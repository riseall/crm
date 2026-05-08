<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mapping extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mst_mappings';
    protected $primaryKey = 'id_coverage';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_coverage',
        'cov_desc',
        'cov_divisi',
        'cov_ket_cbg',
        'cov_pilar',
        'cov_level',
        'cov_level_desc',
        'cov_nik',
        'tanggung_jwb_pilar',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date:Y-m-d',
        'valid_to'   => 'date:Y-m-d',
        'is_active'  => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'cov_nik', 'nik');
    }

    public function users()
    {
        return $this->user();
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class, 'id_coverage', 'id_coverage');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'id_coverage', 'id_coverage');
    }

    // Scope: hanya yang aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Validasi Single/Combo sebelum simpan
    public static function validateCoverage($nik, $pilar, $tanggungJwb, $excludeId = null)
    {
        $query = self::where('cov_nik', $nik)->where('is_active', true);

        if ($excludeId) {
            $query->where('id_coverage', '!=', $excludeId);
        }

        $existing = $query->get();

        if ($tanggungJwb === 'Single') {
            if ($existing->count() >= 1) {
                return ['valid' => false, 'message' => 'NIK ini sudah memiliki coverage aktif (Single hanya boleh 1 coverage).'];
            }
        }

        if ($tanggungJwb === 'Combo') {
            if ($pilar === 'OTC') {
                return ['valid' => false, 'message' => 'OTC tidak bisa masuk Combo.'];
            }
            $samePilar = $existing->where('cov_pilar', $pilar)->count();
            if ($samePilar >= 1) {
                return ['valid' => false, 'message' => "NIK ini sudah memiliki coverage pilar {$pilar} yang aktif."];
            }
            if ($existing->count() >= 2) {
                return ['valid' => false, 'message' => 'Combo maksimal 2 coverage aktif (Ethical + OGB).'];
            }
        }

        return ['valid' => true, 'message' => 'OK'];
    }
}
