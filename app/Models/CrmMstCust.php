<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMstCust extends Model
{
    protected $connection = 'db_dwh';
    protected $table = 'crm_mst_cust';
    protected $primaryKey = 'id_konversi';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_konversi',
        'nama_outlet',
        'alamat',
        'kota',
        'segment',
        'divisi'
    ];

    public function distributors()
    {
        return $this->hasMany(CrmMstCustDist::class, 'id_konversi', 'id_konversi');
    }

    public function coverages()
    {
        return $this->hasMany(CrmMstCustCov::class, 'id_konversi', 'id_konversi');
    }
}
