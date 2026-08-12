<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmMstCustDist extends Model
{
    protected $connection = 'db_dwh';
    protected $table = 'crm_mst_cust_dist';

    protected $fillable = [
        'id_konversi',
        'dist',
        'id_cbg',
        'id_outlet',
        'id_outlet_cbg'
    ];

    public function master()
    {
        return $this->belongsTo(CrmMstCust::class, 'id_konversi', 'id_konversi');
    }
}
