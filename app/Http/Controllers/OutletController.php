<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\CrmMstCust;
use App\Models\CrmMstCustCov;
use App\Models\CrmMstCustDist;
use App\Models\Mapping;
use App\Services\CoverageAccessService;
use App\Services\SelloutQueryService;
use App\Services\UnmappedSelloutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class OutletController extends Controller
{
    private CoverageAccessService $coverageAccess;
    private UnmappedSelloutService $unmappedService;

    public function __construct(
        CoverageAccessService $coverageAccess,
        UnmappedSelloutService $unmappedService
    ) {
        $this->coverageAccess = $coverageAccess;
        $this->unmappedService = $unmappedService;
    }

    private function ensureSoftDeletesColumnExists(): void
    {
        Cache::remember('crm_mst_cust_cov_has_deleted_at', 86400, function () {
            try {
                if (Schema::connection('db_dwh')->hasTable('crm_mst_cust_cov')) {
                    if (!Schema::connection('db_dwh')->hasColumn('crm_mst_cust_cov', 'deleted_at')) {
                        Schema::connection('db_dwh')->table('crm_mst_cust_cov', function ($table) {
                            $table->softDeletes();
                        });
                    }
                }
                return true;
            } catch (\Exception $e) {
                return false;
            }
        });
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
        $this->ensureSoftDeletesColumnExists();

        $totals = $this->dwhQuery('crm_mst_cust_cov as cov')
            ->whereNull('cov.deleted_at')
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

        $totalUnmappedSellout = $this->unmappedService->getUnmappedMasterCount();
        $totalUncoveredPilar = $this->unmappedService->getUncoveredPilarCount();

        $coverage = Mapping::active()
            ->with('user:nik,name')
            ->get(['id', 'id_coverage', 'cov_desc', 'cov_nik', 'cov_level', 'cov_pilar']);
        $allCoverage = $coverage;

        return view('page.outlet.index', compact(
            'totalData',
            'totalTercoverage',
            'totalBelumCoverage',
            'totalButuhPengganti',
            'totalUnmappedSellout',
            'totalUncoveredPilar',
            'coverage',
            'allCoverage'
        ));
    }

    public function data(Request $request)
    {
        $this->ensureSoftDeletesColumnExists();

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
            ->whereNull('cov.deleted_at')
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
            ->whereNull('crm_mst_mappings.deleted_at');

        if (!$request->boolean('include_inactive')) {
            $query->where('crm_mst_mappings.is_active', true);
        }

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
            ->select([
                DB::raw('crm_mst_mappings.id_coverage as id'),
                DB::raw('crm_mst_mappings.cov_nik as cov_nik'),
                DB::raw("CONCAT(crm_mst_mappings.id_coverage, ' - ', crm_mst_mappings.cov_nik, IF(users.name IS NULL OR users.name = '', '', CONCAT(' - ', users.name)), IF(COALESCE(crm_mst_mappings.is_active, 0) = 1, '', ' (Nonaktif)')) as text"),
            ])
            ->distinct()
            ->orderBy('crm_mst_mappings.id_coverage')
            ->limit(30)
            ->get();

        return response()->json(['results' => $coverage]);
    }

    public function store(Request $request)
    {
        $this->ensureSoftDeletesColumnExists();

        $request->validate([
            'id_outlet'   => 'required|string',
            'id_coverage' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
        ]);

        $pilar = $request->input('pilar', 'ETHICAL');

        $existing = CrmMstCustCov::withTrashed()
            ->where('id_konversi', $request->id_outlet)
            ->where('pilar', $pilar)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update(['id_coverage' => $request->id_coverage]);
                Cache::flush();
                return response()->json(['success' => true, 'message' => 'Mapping berhasil ditambahkan kembali.']);
            }

            if ($existing->id_coverage == $request->id_coverage) {
                return response()->json(['success' => false, 'message' => 'Outlet ini sudah di-mapping ke coverage tersebut.'], 422);
            }

            $existing->update(['id_coverage' => $request->id_coverage]);
            Cache::flush();
            return response()->json(['success' => true, 'message' => 'Mapping berhasil diperbarui.']);
        }

        CrmMstCustCov::create([
            'id_konversi' => $request->id_outlet,
            'pilar'       => $pilar,
            'id_coverage' => $request->id_coverage,
        ]);

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
            'old_coverage_id' => 'required|string',
            'new_coverage_id' => 'required|string|different:old_coverage_id',
            'mapping_ids'     => 'required|array|min:1',
            'mapping_ids.*'   => 'required|integer',
        ]);

        if (!$this->coverageAccess->canAccessCoverage(auth()->user(), $request->old_coverage_id)
            || !$this->coverageAccess->canAccessCoverage(auth()->user(), $request->new_coverage_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk memindahkan coverage ini.'
            ], 403);
        }

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

        $latestMappings = $this->dwhQuery('crm_mst_mappings')
            ->selectRaw('MAX(id) as id, id_coverage')
            ->whereNull('deleted_at')
            ->groupBy('id_coverage');

        $outlets = $this->dwhQuery('crm_mst_cust_cov as cov')
            ->leftJoin($this->dwhTable('crm_mst_cust') . ' as master', 'cov.id_konversi', '=', 'master.id_konversi')
            ->leftJoinSub($latestMappings, 'latest_mappings', function ($join) {
                $join->on('cov.id_coverage', '=', 'latest_mappings.id_coverage');
            })
            ->leftJoin($this->dwhTable('crm_mst_mappings') . ' as mapping', 'latest_mappings.id', '=', 'mapping.id')
            ->where('cov.id_coverage', $coverageId)
            ->whereNull('cov.deleted_at')
            ->select([
                'cov.id',
                'cov.id_konversi',
                'master.nama_outlet',
                'master.segment',
                'master.kota',
                'cov.pilar',
                'cov.id_coverage',
                'mapping.id as mapping_id',
                'mapping.valid_to',
                DB::raw('COALESCE(mapping.is_active, 0) as mapping_is_active'),
            ])
            ->orderBy('cov.id_konversi')
            ->get();

        $result = $outlets->map(function ($row) {
            $needsReplacement = empty($row->mapping_id)
                || !(bool) $row->mapping_is_active
                || !empty($row->valid_to);

            return [
                'id'                => $row->id,
                'id_outlet'         => $row->id_konversi,
                'nama_outlet'       => $row->nama_outlet ?? '-',
                'segment'           => $row->segment ?? '-',
                'pilar'             => $row->pilar ?? '-',
                'kota'              => $row->kota ?? '-',
                'id_coverage'       => $row->id_coverage,
                'needs_replacement' => $needsReplacement,
            ];
        });

        return response()->json($result);
    }

    public function destroy($id)
    {
        $this->ensureSoftDeletesColumnExists();

        $cov = CrmMstCustCov::withTrashed()->findOrFail($id);

        if ($cov->trashed()) {
            return response()->json(['success' => false, 'message' => 'Mapping outlet ini sudah dihapus.'], 422);
        }

        $cov->delete();
        Cache::flush();

        return response()->json(['success' => true, 'message' => 'Mapping berhasil dihapus.']);
    }

    public function unmappedSelloutData(Request $request)
    {
        $query = $this->unmappedService->getUnmappedMasterQuery();

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('dist', function ($row) {
                return $row->dist ?? '-';
            })
            ->editColumn('id_cbg', function ($row) {
                return $row->id_cbg ?? '-';
            })
            ->editColumn('id_outlet', function ($row) {
                return $row->id_outlet ?? '-';
            })
            ->editColumn('nama_outlet', function ($row) {
                return $row->nama_outlet ?? '-';
            })
            ->editColumn('segment_dist', function ($row) {
                return $row->segment_dist ?? '-';
            })
            ->addColumn('action', function ($row) {
                $dataJson = htmlspecialchars(json_encode([
                    'dist'         => $row->dist ?? '',
                    'id_cbg'       => $row->id_cbg ?? '',
                    'id_outlet'    => $row->id_outlet ?? '',
                    'nama_outlet'  => $row->nama_outlet ?? '',
                    'segment_dist' => $row->segment_dist ?? '',
                ]), ENT_QUOTES, 'UTF-8');

                return '<button type="button" class="btn btn-sm btn-primary font-weight-bolder btn-map-unmapped" data-outlet=\'' . $dataJson . '\'>'
                    . '<i class="fas fa-link mr-1"></i> Petakan'
                    . '</button>';
            })
            ->filterColumn('id_cbg', function ($query, $keyword) {
                $query->where('so.id_cbg', 'like', "%{$keyword}%");
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function uncoveredPilarData(Request $request)
    {
        $query = $this->unmappedService->getUncoveredPilarQuery();

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('id_konversi', function ($row) {
                return $row->id_konversi ?? '-';
            })
            ->editColumn('nama_outlet', function ($row) {
                return $row->nama_outlet ?? '-';
            })
            ->editColumn('segment', function ($row) {
                return $row->segment ?? '-';
            })
            ->editColumn('pilar', function ($row) {
                return '<span class="badge badge-light-primary font-weight-bold">' . ($row->pilar ?? '-') . '</span>';
            })
            ->addColumn('action', function ($row) {
                $dataJson = htmlspecialchars(json_encode([
                    'id_konversi' => $row->id_konversi ?? '',
                    'nama_outlet' => $row->nama_outlet ?? '',
                    'pilar'       => $row->pilar ?? '',
                ]), ENT_QUOTES, 'UTF-8');

                return '<button type="button" class="btn btn-sm btn-info font-weight-bolder btn-assign-pilar" data-outlet=\'' . $dataJson . '\'>'
                    . '<i class="fas fa-user-plus mr-1"></i> Tetapkan Coverage'
                    . '</button>';
            })
            ->filterColumn('id_konversi', function ($query, $keyword) {
                $query->where('so.id_konversi', 'like', "%{$keyword}%");
            })
            ->rawColumns(['pilar', 'action'])
            ->make(true);
    }

    public function unmappedSelloutStats()
    {
        return response()->json([
            'total_unmapped_outlet' => $this->unmappedService->getUnmappedMasterCount(),
            'total_uncovered_pilar' => $this->unmappedService->getUncoveredPilarCount(),
        ]);
    }

    public function mapUnmappedSellout(Request $request)
    {
        $request->validate([
            'dist'        => 'required|string',
            'id_cbg'      => 'required|string',
            'id_outlet'   => 'required|string',
            'mode'        => 'required|in:existing,new',
            'id_konversi' => 'required|string|max:50',
            'nama_outlet' => 'required_if:mode,new|nullable|string|max:255',
            'segment'     => 'nullable|string|max:100',
            'kota'        => 'nullable|string|max:100',
            'alamat'      => 'nullable|string',
            'id_coverage' => 'nullable|string|exists:db_dwh.crm_mst_mappings,id_coverage',
            'pilar'       => 'nullable|string',
        ]);

        $mode = $request->input('mode');
        $idKonversi = trim((string) $request->input('id_konversi'));
        $dist = trim((string) $request->input('dist'));
        $idCbg = trim((string) $request->input('id_cbg'));
        $idOutlet = trim((string) $request->input('id_outlet'));

        DB::connection('db_dwh')->beginTransaction();
        try {
            if ($mode === 'new') {
                $existingMaster = CrmMstCust::find($idKonversi);
                if ($existingMaster) {
                    return response()->json([
                        'success' => false,
                        'message' => "ID Konversi '{$idKonversi}' sudah terdaftar di Master Customer. Silakan gunakan opsi 'Hubungkan ke Master Eksisting' atau ganti ID Konversi."
                    ], 422);
                }

                CrmMstCust::create([
                    'id_konversi' => $idKonversi,
                    'nama_outlet' => $request->input('nama_outlet'),
                    'segment'     => $request->input('segment'),
                    'kota'        => $request->input('kota'),
                    'alamat'      => $request->input('alamat'),
                ]);
            } else {
                $master = CrmMstCust::find($idKonversi);
                if (!$master) {
                    return response()->json([
                        'success' => false,
                        'message' => "Master Outlet dengan ID Konversi '{$idKonversi}' tidak ditemukan."
                    ], 404);
                }
            }

            // 1. Simpan atau update crm_mst_cust_dist
            $custDist = CrmMstCustDist::where('dist', $dist)
                ->where('id_cbg', $idCbg)
                ->where('id_outlet', $idOutlet)
                ->first();

            if ($custDist) {
                $custDist->update(['id_konversi' => $idKonversi]);
            } else {
                CrmMstCustDist::create([
                    'dist'          => $dist,
                    'id_cbg'        => $idCbg,
                    'id_outlet'     => $idOutlet,
                    'id_konversi'   => $idKonversi,
                    'id_outlet_cbg' => $dist . '_' . $idCbg . '_' . $idOutlet,
                ]);
            }

            // 2. Hubungkan ke Coverage (crm_mst_cust_cov) jika dipilih
            $idCoverage = trim((string) $request->input('id_coverage', ''));
            if ($idCoverage !== '') {
                $this->ensureSoftDeletesColumnExists();
                $pilar = strtoupper(trim((string) $request->input('pilar', 'ETHICAL')));
                if ($pilar === '') {
                    $pilar = 'ETHICAL';
                }

                $existingCov = CrmMstCustCov::withTrashed()
                    ->where('id_konversi', $idKonversi)
                    ->where('pilar', $pilar)
                    ->first();

                if ($existingCov) {
                    if ($existingCov->trashed()) {
                        $existingCov->restore();
                    }
                    $existingCov->update(['id_coverage' => $idCoverage]);
                } else {
                    CrmMstCustCov::create([
                        'id_konversi' => $idKonversi,
                        'pilar'       => $pilar,
                        'id_coverage' => $idCoverage,
                    ]);
                }
            }

            // 3. Auto-update tabel crm_sellout_summary
            $updatedRows = DB::connection('db_dwh')
                ->table('crm_sellout_summary')
                ->where('dist', $dist)
                ->whereRaw('TRIM(id_cbg) = ?', [$idCbg])
                ->whereRaw('TRIM(id_outlet) = ?', [$idOutlet])
                ->where(function ($q) {
                    $q->whereNull('id_konversi')
                        ->orWhere('id_konversi', '');
                })
                ->update([
                    'id_konversi' => $idKonversi,
                    'updated_at'  => now(),
                ]);

            DB::connection('db_dwh')->commit();
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => "Outlet {$dist} ({$idOutlet} - {$idCbg}) berhasil dipetakan ke ID Konversi '{$idKonversi}'. {$updatedRows} baris transaksi sellout berhasil diperbarui!",
                'updated_summary_count' => $updatedRows,
            ]);
        } catch (\Exception $e) {
            DB::connection('db_dwh')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pemetaan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function assignPilarCoverage(Request $request)
    {
        $request->validate([
            'id_konversi' => 'required|string|exists:db_dwh.crm_mst_cust,id_konversi',
            'pilar'       => 'required|string|in:ETHICAL,OGB,CH,OTC',
            'id_coverage' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
        ]);

        $idKonversi = trim((string) $request->input('id_konversi'));
        $pilar = trim((string) $request->input('pilar'));
        $idCoverage = trim((string) $request->input('id_coverage'));

        DB::connection('db_dwh')->beginTransaction();
        try {
            $existing = CrmMstCustCov::withTrashed()
                ->where('id_konversi', $idKonversi)
                ->where('pilar', $pilar)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->id_coverage = $idCoverage;
                $existing->save();
            } else {
                CrmMstCustCov::create([
                    'id_konversi' => $idKonversi,
                    'pilar'       => $pilar,
                    'id_coverage' => $idCoverage,
                ]);
            }

            DB::connection('db_dwh')->commit();
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => "Coverage pilar '{$pilar}' berhasil ditetapkan untuk outlet {$idKonversi}.",
            ]);
        } catch (\Exception $e) {
            DB::connection('db_dwh')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menetapkan coverage: ' . $e->getMessage(),
            ], 500);
        }
    }
}

