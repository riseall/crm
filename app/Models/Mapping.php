<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mapping extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'db_dwh';
    protected $table = 'crm_mst_mappings';

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
    public static function validateCoverage($nik, $pilar, $tanggungJwb, $excludeId = null, bool $willBeActive = true)
    {
        if (!$willBeActive) {
            return ['valid' => true, 'message' => 'OK'];
        }

        $pilar = self::normalizePilar($pilar);

        $query = self::where('cov_nik', $nik)->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existing = $query->get();

        if ($tanggungJwb === 'Single') {
            if ($existing->count() >= 1) {
                return ['valid' => false, 'message' => 'NIK ini sudah memiliki coverage aktif. Untuk menambah coverage baru dengan PIC yang sama, ubah coverage existing menjadi Combo terlebih dahulu.'];
            }
        }

        if ($tanggungJwb === 'Combo') {
            if ($pilar === 'OTC') {
                return ['valid' => false, 'message' => 'OTC tidak bisa masuk Combo.'];
            }

            if (!in_array($pilar, ['ETHICAL', 'OGB'], true)) {
                return ['valid' => false, 'message' => 'Combo hanya berlaku untuk pilar ETHICAL dan OGB.'];
            }

            $nonComboCoverage = $existing->first(function ($row) {
                return $row->tanggung_jwb_pilar !== 'Combo';
            });
            if ($nonComboCoverage) {
                return ['valid' => false, 'message' => 'PIC ini masih memiliki coverage aktif yang belum bertanggung jawab Combo. Edit coverage tersebut menjadi Combo terlebih dahulu sebelum menambah coverage baru.'];
            }

            $samePilar = $existing
                ->filter(fn($row) => self::normalizePilar($row->cov_pilar) === $pilar)
                ->count();
            if ($samePilar >= 1) {
                return ['valid' => false, 'message' => "NIK ini sudah memiliki coverage pilar {$pilar} yang aktif."];
            }

            if ($existing->count() >= 2) {
                return ['valid' => false, 'message' => 'Combo maksimal 2 coverage aktif (Ethical + OGB).'];
            }
        }

        return ['valid' => true, 'message' => 'OK'];
    }

    private static function normalizePilar(?string $pilar): ?string
    {
        switch ($pilar) {
            case 'ETHICOM':
            case 'ETHIPROM':
                return 'ETHICAL';
            case 'GENERIK':
                return 'OGB';
            default:
                return $pilar;
        }
    }
}
