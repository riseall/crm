<?php

namespace App\Services;

use App\Http\Requests\StockFilterRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StockQueryService
{
    private const CACHE_TTL = 300;
    public  const DB_DWH    = 'db_dwh';

    // -------------------------------------------------------
    // BASE QUERY — semua query stock dimulai dari sini
    // -------------------------------------------------------

    public function baseQuery(StockFilterRequest $request): Builder
    {
        $query = DB::connection(self::DB_DWH)
            ->table('crm_stock_summary as s');

        if ($request->filled('source')) {
            $query->whereIn('s.source', $request->input('source'));
        }

        if ($request->filled('pilar')) {
            $query->whereIn('s.pilar', $request->input('pilar'));
        }

        if ($request->filled('cabang')) {
            $query->whereIn('s.cabang', $request->input('cabang'));
        }

        if ($request->filled('kelompok_ed')) {
            $query->whereIn('s.kelompok_ed', $request->input('kelompok_ed'));
        }

        return $query;
    }

    // -------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------

    public function cacheTtl(): int
    {
        return self::CACHE_TTL;
    }

    public function cacheKey(string $prefix, StockFilterRequest $request, ?string $extra = null): string
    {
        return 'stock:' . $prefix . ':' . md5(implode('|', [
            json_encode($request->get('source', [])),
            json_encode($request->get('pilar', [])),
            json_encode($request->get('cabang', [])),
            json_encode($request->get('kelompok_ed', [])),
            $extra ?? '',
        ]));
    }

    public function countGrouped(Builder $query): int
    {
        return DB::connection(self::DB_DWH)
            ->query()
            ->fromSub($query, 'agg')
            ->count();
    }

    // -------------------------------------------------------
    // STATIC HELPERS — label & urutan kelompok ED
    // -------------------------------------------------------

    public static function kelompokEdLabel(string $key): string
    {
        $labels = [
            'sudah_ed' => 'Sudah ED',
            '1_3'      => '1-3 Bln',
            '4_7'      => '4-7 Bln',
            '8_12'     => '8-12 Bln',
            '13_17'    => '13-17 Bln',
            'gt_18'    => '>18 Bln',
        ];

        return $labels[$key] ?? $key;
    }

    /** Urutan dari paling kritis ke paling aman */
    public static function kelompokEdOrder(): array
    {
        return ['sudah_ed', '1_3', '4_7', '8_12', '13_17', 'gt_18'];
    }
}
