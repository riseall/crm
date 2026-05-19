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

    public function index()
    {
        // $user = auth()->user();
        $totalData = MCustDist::query()->leftJoin('mst_outlets', 'mst_cust_dist.id_outlet', '=', 'mst_outlets.id_outlet')->count();
        $totalTercoverage = MCustDist::query()->leftJoin('mst_outlets', 'mst_cust_dist.id_outlet', '=', 'mst_outlets.id_outlet')->whereNotNull('mst_outlets.id_coverage')->count();
        $totalBelumCoverage = MCustDist::query()->leftJoin('mst_outlets', 'mst_cust_dist.id_outlet', '=', 'mst_outlets.id_outlet')->whereNull('mst_outlets.id_coverage')->count();
        $totalButuhPengganti = MCustDist::query()->leftJoin('mst_outlets', 'mst_cust_dist.id_outlet', '=', 'mst_outlets.id_outlet')->where('mst_outlets.needs_replacement', 1)->count();
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

        $coverageList = Mapping::active()
            ->with('user:nik,name')
            ->orderBy('id_coverage')
            ->get(['id', 'id_coverage', 'cov_desc', 'cov_nik', 'cov_level', 'cov_pilar']);
        $coverageList = $this->coverageAccess->filterCoverageCollection($coverageList, $user);

        return view('page.outlet.history', compact('years', 'months', 'coverageList') + [
            'currentMonth' => (int) $now->month,
            'currentYear' => (int) $now->year,
        ]);
    }

    public function data(Request $request)
    {
        $latestMappings = DB::table('mst_mappings')
            ->selectRaw('MAX(id) as id, id_coverage')
            ->whereNull('deleted_at')
            ->groupBy('id_coverage');

        $query = MCustDist::where('dist', '!=', 'Nusindo')
            ->leftJoin('mst_outlets', 'mst_cust_dist.id_outlet', '=', 'mst_outlets.id_outlet')
            ->leftJoinSub($latestMappings, 'latest_mappings', function ($join) {
                $join->on('mst_outlets.id_coverage', '=', 'latest_mappings.id_coverage');
            })
            ->leftJoin('mst_mappings', function ($join) {
                $join->on('latest_mappings.id', '=', 'mst_mappings.id');
            })
            ->leftJoin('users', 'mst_mappings.cov_nik', '=', 'users.nik')
            ->select([
                'mst_outlets.id',
                'mst_cust_dist.id_outlet',
                'mst_outlets.id_coverage',
                'mst_outlets.needs_replacement',
                'mst_cust_dist.dist',
                'mst_cust_dist.nama_outlet',
                'mst_cust_dist.id_cbg_dist',
                // 'mst_cust_dist.nama_cabang',
                // 'mst_cust_dist.kota',
                'mst_cust_dist.segment',
                'mst_mappings.cov_pilar as pilar',
                'mst_mappings.cov_nik',
                'mst_mappings.id as mapping_id',
                'mst_mappings.valid_to',
                DB::raw('COALESCE(mst_mappings.is_active, 0) as mapping_is_active'),
                'users.name as user_name',
            ]);

        $this->coverageAccess->applyScope($query, 'mst_outlets.id_coverage');

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
                        $q->whereNull('mst_outlets.id_coverage')
                            ->orWhere('mst_outlets.id_coverage', '');
                    });
                    return;
                }

                if (str_contains('butuh pengganti', $keyword) || str_contains('pengganti', $keyword)) {
                    $query->where(function ($q) {
                        $q->whereNotNull('mst_outlets.id_coverage')
                            ->where('mst_outlets.id_coverage', '!=', '')
                            ->where(function ($qq) {
                                $qq->where('mst_outlets.needs_replacement', true)
                                    ->orWhereNull('mst_mappings.id')
                                    ->orWhere('mst_mappings.is_active', false)
                                    ->orWhereNotNull('mst_mappings.valid_to');
                            });
                    });
                    return;
                }

                if (str_contains('ok', $keyword)) {
                    $query->whereNotNull('mst_outlets.id_coverage')
                        ->where('mst_outlets.id_coverage', '!=', '')
                        ->where('mst_outlets.needs_replacement', false)
                        ->whereNotNull('mst_mappings.id')
                        ->where('mst_mappings.is_active', true)
                        ->whereNull('mst_mappings.valid_to');
                }
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderByRaw("(CASE WHEN mst_outlets.id_coverage IS NULL OR mst_outlets.id_coverage = '' THEN 0 WHEN mst_outlets.needs_replacement = 1 OR mst_mappings.id IS NULL OR COALESCE(mst_mappings.is_active, 0) = 0 OR mst_mappings.valid_to IS NOT NULL THEN 1 ELSE 2 END) {$order}");
            })
            ->addColumn('action', function ($row) {
                if (empty($row->id)) {
                    return '-';
                }

                $edit = '<button class="btn btn-sm btn-outline-warning btn-icon mr-2 btn-edit" data-id="' . $row->id . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $delete = '<button class="btn btn-sm btn-outline-danger btn-icon btn-delete" data-id="' . $row->id . '">
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
        $base = $this->sellout->outletHistoryQuery($request);

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
                        ->orWhere('vcd.nama_outlet', 'like', $like)
                        ->orWhere('mo.id_coverage', 'like', $like)
                        ->orWhere('mc.cov_desc', 'like', $like)
                        ->orWhere('mc.cov_nik', 'like', $like)
                        ->orWhere('ma.name', 'like', $like)
                        ->orWhere('vp.pilar', 'like', $like)
                        ->orWhere('mc.cov_pilar', 'like', $like)
                        ->orWhere('mc.valid_from', 'like', $like)
                        ->orWhere('mc.valid_to', 'like', $like)
                        ->orWhereRaw("DATE_FORMAT(so.tgl_faktur, '%M %Y') like ?", [$like])
                        ->orWhereRaw("DATE_FORMAT(mc.valid_from, '%Y-%m-%d') like ?", [$like])
                        ->orWhereRaw("DATE_FORMAT(mc.valid_to, '%Y-%m-%d') like ?", [$like])
                        ->orWhereRaw("(CASE WHEN mo.id_coverage IS NULL OR mc.cov_nik IS NULL OR mc.cov_nik = '' THEN 'Unmapped' WHEN mc.valid_to IS NULL OR mc.valid_to >= CURDATE() THEN 'Aktif' ELSE 'Non-aktif' END) like ?", [$like])
                        ->orWhereRaw("(CASE mc.cov_level WHEN 1 THEN 'GM' WHEN 2 THEN 'RM' WHEN 3 THEN 'SAM' WHEN 4 THEN 'AM' WHEN 5 THEN 'MR' ELSE 'Unmapped' END) like ?", [$like]);
                });
            }, true)
            ->orderColumn('dist', 'so.dist $1')
            ->orderColumn('id_outlet', 'so.id_outlet $1')
            ->orderColumn('nama_outlet', 'vcd.nama_outlet $1')
            ->orderColumn('id_coverage', 'mo.id_coverage $1')
            ->orderColumn('cov_desc', 'mc.cov_desc $1')
            ->orderColumn('cov_nik', 'mc.cov_nik $1')
            ->orderColumn('user_name', 'ma.name $1')
            ->orderColumn('level_name', 'mc.cov_level $1')
            ->orderColumn('pilar', 'COALESCE(mc.cov_pilar, vp.pilar) $1')
            ->orderColumn('valid_from', 'mc.valid_from $1')
            ->orderColumn('valid_to', 'mc.valid_to $1')
            ->orderColumn('status_label', function ($query, $order) {
                $query->orderByRaw("(CASE WHEN mo.id_coverage IS NULL OR mc.cov_nik IS NULL OR mc.cov_nik = '' THEN 0 WHEN mc.valid_to IS NULL OR mc.valid_to >= CURDATE() THEN 2 ELSE 1 END) {$order}");
            })
            ->orderColumn('total_transaksi', 'COUNT(so.id) $1')
            ->orderColumn('total_netto', 'SUM(so.netto) $1')
            ->editColumn('valid_to', function ($row) {
                return $row->valid_to ?: null;
            })
            ->editColumn('level_name', function ($row) {
                return $this->sellout->numToLevel($row->cov_level);
            })
            ->editColumn('status_label', function ($row) {
                if (empty($row->id_coverage) || empty($row->cov_nik) || $row->cov_nik === '-') {
                    return 'Unmapped';
                }

                $isExpired = $row->valid_to && \Carbon\Carbon::parse($row->valid_to)->lt(now());

                return $isExpired ? 'Non-aktif' : 'Aktif';
            })
            ->addColumn('status_class', function ($row) {
                if (empty($row->id_coverage) || empty($row->cov_nik) || $row->cov_nik === '-') {
                    return 'badge-warning text-white';
                }

                $isExpired = $row->valid_to && \Carbon\Carbon::parse($row->valid_to)->lt(now());

                return $isExpired ? 'badge-danger' : 'badge-success';
            })
            ->editColumn('user_name', function ($row) {
                if (empty($row->cov_nik) || $row->cov_nik === '-') {
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

    public function store(Request $request)
    {
        $request->validate([
            'id_outlet'   => 'required|string',
            'id_coverage' => 'required|string|exists:mst_mappings,id_coverage',
        ]);

        // Cek duplikat
        $exists = Outlet::where('id_outlet', $request->id_outlet)
            ->where('id_coverage', $request->id_coverage)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Outlet ini sudah di-mapping ke coverage tersebut.'], 422);
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
            'id_coverage' => 'required|string|exists:mst_mappings,id_coverage',
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
            'old_coverage_id' => 'required|string|exists:mst_mappings,id_coverage',
            'new_coverage_id' => 'required|string|exists:mst_mappings,id_coverage|different:old_coverage_id',
            'mapping_ids'     => 'required|array|min:1',
            'mapping_ids.*'   => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            $updatedCount = Outlet::where('id_coverage', $request->old_coverage_id)
                ->whereIn('id', $request->mapping_ids)
                ->update([
                    'id_coverage'       => $request->new_coverage_id,
                    'needs_replacement' => false,
                ]);

            DB::commit();

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
            DB::rollBack();
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
        Outlet::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Mapping berhasil dihapus.']);
    }
}
