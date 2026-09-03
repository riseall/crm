<?php

namespace App\Http\Controllers;

use App\Services\CoverageAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SelloutAdjustmentController extends Controller
{
    private const CONN_DWH = 'db_dwh';
    private const SUMMARY_TABLE = 'crm_sellout_summary';

    public const DIVISI_OPTIONS = ['CMA', 'REGULER'];

    public const SUB_DIVISI_OPTIONS = [
        'CMA-MMR',
        'CMA-OTC',
        'CMA-PBF',
        'REGULER',
    ];

    public const DIVISI_SUB_DIVISI_MAP = [
        'CMA' => ['CMA-MMR', 'CMA-OTC', 'CMA-PBF'],
        'REGULER' => ['REGULER'],
    ];

    private CoverageAccessService $coverageAccess;

    public function __construct(CoverageAccessService $coverageAccess)
    {
        $this->middleware('auth');
        $this->coverageAccess = $coverageAccess;
    }

    /**
     * Tampilan utama halaman Adjustment Data Sellout
     */
    public function index(Request $request)
    {
        // Summary stats untuk cards
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        // Opsi Divisi & Sub Divisi
        $divisiOptions = self::DIVISI_OPTIONS;
        $subDivisiOptions = self::SUB_DIVISI_OPTIONS;
        $divisiSubDivisiMap = self::DIVISI_SUB_DIVISI_MAP;

        // Ambil list pilar unik
        $pilarOptions = ['ETHIPROM', 'ETHICOM', 'OGB', 'OTC'];

        // Ambil list distributor
        $distOptions = ['KFTD', 'AAM', 'METD'];

        // Default stats (Bulan Berjalan)
        $monthStats = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE)
            ->whereBetween('tgl_faktur', [$startOfMonth, $endOfMonth])
            ->selectRaw("
                COUNT(*) as total_count,
                COALESCE(SUM(netto), 0) as total_netto,
                SUM(CASE WHEN UPPER(TRIM(divisi)) = 'REGULER' THEN 1 ELSE 0 END) as count_reguler,
                SUM(CASE WHEN UPPER(TRIM(divisi)) = 'CMA' THEN 1 ELSE 0 END) as count_cma
            ")
            ->first();

        $totalData = $monthStats ? (int) $monthStats->total_count : 0;
        $totalNetto = $monthStats ? (float) $monthStats->total_netto : 0.0;
        $countReguler = $monthStats ? (int) $monthStats->count_reguler : 0;
        $countCma = $monthStats ? (int) $monthStats->count_cma : 0;

        return view('page.sellout_adjustment.index', compact(
            'totalData',
            'totalNetto',
            'countReguler',
            'countCma',
            'divisiOptions',
            'subDivisiOptions',
            'divisiSubDivisiMap',
            'pilarOptions',
            'distOptions',
            'startOfMonth',
            'endOfMonth'
        ));
    }

    /**
     * Server-side DataTables untuk transaksi sellout
     */
    public function data(Request $request)
    {
        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->select([
                'so.id',
                'so.source_table',
                'so.source_id',
                'so.tgl_faktur',
                'so.no_faktur',
                'so.dist',
                'so.id_cbg',
                'so.id_cbg_ph',
                'so.id_outlet',
                'so.id_konversi',
                'so.nama_outlet',
                'so.code_product',
                'so.code_prod_ph',
                'so.desc',
                'so.pilar_sub',
                'so.divisi',
                'so.sub_divisi',
                'so.regional',
                'so.qty',
                'so.hna',
                'so.netto',
            ]);

        // Filter Periode / Tanggal
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->input('start_date'))->startOfDay()->toDateTimeString();
            $end = Carbon::parse($request->input('end_date'))->endOfDay()->toDateTimeString();
            $query->whereBetween('so.tgl_faktur', [$start, $end]);
        } elseif ($request->filled('bulan') && $request->filled('tahun')) {
            $year = (int) $request->input('tahun');
            $month = (int) $request->input('bulan');
            $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateTimeString();
            $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateTimeString();
            $query->whereBetween('so.tgl_faktur', [$start, $end]);
        }

        // Filter Distributor
        if ($request->filled('dist')) {
            $dist = (array) $request->input('dist');
            if (!empty($dist)) {
                $query->whereIn('so.dist', $dist);
            }
        }

        // Filter Divisi
        if ($request->filled('divisi')) {
            $divisi = $request->input('divisi');
            if ($divisi === 'UNASSIGNED') {
                $query->where(function ($q) {
                    $q->whereNull('so.divisi')->orWhere('so.divisi', '');
                });
            } else {
                $query->where('so.divisi', $divisi);
            }
        }

        // Filter Sub Divisi
        if ($request->filled('sub_divisi')) {
            $subDivisi = $request->input('sub_divisi');
            if ($subDivisi === 'UNASSIGNED') {
                $query->where(function ($q) {
                    $q->whereNull('so.sub_divisi')->orWhere('so.sub_divisi', '');
                });
            } else {
                $query->where('so.sub_divisi', $subDivisi);
            }
        }

        // Filter Pilar
        if ($request->filled('pilar')) {
            $pilar = (array) $request->input('pilar');
            if (!empty($pilar)) {
                $query->whereIn('so.pilar_sub', $pilar);
            }
        }

        // Filter Produk (Kode / Deskripsi)
        if ($request->filled('product_code')) {
            $prodCode = trim((string) $request->input('product_code'));
            $query->where(function ($q) use ($prodCode) {
                $q->where('so.code_prod_ph', $prodCode)
                    ->orWhere('so.code_product', $prodCode);
            });
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('checkbox', function ($row) {
                return '<div class="custom-control custom-checkbox text-center">
                    <input type="checkbox" class="custom-control-input row-checkbox" id="chk_' . $row->id . '" value="' . $row->id . '" data-divisi="' . e($row->divisi) . '" data-sub-divisi="' . e($row->sub_divisi) . '" data-faktur="' . e($row->no_faktur) . '" data-desc="' . e($row->desc) . '">
                    <label class="custom-control-label" for="chk_' . $row->id . '"></label>
                </div>';
            })
            ->editColumn('tgl_faktur', function ($row) {
                return $row->tgl_faktur ? Carbon::parse($row->tgl_faktur)->format('d/m/Y') : '-';
            })
            ->editColumn('netto', function ($row) {
                return 'Rp ' . number_format((float) $row->netto, 0, ',', '.');
            })
            ->editColumn('qty', function ($row) {
                return number_format((float) $row->qty, 0, ',', '.');
            })
            ->editColumn('divisi', function ($row) {
                $divisi = strtoupper(trim((string) $row->divisi));
                if ($divisi === 'CMA') {
                    return '<span class="badge badge-success font-weight-bolder px-2 py-1">CMA</span>';
                } elseif ($divisi === 'REGULER') {
                    return '<span class="badge badge-primary font-weight-bolder px-2 py-1">REGULER</span>';
                } elseif ($divisi === 'NON REGULER') {
                    return '<span class="badge badge-info font-weight-bolder px-2 py-1">NON REGULER</span>';
                } elseif ($divisi !== '') {
                    return '<span class="badge badge-dark font-weight-bolder px-2 py-1">' . e($row->divisi) . '</span>';
                }
                return '<span class="badge badge-secondary font-weight-bold px-2 py-1">UNASSIGNED</span>';
            })
            ->editColumn('dist', function ($row) {
                $dist = strtoupper(trim((string) $row->dist));
                $badgeColor = 'secondary';
                if ($dist === 'KFTD') {
                    $badgeColor = 'primary';
                } elseif ($dist === 'AAM') {
                    $badgeColor = 'success';
                } elseif ($dist === 'METD') {
                    $badgeColor = 'warning';
                }
                return '<span class="badge badge-light-' . $badgeColor . ' font-weight-bolder">' . e($row->dist) . '</span>';
            })
            ->addColumn('action', function ($row) {
                $btnEdit = '<button type="button" class="btn btn-xs btn-outline-warning btn-icon btn-edit-single" ' .
                    'data-id="' . $row->id . '" ' .
                    'data-faktur="' . e($row->no_faktur) . '" ' .
                    'data-outlet="' . e($row->nama_outlet ?: $row->id_outlet) . '" ' .
                    'data-code="' . e($row->code_prod_ph ?: $row->code_product) . '" ' .
                    'data-desc="' . e($row->desc) . '" ' .
                    'data-divisi="' . e($row->divisi) . '" ' .
                    'data-sub-divisi="' . e($row->sub_divisi) . '" ' .
                    'data-pilar="' . e($row->pilar_sub) . '" ' .
                    'data-netto="' . number_format((float) $row->netto, 0, ',', '.') . '" ' .
                    'title="Ubah Divisi Baris Ini">' .
                    '<i class="fas fa-edit"></i>' .
                    '</button>';

                return '<div class="text-center text-nowrap">' . $btnEdit . '</div>';
            })
            ->rawColumns(['checkbox', 'divisi', 'dist', 'action'])
            ->make(true);
    }

    /**
     * Hitung statistik dinamis sesuai filter saat ini
     */
    public function stats(Request $request)
    {
        $query = DB::connection(self::CONN_DWH)->table(self::SUMMARY_TABLE . ' as so');

        // Filter Tanggal
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->input('start_date'))->startOfDay()->toDateTimeString();
            $end = Carbon::parse($request->input('end_date'))->endOfDay()->toDateTimeString();
            $query->whereBetween('so.tgl_faktur', [$start, $end]);
        } elseif ($request->filled('bulan') && $request->filled('tahun')) {
            $year = (int) $request->input('tahun');
            $month = (int) $request->input('bulan');
            $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateTimeString();
            $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateTimeString();
            $query->whereBetween('so.tgl_faktur', [$start, $end]);
        }

        // Filter Distributor
        if ($request->filled('dist')) {
            $dist = (array) $request->input('dist');
            if (!empty($dist)) {
                $query->whereIn('so.dist', $dist);
            }
        }

        // Filter Divisi
        if ($request->filled('divisi')) {
            $divisi = $request->input('divisi');
            if ($divisi === 'UNASSIGNED') {
                $query->where(function ($q) {
                    $q->whereNull('so.divisi')->orWhere('so.divisi', '');
                });
            } else {
                $query->where('so.divisi', $divisi);
            }
        }

        // Filter Sub Divisi
        if ($request->filled('sub_divisi')) {
            $subDivisi = $request->input('sub_divisi');
            if ($subDivisi === 'UNASSIGNED') {
                $query->where(function ($q) {
                    $q->whereNull('so.sub_divisi')->orWhere('so.sub_divisi', '');
                });
            } else {
                $query->where('so.sub_divisi', $subDivisi);
            }
        }

        // Filter Pilar
        if ($request->filled('pilar')) {
            $pilar = (array) $request->input('pilar');
            if (!empty($pilar)) {
                $query->whereIn('so.pilar_sub', $pilar);
            }
        }

        // Filter Produk
        if ($request->filled('product_code')) {
            $prodCode = trim((string) $request->input('product_code'));
            $query->where(function ($q) use ($prodCode) {
                $q->where('so.code_prod_ph', $prodCode)
                    ->orWhere('so.code_product', $prodCode);
            });
        }

        $res = $query->selectRaw("
            COUNT(*) as total_count,
            COALESCE(SUM(so.netto), 0) as total_netto,
            SUM(CASE WHEN UPPER(TRIM(so.divisi)) = 'REGULER' THEN 1 ELSE 0 END) as count_reguler,
            SUM(CASE WHEN UPPER(TRIM(so.divisi)) = 'CMA' THEN 1 ELSE 0 END) as count_cma
        ")->first();

        return response()->json([
            'total_count'   => $res ? (int) $res->total_count : 0,
            'total_netto'   => $res ? 'Rp ' . number_format((float) $res->total_netto, 0, ',', '.') : 'Rp 0',
            'count_reguler' => $res ? (int) $res->count_reguler : 0,
            'count_cma'     => $res ? (int) $res->count_cma : 0,
        ]);
    }

    /**
     * Update divisi & sub_divisi untuk 1 baris transaksi tertentu
     */
    public function updateSingle(Request $request, $id)
    {
        $request->validate([
            'divisi'     => 'required|string|max:100',
            'sub_divisi' => 'nullable|string|max:100',
        ]);

        $divisi = trim((string) $request->input('divisi'));
        $subDivisi = $request->filled('sub_divisi') ? trim((string) $request->input('sub_divisi')) : null;

        $updated = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE)
            ->where('id', $id)
            ->update([
                'divisi'     => $divisi,
                'sub_divisi' => $subDivisi,
                'updated_at' => Carbon::now(),
            ]);

        if ($updated) {
            Cache::flush();
            return response()->json([
                'success' => true,
                'message' => "Divisi transaksi sellout berhasil diubah menjadi '{$divisi}'.",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data transaksi tidak ditemukan atau tidak ada perubahan.',
        ], 404);
    }

    /**
     * Update bulk untuk baris-baris yang dipilih via checkbox
     */
    public function updateBulk(Request $request)
    {
        $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'required|integer',
            'divisi'     => 'required|string|max:100',
            'sub_divisi' => 'nullable|string|max:100',
        ]);

        $ids = $request->input('ids');
        $divisi = trim((string) $request->input('divisi'));
        $subDivisi = $request->filled('sub_divisi') ? trim((string) $request->input('sub_divisi')) : null;

        $updated = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE)
            ->whereIn('id', $ids)
            ->update([
                'divisi'     => $divisi,
                'sub_divisi' => $subDivisi,
                'updated_at' => Carbon::now(),
            ]);

        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbarui divisi menjadi '{$divisi}' untuk {$updated} transaksi terpilih.",
            'updated_count' => $updated,
        ]);
    }


    /**
     * Dropdown / Select2 lookup untuk mencari produk
     */
    public function getProductOptions(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        $query = DB::connection(self::CONN_DWH)
            ->table('v_pt_mstr_dist')
            ->select([
                'pt_ph as id',
                DB::raw("CONCAT(pt_ph, ' - ', MIN(pt_desc)) as text"),
                DB::raw("MIN(pt_desc) as product_name")
            ])
            ->groupBy('pt_ph');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('pt_ph', 'like', "%{$search}%")
                    ->orWhere('pt_desc', 'like', "%{$search}%");
            });
        }

        $results = $query->limit(30)->get();

        return response()->json(['results' => $results]);
    }
}
