<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\CrmMstCust;
use App\Models\CrmMstCustCov;
use App\Models\Mapping;
use App\Services\CoverageAccessService;
use App\Services\SelloutQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class OutletController extends Controller
{
    private CoverageAccessService $coverageAccess;

    public function __construct(CoverageAccessService $coverageAccess)
    {
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

    private function dwhQuery(string $table): \Illuminate\Database\Query\Builder
    {
        return DB::connection('db_dwh')->table($table);
    }

    public function index()
    {
        $totals = $this->dwhQuery('crm_mst_cust_cov as cov')
            ->selectRaw('
                COUNT(*) as total_data,
                SUM(CASE WHEN cov.id_coverage IS NOT NULL AND cov.id_coverage != "" THEN 1 ELSE 0 END) as total_tercoverage,
                SUM(CASE WHEN cov.id_coverage IS NULL OR cov.id_coverage = "" THEN 1 ELSE 0 END) as total_belum_coverage,
                0 as total_butuh_pengganti
            ')
            ->first();

        $totalData = (int) ($totals->total_data ?? 0);
        $totalTercoverage = (int) ($totals->total_tercoverage ?? 0);
        $totalBelumCoverage = (int) ($totals->total_belum_coverage ?? 0);
        $totalButuhPengganti = (int) ($totals->total_butuh_pengganti ?? 0);

        $coverage = Mapping::active()
            ->with('user:nik,name')
            ->get(['id', 'id_coverage', 'cov_desc', 'cov_nik', 'cov_level', 'cov_pilar']);
        $allCoverage = $coverage;

        return view('page.outlet.index', compact('totalData', 'totalTercoverage', 'totalBelumCoverage', 'totalButuhPengganti', 'coverage', 'allCoverage'));
    }

    public function data(Request $request)
    {
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        if (mb_strlen($search) === 1) {
            $request->merge([
                'search' => array_replace((array) $request->input('search', []), ['value' => '']),
            ]);
        }

        $latestMappings = $this->dwhQuery('crm_mst_mappings')
            ->selectRaw('MAX(id) as id, id_coverage')
            ->whereNull('deleted_at')
            ->groupBy('id_coverage');

        $query = $this->dwhQuery('crm_mst_cust_cov as cov')
            ->leftJoin($this->dwhTable('crm_mst_cust') . ' as master', 'cov.id_konversi', '=', 'master.id_konversi')
            ->leftJoinSub($latestMappings, 'latest_mappings', function ($join) {
                $join->on('cov.id_coverage', '=', 'latest_mappings.id_coverage');
            })
            ->leftJoin($this->dwhTable('crm_mst_mappings') . ' as mapping', 'latest_mappings.id', '=', 'mapping.id')
            ->leftJoin(DB::raw($this->authTable('users') . ' as users'), 'mapping.cov_nik', '=', 'users.nik')
            ->select([
                'cov.id',
                'cov.id_konversi as id_outlet',
                'cov.id_coverage',
                DB::raw("'-' as dist"),
                'master.nama_outlet',
                DB::raw("'-' as id_cbg_dist"),
                'master.segment',
                'cov.pilar as pilar',
                'mapping.cov_nik',
                'mapping.id as mapping_id',
                'mapping.valid_to',
                DB::raw('COALESCE(mapping.is_active, 0) as mapping_is_active'),
                'users.name as user_name',
            ]);

        $this->coverageAccess->applyScope($query, 'cov.id_coverage');

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

                $needsReplacement = empty($row->mapping_id)
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
                        $q->whereNull('cov.id_coverage')
                            ->orWhere('cov.id_coverage', '');
                    });
                    return;
                }

                if (str_contains('butuh pengganti', $keyword) || str_contains('pengganti', $keyword)) {
                    $query->where(function ($q) {
                        $q->whereNotNull('cov.id_coverage')
                            ->where('cov.id_coverage', '!=', '')
                            ->where(function ($qq) {
                                $qq->whereNull('mapping.id')
                                    ->orWhere('mapping.is_active', false)
                                    ->orWhereNotNull('mapping.valid_to');
                            });
                    });
                    return;
                }

                if (str_contains('ok', $keyword)) {
                    $query->whereNotNull('cov.id_coverage')
                        ->where('cov.id_coverage', '!=', '')
                        ->whereNotNull('mapping.id')
                        ->where('mapping.is_active', true)
                        ->whereNull('mapping.valid_to');
                }
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderByRaw("(CASE WHEN cov.id_coverage IS NULL OR cov.id_coverage = '' THEN 0 WHEN mapping.id IS NULL OR COALESCE(mapping.is_active, 0) = 0 OR mapping.valid_to IS NOT NULL THEN 1 ELSE 2 END) {$order}");
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

    // Ambil master outlet untuk dropdown/search
    public function getOutlet(Request $request)
    {
        $search = $request->get('q', '');
        $outlets = CrmMstCust::query()
            ->where(function ($q) use ($search) {
                $q->where('id_konversi', 'like', "%$search%")
                    ->orWhere('nama_outlet', 'like', "%$search%");
            })
            ->selectRaw("id_konversi as id, CONCAT(id_konversi, ' - ', nama_outlet) as text")
            ->limit(30)
            ->get();

        return response()->json(['results' => $outlets]);
    }

    public function getCoverage(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        $query = $this->dwhQuery('crm_mst_mappings')
            ->leftJoin(DB::raw($this->authTable('users') . ' as users'), 'crm_mst_mappings.cov_nik', '=', 'users.nik')
            ->where('crm_mst_mappings.is_active', true)
            ->whereNull('crm_mst_mappings.deleted_at');

        if (!$request->boolean('all_levels')) {
            $query->where('crm_mst_mappings.cov_level', 5);
        }

        $excludePrefix = trim((string) $request->get('exclude_prefix', ''));
        if ($excludePrefix !== '') {
            $query->where('crm_mst_mappings.id_coverage', 'not like', $excludePrefix . '%');
        }

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
                DB::raw('crm_mst_mappings.cov_nik as cov_nik'),
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

        $pilar = $request->input('pilar', 'ETHICAL');

        $exists = CrmMstCustCov::where('id_konversi', $request->id_outlet)
            ->where('pilar', $pilar)
            ->where('id_coverage', $request->id_coverage)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Outlet ini sudah di-mapping ke coverage tersebut.'], 422);
        }

        CrmMstCustCov::updateOrCreate(
            [
                'id_konversi' => $request->id_outlet,
                'pilar'       => $pilar,
            ],
            [
                'id_coverage' => $request->id_coverage,
            ]
        );

        Cache::flush();

        return response()->json(['success' => true, 'message' => 'Mapping berhasil ditambahkan.']);
    }

    public function show($id)
    {
        $cov = CrmMstCustCov::with([
            'master:id_konversi,nama_outlet,segment,kota',
            'mapping:id_coverage,cov_desc,cov_nik,cov_level,cov_pilar',
            'mapping.user:nik,name',
        ])->findOrFail($id);

        return response()->json([
            'id'          => $cov->id,
            'id_outlet'   => $cov->id_konversi,
            'id_coverage' => $cov->id_coverage,
            'pilar'       => $cov->pilar,
            'nama_outlet' => $cov->master ? $cov->master->nama_outlet : null,
            'outlet_text' => $cov->master
                ? $cov->id_konversi . ' - ' . ($cov->master->nama_outlet ?? '')
                : $cov->id_konversi,
            'master'      => $cov->master,
            'coverage'    => $cov->mapping,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'id_coverage' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
        ]);

        $cov = CrmMstCustCov::findOrFail($id);
        $cov->update([
            'id_coverage' => $request->id_coverage,
            'pilar'       => $request->input('pilar', $cov->pilar),
        ]);

        Cache::flush();

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
            $updatedCount = CrmMstCustCov::where('id_coverage', $request->old_coverage_id)
                ->whereIn('id', $request->mapping_ids)
                ->update([
                    'id_coverage' => $request->new_coverage_id,
                ]);

            DB::connection('db_dwh')->commit();
            Cache::flush();

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

        $outlets = CrmMstCustCov::where('id_coverage', $coverageId)
            ->select(['id', 'id_konversi', 'pilar', 'id_coverage'])
            ->limit(500)
            ->get();

        return response()->json($outlets);
    }

    public function destroy($id)
    {
        $cov = CrmMstCustCov::findOrFail($id);
        $cov->delete();
        Cache::flush();

        return response()->json(['success' => true, 'message' => 'Mapping berhasil dihapus.']);
    }
}
