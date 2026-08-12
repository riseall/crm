<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMstCustCov extends Model
{
    protected $connection = 'db_dwh';
    protected $table = 'crm_mst_cust_cov';

    protected $fillable = [
        'id_konversi',
        'pilar',
        'id_coverage'
    ];

    public function master()
    {
        return $this->belongsTo(CrmMstCust::class, 'id_konversi', 'id_konversi');
    }

    public function mapping()
    {
        return $this->belongsTo(Mapping::class, 'id_coverage', 'id_coverage');
    }
}
