<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\Mapping;
use App\Models\MCustDist;
use App\Models\Outlet;
use App\Services\CoverageAccessService;
use App\Services\SelloutQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class OutletController extends Controller
{
    private SelloutQueryService $sellout;
    private CoverageAccessService $coverageAccess;

    public function __construct(SelloutQueryService $sellout, CoverageAccessService $coverageAccess)
    {
        $this->sellout = $sellout;
        $this->coverageAccess = $coverageAccess;
    }

    private function dwhTable(string $table): string
    {
        return DB::connection('db_dwh')->getDatabaseName() . '.' . $table;
    }

    private function authTable(string $table): string
    {
        return DB::connection('mysql')->getDatabaseName() . '.' . $table;
    }

    public function index()
    {
        // $user = auth()->user();
        $outletsTable = DB::raw($this->dwhTable('crm_mst_outlets') . ' as crm_mst_outlets');
        $joinActiveOutlets = function ($join) {
            $join->on('mst_cust_dist.id_outlet', '=', 'crm_mst_outlets.id_outlet')
                ->whereNull('crm_mst_outlets.deleted_at');
        };

        $totalData = MCustDist::query()->leftJoin($outletsTable, $joinActiveOutlets)->count();
        $totalTercoverage = MCustDist::query()->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as crm_mst_outlets'), $joinActiveOutlets)->whereNotNull('crm_mst_outlets.id_coverage')->count();
        $totalBelumCoverage = MCustDist::query()->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as crm_mst_outlets'), $joinActiveOutlets)->whereNull('crm_mst_outlets.id_coverage')->count();
        $totalButuhPengganti = MCustDist::query()->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as crm_mst_outlets'), $joinActiveOutlets)->where('crm_mst_outlets.needs_replacement', 1)->count();
        // $newData = Outlet::where('created_at', '>=', now()->subDays(7))->count();
        // $updatedToday = Outlet::whereDate('updated_at', today())->count();
        $coverage = Mapping::active()
            ->with('user:nik,name')
            ->get(['id', 'id_coverage', 'cov_desc', 'cov_nik', 'cov_level', 'cov_pilar']);
        // $coverage = $this->coverageAccess->filterCoverageCollection($coverage, $user);
        $allCoverage = $coverage;

        return view('page.outlet.index', compact('totalData', 'totalTercoverage', 'totalBelumCoverage', 'totalButuhPengganti', 'coverage', 'allCoverage'));
    }

    public function history()
    {
        $now = now();
        $user = auth()->user();

        $years = collect(range($now->year - 5, $now->year))
            ->reverse()
            ->values()
            ->all();

        $months = collect(range(1, 12))
            ->mapWithKeys(fn($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])
            ->toArray();

        return view('page.outlet.history', compact('years', 'months') + [
            'currentMonth' => (int) $now->month,
            'currentYear' => (int) $now->year,
        ]);
    }

    public function data(Request $request)
    {
        $latestMappings = DB::connection('db_dwh')
            ->table('crm_mst_mappings')
            ->selectRaw('MAX(id) as id, id_coverage')
            ->whereNull('deleted_at')
            ->groupBy('id_coverage');

        $query = MCustDist::where('dist', '!=', 'Nusindo')
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as crm_mst_outlets'), function ($join) {
                $join->on('mst_cust_dist.id_outlet', '=', 'crm_mst_outlets.id_outlet')
                    ->whereNull('crm_mst_outlets.deleted_at');
            })
            ->leftJoinSub($latestMappings, 'latest_mappings', function ($join) {
                $join->on('crm_mst_outlets.id_coverage', '=', 'latest_mappings.id_coverage');
            })
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_mappings') . ' as crm_mst_mappings'), function ($join) {
                $join->on('latest_mappings.id', '=', 'crm_mst_mappings.id');
            })
            ->leftJoin(DB::raw($this->authTable('users') . ' as users'), 'crm_mst_mappings.cov_nik', '=', 'users.nik')
            ->select([
                'crm_mst_outlets.id',
                'mst_cust_dist.id_outlet',
                'crm_mst_outlets.id_coverage',
                'crm_mst_outlets.needs_replacement',
                'mst_cust_dist.dist',
                'mst_cust_dist.nama_outlet',
                'mst_cust_dist.id_cbg_dist',
                // 'mst_cust_dist.nama_cabang',
                // 'mst_cust_dist.kota',
                'mst_cust_dist.segment',
                'crm_mst_mappings.cov_pilar as pilar',
                'crm_mst_mappings.cov_nik',
                'crm_mst_mappings.id as mapping_id',
                'crm_mst_mappings.valid_to',
                DB::raw('COALESCE(crm_mst_mappings.is_active, 0) as mapping_is_active'),
                'users.name as user_name',
            ]);

        $this->coverageAccess->applyScope($query, 'crm_mst_outlets.id_coverage');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('dist', function ($row) {
                return $row->dist ?? '-';
            })
            ->editColumn('nama_outlet', function ($row) {
                return $row->nama_outlet ?? '-';
            })
            ->editColumn('kode_cabang', function ($row) {
                return $row->id_cbg_dist ?? '-';
            })
            // ->editColumn('nama_cabang', function ($row) {
            //     return $row->nama_cabang ?? '-';
            // })
            ->editColumn('kota', function ($row) {
                return $row->kota ?? '-';
            })
            ->editColumn('segment', function ($row) {
                return $row->segment ?? '-';
            })
            ->editColumn('pilar', function ($row) {
                return $row->pilar ?? '-';
            })
            ->editColumn('cov_nik', function ($row) {
                return $row->cov_nik ?? '-';
            })
            ->editColumn('user_name', function ($row) {
                return $row->user_name ?? '-';
            })
            ->addColumn('status', function ($row) {
                if (empty($row->id_coverage)) {
                    return '<span class="badge badge-warning text-white">Unmapped</span>';
                }

                $needsReplacement = (bool) $row->needs_replacement
                    || empty($row->mapping_id)
                    || !(bool) $row->mapping_is_active
                    || !empty($row->valid_to);

                return $needsReplacement
                    ? '<span class="badge badge-info">Butuh Pengganti</span>'
                    : '<span class="badge badge-success">OK</span>';
            })
            ->filterColumn('status', function ($query, $keyword) {
                $keyword = strtolower($keyword);

                if (str_contains('unmapped', $keyword) || str_contains('belum', $keyword)) {
                    $query->where(function ($q) {
                        $q->whereNull('crm_mst_outlets.id_coverage')
                            ->orWhere('crm_mst_outlets.id_coverage', '');
                    });
                    return;
                }

                if (str_contains('butuh pengganti', $keyword) || str_contains('pengganti', $keyword)) {
                    $query->where(function ($q) {
                        $q->whereNotNull('crm_mst_outlets.id_coverage')
                            ->where('crm_mst_outlets.id_coverage', '!=', '')
                            ->where(function ($qq) {
                                $qq->where('crm_mst_outlets.needs_replacement', true)
                                    ->orWhereNull('crm_mst_mappings.id')
                                    ->orWhere('crm_mst_mappings.is_active', false)
                                    ->orWhereNotNull('crm_mst_mappings.valid_to');
                            });
                    });
                    return;
                }

                if (str_contains('ok', $keyword)) {
                    $query->whereNotNull('crm_mst_outlets.id_coverage')
                        ->where('crm_mst_outlets.id_coverage', '!=', '')
                        ->where('crm_mst_outlets.needs_replacement', false)
                        ->whereNotNull('crm_mst_mappings.id')
                        ->where('crm_mst_mappings.is_active', true)
                        ->whereNull('crm_mst_mappings.valid_to');
                }
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderByRaw("(CASE WHEN crm_mst_outlets.id_coverage IS NULL OR crm_mst_outlets.id_coverage = '' THEN 0 WHEN crm_mst_outlets.needs_replacement = 1 OR crm_mst_mappings.id IS NULL OR COALESCE(crm_mst_mappings.is_active, 0) = 0 OR crm_mst_mappings.valid_to IS NOT NULL THEN 1 ELSE 2 END) {$order}");
            })
            ->addColumn('action', function ($row) {
                if (empty($row->id)) {
                    return '-';
                }

                $edit = '<button class="btn btn-sm btn-outline-warning btn-icon mr-2 btn-edit" data-id="' . $row->id . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $delete = '<button class="btn btn-sm btn-outline-danger btn-icon btn-delete" data-id="' . $row->id . '" data-url="' . route('outlet.destroy', $row->id) . '">
                               <i class="fas fa-trash"></i>
                           </button>';

                return '<div class="d-flex flex-nowrap justify-content-center align-items-center action-buttons">'
                    . $edit . $delete .
                    '</div>';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function historyData(DashboardFilterRequest $request)
    {
        $base = $this->sellout->outletHistoryQuery($request)
            ->selectRaw('
                so.dist,
                so.id_outlet,
                so.outlet_name,
                so.coverage_id,
                so.coverage_desc,
                so.coverage_nik,
                so.user_name,
                so.coverage_level,
                so.coverage_pilar,
                so.product_pilar,
                so.coverage_valid_from,
                so.coverage_valid_to,
                MIN(so.tgl_faktur) as bulan_date,
                COUNT(so.source_id) as total_transaksi,
                SUM(so.netto) as total_netto
            ')
            ->groupBy(
                'so.dist',
                'so.id_outlet',
                'so.outlet_name',
                'so.coverage_id',
                'so.coverage_desc',
                'so.coverage_nik',
                'so.user_name',
                'so.coverage_level',
                'so.coverage_pilar',
                'so.product_pilar',
                'so.coverage_valid_from',
                'so.coverage_valid_to',
                DB::raw('YEAR(so.tgl_faktur)'),
                DB::raw('MONTH(so.tgl_faktur)')
            );

        $datatable = DataTables::of($base)
            ->filter(function ($query) use ($request) {
                $search = trim((string) data_get($request->input('search'), 'value', ''));

                if ($search === '') {
                    return;
                }

                $query->where(function ($q) use ($search) {
                    $like = '%' . $search . '%';

                    $q->where('so.dist', 'like', $like)
                        ->orWhere('so.id_outlet', 'like', $like)
                        ->orWhere('so.outlet_name', 'like', $like)
                        ->orWhere('so.coverage_nik', 'like', $like)
                        ->orWhere('so.user_name', 'like', $like)
                        ->orWhere('so.product_pilar', 'like', $like)
                        ->orWhereRaw("(CASE so.coverage_level WHEN 1 THEN 'GM' WHEN 2 THEN 'RM' WHEN 3 THEN 'SAM' WHEN 4 THEN 'AM' WHEN 5 THEN 'MR' ELSE 'Unmapped' END) like ?", [$like]);
                });
            }, true)
            ->orderColumn('dist', 'so.dist $1')
            ->orderColumn('id_outlet', 'so.id_outlet $1')
            ->orderColumn('outlet_name', 'so.outlet_name $1')
            ->orderColumn('coverage_id', 'so.coverage_id $1')
            ->orderColumn('coverage_desc', 'so.coverage_desc $1')
            ->orderColumn('coverage_nik', 'so.coverage_nik $1')
            ->orderColumn('user_name', 'so.user_name $1')
            ->orderColumn('coverage_level', 'so.coverage_level $1')
            ->orderColumn('product_pilar', 'so.product_pilar $1')
            ->orderColumn('bulan', 'MIN(so.tgl_faktur) $1')
            ->orderColumn('status_label', function ($query, $order) {
                $query->orderByRaw("(CASE WHEN so.coverage_id IS NULL OR so.coverage_nik IS NULL OR so.coverage_nik = '' THEN 0 WHEN so.coverage_valid_to IS NULL OR so.coverage_valid_to >= CURDATE() THEN 2 ELSE 1 END) {$order}");
            })
            ->orderColumn('total_transaksi', 'COUNT(so.source_id) $1')
            ->orderColumn('total_netto', 'SUM(so.netto) $1')
            ->addColumn('bulan', function ($row) {
                $months = [
                    1 => 'Januari',
                    2 => 'Februari',
                    3 => 'Maret',
                    4 => 'April',
                    5 => 'Mei',
                    6 => 'Juni',
                    7 => 'Juli',
                    8 => 'Agustus',
                    9 => 'September',
                    10 => 'Oktober',
                    11 => 'November',
                    12 => 'Desember',
                ];

                $date = \Carbon\Carbon::parse($row->bulan_date);

                return ($months[(int) $date->month] ?? $date->format('F')) . ' ' . $date->year;
            })
            ->editColumn('coverage_nik', function ($row) {
                return empty($row->coverage_nik) || $row->coverage_nik === '-' ? 'Unmapped' : $row->coverage_nik;
            })
            ->editColumn('coverage_level', function ($row) {
                return $this->sellout->numToLevel($row->coverage_level);
            })
            ->editColumn('status_label', function ($row) {
                if (empty($row->coverage_id) || empty($row->coverage_nik) || $row->coverage_nik === '-') {
                    return 'Unmapped';
                }

                $isExpired = $row->coverage_valid_to && \Carbon\Carbon::parse($row->coverage_valid_to)->lt(now());

                return $isExpired ? 'Non-aktif' : 'Aktif';
            })
            ->addColumn('status_class', function ($row) {
                if (empty($row->coverage_id) || empty($row->coverage_nik) || $row->coverage_nik === '-') {
                    return 'badge-warning text-white';
                }

                $isExpired = $row->coverage_valid_to && \Carbon\Carbon::parse($row->coverage_valid_to)->lt(now());

                return $isExpired ? 'badge-danger' : 'badge-success';
            })
            ->editColumn('user_name', function ($row) {
                if (empty($row->coverage_nik) || $row->coverage_nik === '-') {
                    return '<span class="font-size-sm">Unmapped</span>';
                }

                return $row->user_name ?: '-';
            })
            ->rawColumns(['user_name', 'status_label'])
            ->addIndexColumn();

        return $datatable->make(true);
    }

    // Ambil outlet dari mst_cust_dist (HRMS) untuk dropdown/search
    public function getOutlet(Request $request)
    {
        $search = $request->get('q', '');
        $outlets = MCustDist::query()
            ->where(function ($q) use ($search) {
                $q->where('id_outlet', 'like', "%$search%")
                    ->orWhere('nama_outlet', 'like', "%$search%");
            })
            ->selectRaw("id_outlet as id, CONCAT(id_outlet, ' - ', MIN(nama_outlet)) as text")
            ->groupBy('id_outlet')
            ->limit(30)
            ->get();

        return response()->json(['results' => $outlets]);
    }

    public function getCoverage(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        $query = DB::connection('db_dwh')
            ->table('crm_mst_mappings')
            ->leftJoin(DB::raw($this->authTable('users') . ' as users'), 'crm_mst_mappings.cov_nik', '=', 'users.nik')
            ->where('crm_mst_mappings.is_active', true)
            ->where('crm_mst_mappings.cov_level', 5)
            ->whereNull('crm_mst_mappings.deleted_at');

        $this->coverageAccess->applyScope($query, 'crm_mst_mappings.id_coverage');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where('crm_mst_mappings.id_coverage', 'like', $like)
                    ->orWhere('crm_mst_mappings.cov_nik', 'like', $like)
                    ->orWhere('crm_mst_mappings.cov_desc', 'like', $like)
                    ->orWhere('users.name', 'like', $like);
            });
        }

        $coverage = $query
            ->orderBy('crm_mst_mappings.id_coverage')
            ->limit(30)
            ->get([
                DB::raw('crm_mst_mappings.id_coverage as id'),
                DB::raw("CONCAT(crm_mst_mappings.id_coverage, ' - ', crm_mst_mappings.cov_nik, IF(users.name IS NULL OR users.name = '', '', CONCAT(' - ', users.name))) as text"),
            ]);

        return response()->json(['results' => $coverage]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_outlet'   => 'required|string',
            'id_coverage' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
        ]);

        $mapping = Outlet::withTrashed()
            ->where('id_outlet', $request->id_outlet)
            ->where('id_coverage', $request->id_coverage)
            ->first();

        if ($mapping && ! $mapping->trashed()) {
            return response()->json(['success' => false, 'message' => 'Outlet ini sudah di-mapping ke coverage tersebut.'], 422);
        }

        if ($mapping && $mapping->trashed()) {
            $mapping->restore();
            $mapping->update([
                'needs_replacement' => false,
            ]);

            return response()->json(['success' => true, 'message' => 'Mapping berhasil ditambahkan kembali.']);
        }

        Outlet::create([
            'id_outlet'         => $request->id_outlet,
            'id_coverage'       => $request->id_coverage,
            'needs_replacement' => false,
        ]);

        return response()->json(['success' => true, 'message' => 'Mapping berhasil ditambahkan.']);
    }

    public function show($id)
    {
        $mapping = Outlet::with([
            'custDist:id_outlet,nama_outlet',
            'coverage:id_coverage,cov_desc,cov_nik,cov_level,cov_pilar',
            'coverage.user:nik,name',
        ])->findOrFail($id);

        return response()->json([
            'id' => $mapping->id,
            'id_outlet' => $mapping->id_outlet,
            'id_coverage' => $mapping->id_coverage,
            'needs_replacement' => $mapping->needs_replacement,
            'nama_outlet' => $mapping->custDist ? $mapping->custDist->nama_outlet : null,
            'outlet_text' => $mapping->custDist
                ? $mapping->id_outlet . ' - ' . ($mapping->custDist->nama_outlet ?? '')
                : $mapping->id_outlet,
            'cust_dist' => $mapping->custDist,
            'coverage' => $mapping->coverage,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'id_outlet'   => 'required|string',
            'id_coverage' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
        ]);

        $mapping = Outlet::findOrFail($id);
        $mapping->update([
            'id_outlet'         => $request->id_outlet,
            'id_coverage'       => $request->id_coverage,
            'needs_replacement' => false, // reset flag saat diupdate
        ]);

        return response()->json(['success' => true, 'message' => 'Mapping berhasil diupdate.']);
    }

    // Bulk update coverage (saat mutasi)
    public function bulkUpdateCoverage(Request $request)
    {
        $request->validate([
            'old_coverage_id' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
            'new_coverage_id' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage|different:old_coverage_id',
            'mapping_ids'     => 'required|array|min:1',
            'mapping_ids.*'   => 'required|integer',
        ]);

        DB::connection('db_dwh')->beginTransaction();
        try {
            $updatedCount = Outlet::where('id_coverage', $request->old_coverage_id)
                ->whereIn('id', $request->mapping_ids)
                ->update([
                    'id_coverage'       => $request->new_coverage_id,
                    'needs_replacement' => false,
                ]);

            DB::connection('db_dwh')->commit();

            if ($updatedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada outlet yang cocok dengan coverage lama yang dipilih.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $updatedCount . ' outlet berhasil dipindahkan ke coverage baru.'
            ]);
        } catch (\Exception $e) {
            DB::connection('db_dwh')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Ambil outlet yang terdampak dari 1 coverage (untuk bulk update)
    public function getOutletByCoverage($coverageId)
    {
        if (!$this->coverageAccess->canAccessCoverage(auth()->user(), $coverageId)) {
            return response()->json([]);
        }

        $outlets = Outlet::where('id_coverage', $coverageId)
            ->select(['id', 'id_outlet', 'needs_replacement'])
            ->limit(500) // guard agar tidak load ribuan outlet sekaligus
            ->get();

        return response()->json($outlets);
    }

    public function destroy($id)
    {
        $mapping = Outlet::withTrashed()->findOrFail($id);

        if ($mapping->trashed()) {
            throw ValidationException::withMessages([
                'id' => 'Mapping ini sudah dihapus.',
            ]);
        }

        $mapping->delete();

        return response()->json(['success' => true, 'message' => 'Mapping berhasil dihapus.']);
    }
}
