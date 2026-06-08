<?php

namespace App\Services;

use App\Http\Requests\StockFilterRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * StockQueryService
 *
 * Query langsung ke view v_stock_ph di DB DWH.
 * Tidak ada temp table — data kecil (~7.850 row) cukup query langsung.
 *
 * Kolom v_stock_ph:
 *   source, pt_part, pt_desc, cabang, stock, ptprice, value,
 *   pilar, exp_date, kelompok_ed
 */
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
            ->table('v_stock_ph as s');

        if ($request->filled('source')) {
            $query->where('s.source', $request->source);
        }

        if ($request->filled('pilar')) {
            $query->where('s.pilar', $request->pilar);
        }

        if ($request->filled('cabang')) {
            $query->where('s.cabang', $request->cabang);
        }

        if ($request->filled('kelompok_ed')) {
            $query->where('s.kelompok_ed', $request->kelompok_ed);
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
            $request->get('source', ''),
            $request->get('pilar', ''),
            $request->get('cabang', ''),
            $request->get('kelompok_ed', ''),
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
