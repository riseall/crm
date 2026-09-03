<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UnmappedSelloutService
{
    private const CONN_DWH = 'db_dwh';

    /**
     * Helper to get table with database name on DWH connection.
     */
    private function dwhTable(string $table): string
    {
        return DB::connection(self::CONN_DWH)->getDatabaseName() . '.' . $table;
    }

    // =========================================================================
    // LEVEL 1: UNMAPPED MASTER (id_konversi IS NULL)
    // =========================================================================

    /**
     * Base query for sellout rows without id_konversi.
     */
    public function getUnmappedMasterBaseQuery()
    {
        return DB::connection(self::CONN_DWH)
            ->table('crm_sellout_summary as so')
            ->where(function ($q) {
                $q->whereNull('so.id_konversi')
                    ->orWhere('so.id_konversi', '');
            });
    }

    /**
     * Grouped distinct unmapped sellout outlets query.
     */
    public function getUnmappedMasterQuery()
    {
        return $this->getUnmappedMasterBaseQuery()
            ->groupBy([
                'so.dist',
                'so.id_cbg',
                'so.id_outlet',
                'so.nama_outlet',
                'so.segment_dist',
            ])
            ->select([
                'so.dist',
                'so.id_cbg',
                'so.id_outlet',
                'so.nama_outlet',
                'so.segment_dist',
            ]);
    }

    /**
     * Total count of distinct unmapped master outlets.
     */
    public function getUnmappedMasterCount(): int
    {
        $stats = $this->getUnmappedMasterBaseQuery()
            ->selectRaw('COUNT(DISTINCT CONCAT(COALESCE(so.dist, ""), "_", COALESCE(so.id_cbg, ""), "_", COALESCE(so.id_outlet, ""))) as total_count')
            ->first();

        return (int) ($stats->total_count ?? 0);
    }

    // =========================================================================
    // LEVEL 2: UNCOVERED PILAR (Has id_konversi, but missing pilar coverage)
    // =========================================================================

    /**
     * Base query for sellout rows with id_konversi but no matching coverage for transaction's pilar.
     */
    public function getUncoveredPilarBaseQuery()
    {
        return DB::connection(self::CONN_DWH)
            ->table('crm_sellout_summary as so')
            ->whereNotNull('so.id_konversi')
            ->where('so.id_konversi', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(DB::raw($this->dwhTable('crm_mst_cust_cov') . ' as cov'))
                    ->whereColumn('cov.id_konversi', 'so.id_konversi')
                    ->whereNull('cov.deleted_at')
                    ->whereRaw("(cov.pilar = so.pilar_sub OR (cov.pilar = 'ETHICAL' AND so.pilar_sub IN ('ETHICOM', 'ETHIPROM')))");
            });
    }

    /**
     * Grouped distinct uncovered pilar query.
     */
    public function getUncoveredPilarQuery()
    {
        $pilarExpr = "CASE WHEN so.pilar_sub IN ('ETHICOM', 'ETHIPROM', 'ETHICAL') THEN 'ETHICAL' ELSE COALESCE(NULLIF(so.pilar_sub, ''), 'ETHICAL') END";

        return $this->getUncoveredPilarBaseQuery()
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_cust') . ' as master'), 'so.id_konversi', '=', 'master.id_konversi')
            ->groupBy([
                'so.id_konversi',
                'master.nama_outlet',
                'master.segment',
                DB::raw($pilarExpr),
            ])
            ->select([
                'so.id_konversi',
                'master.nama_outlet',
                'master.segment',
                DB::raw("{$pilarExpr} as pilar"),
                DB::raw("COUNT(DISTINCT CONCAT(COALESCE(so.dist, ''), '_', COALESCE(so.id_cbg, ''), '_', COALESCE(so.id_outlet, ''))) as total_dist_outlets"),
            ]);
    }

    /**
     * Total count of distinct (id_konversi, pilar) needing coverage assignment.
     */
    public function getUncoveredPilarCount(): int
    {
        $pilarExpr = "CASE WHEN so.pilar_sub IN ('ETHICOM', 'ETHIPROM', 'ETHICAL') THEN 'ETHICAL' ELSE COALESCE(NULLIF(so.pilar_sub, ''), 'ETHICAL') END";

        $stats = $this->getUncoveredPilarBaseQuery()
            ->selectRaw("COUNT(DISTINCT CONCAT(so.id_konversi, '_', {$pilarExpr})) as total_count")
            ->first();

        return (int) ($stats->total_count ?? 0);
    }
}
