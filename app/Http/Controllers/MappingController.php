<?php

namespace App\Http\Controllers;

use App\Models\Mapping;
use App\Models\Outlet;
use App\Services\CoverageReplacementAlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class MappingController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('permission:coverage-list|coverage-create|coverage-edit|coverage-delete', ['only' => ['index', 'data']]);
    //     $this->middleware('permission:coverage-create', ['only' => ['store']]);
    //     $this->middleware('permission:coverage-edit', ['only' => ['update', 'toggleActive']]);
    //     $this->middleware('permission:coverage-delete', ['only' => ['destroy']]);
    // }

    private function authTable(string $table): string
    {
        return DB::connection('mysql')->getDatabaseName() . '.' . $table;
    }

    private function validToIsExpired($validTo): bool
    {
        return !empty($validTo) && Carbon::parse($validTo)->lt(Carbon::today());
    }

    private function coverageIsEffectivelyActive($coverage): bool
    {
        return (bool) $coverage->is_active && !$this->validToIsExpired($coverage->valid_to);
    }

    private function monthStart(?string $month): ?string
    {
        if (empty($month)) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m', $month)->startOfMonth()->toDateString();
    }

    private function monthEnd(?string $month): ?string
    {
        if (empty($month)) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m', $month)->endOfMonth()->toDateString();
    }

    private function formatMonthYear($date): string
    {
        if (empty($date)) {
            return '-';
        }

        return Carbon::parse($date)->translatedFormat('F Y');
    }

    public function index()
    {
        $totalData = Mapping::count();
        $newData = Mapping::where('created_at', '>=', now()->subDays(7))->count();
        $activeData = Mapping::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', Carbon::today());
            })
            ->count();
        $updatedToday = Mapping::whereDate('updated_at', today())->count();
        $needsReplacement = app(CoverageReplacementAlertService::class)->pendingAlerts()->count();

        return view('page.mapping.index', compact(
            'totalData',
            'newData',
            'activeData',
            'updatedToday',
            'needsReplacement'
        ));
    }

    public function getData(Request $request)
    {
        $query = Mapping::query()
            ->whereNull('crm_mst_mappings.deleted_at')
            ->leftJoin(DB::raw($this->authTable('users') . ' as users'), 'crm_mst_mappings.cov_nik', '=', 'users.nik')
            ->select('crm_mst_mappings.*', 'users.name as user_name');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('user_name', function ($row) {
                return $row->user_name ?? '-';
            })
            ->editColumn('valid_from', function ($row) {
                return $this->formatMonthYear($row->valid_from);
            })
            ->editColumn('valid_to', function ($row) {
                return $this->formatMonthYear($row->valid_to);
            })
            ->filterColumn('cov_nik', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('crm_mst_mappings.cov_nik', 'like', "%{$keyword}%")
                        ->orWhere('users.name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('user_name', function ($query, $keyword) {
                $query->where('users.name', 'like', "%{$keyword}%");
            })
            ->orderColumn('user_name', function ($query, $order) {
                $query->orderBy('users.name', $order);
            })
            ->addColumn('status', function ($row) {
                $badge = $this->coverageIsEffectivelyActive($row)
                    ? '<span class="label label-inline label-success">Aktif</span>'
                    : '<span class="label label-inline label-danger">Nonaktif</span>';
                return $badge;
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderByRaw("(CASE WHEN crm_mst_mappings.is_active = 1 AND (crm_mst_mappings.valid_to IS NULL OR crm_mst_mappings.valid_to >= CURDATE()) THEN 1 ELSE 0 END) {$order}");
            })
            ->addColumn('action', function ($row) {
                $edit = '<button class="btn btn-sm btn-outline-warning btn-icon mr-2 btn-edit" data-id="' . $row->id . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $toggle = $this->coverageIsEffectivelyActive($row)
                    ? '<button class="btn btn-sm btn-outline-primary btn-icon mr-2 btn-toggle" data-id="' . $row->id . '" title="Nonaktifkan">
                            <i class="fas fa-toggle-on"></i>
                       </button>'
                    : '<button class="btn btn-sm btn-outline-danger btn-icon mr-2 btn-toggle" data-id="' . $row->id . '" title="Aktifkan">
                            <i class="fas fa-toggle-off"></i>
                       </button>';
                $delete = '<button class="btn btn-sm btn-outline-danger btn-icon mr-2 btn-delete" data-id="' . $row->id . '" data-url="' . route('mapping.destroy', $row->id) . '">
                               <i class="fas fa-trash"></i>
                           </button>';
                return '<div class="d-flex flex-nowrap justify-content-center align-items-center action-buttons">'
                    . $edit . $toggle . $delete .
                    '</div>';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    // Ambil data anggota dari HRMS untuk dropdown
    public function getAnggota(Request $request)
    {
        $search = $request->get('q', '');
        $anggota = DB::connection('db_master')
            ->table('mst_anggota')
            ->where('status_hapus', 1)
            ->where(function ($q) use ($search) {
                $q->where('nik', 'like', "%$search%")
                    ->orWhere('nama', 'like', "%$search%");
            })
            ->limit(30)
            ->get()
            ->map(function ($row) {
                $anggota = (array) $row;

                return [
                    'id' => $row->nik,
                    'text' => $row->nik . ' - ' . $row->nama,
                    'name' => $row->nama,
                    'email' => $row->email ?? null,
                    'jabatan' => $row->ref_nama_jabatan ?? null,
                    'is_active' => $this->hrmsIsActive($anggota),
                    'tanggal_masuk' => $this->hrmsDate($anggota, ['tanggal_masuk', 'tgl_masuk', 'date_in', 'join_date']),
                    'tanggal_keluar' => $this->hrmsDate($anggota, ['tanggal_keluar', 'tgl_keluar', 'date_out', 'resign_date']),
                    // 'level_desc' => $this->inferLevelDesc($row->ref_nama_jabatan ?? null),
                ];
            });

        return response()->json(['results' => $anggota]);
    }

    public function lookupByCoverage(Request $request)
    {
        $request->validate([
            'id_coverage' => 'required|string',
        ]);

        $coverage = Mapping::query()
            ->where('id_coverage', $request->id_coverage)
            ->orderByDesc('id')
            ->first([
                'id_coverage',
                'cov_desc',
                'cov_divisi',
                'cov_ket_cbg',
                'cov_pilar',
                'cov_level',
                'cov_level_desc',
                'tanggung_jwb_pilar',
            ]);

        if (!$coverage) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'coverage' => [
                'id_coverage' => $coverage->id_coverage,
                'cov_desc' => $coverage->cov_desc,
                'cov_divisi' => $coverage->cov_divisi,
                'cov_ket_cbg' => $coverage->cov_ket_cbg,
                'cov_pilar' => $coverage->cov_pilar,
                'level_desc' => $coverage->cov_level_desc ?: $this->levelNumToDesc($coverage->cov_level),
                'responsibility' => $coverage->tanggung_jwb_pilar,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_coverage'        => 'required|string',
            'cov_desc'           => 'required|string',
            'cov_divisi'         => 'required|string',
            'cov_pilar'          => 'nullable|in:ETHIPROM,ETHICOM,ETHICAL,OGB,OTC',
            'cov_level_desc'     => 'required|in:MR/SR,AM,SAM,RM,PM,GM',
            'tanggung_jwb_pilar' => 'required|in:Single,Combo',
            'cov_nik'            => 'required|string',
            'valid_from'         => 'required|date_format:Y-m',
            'valid_to'           => 'nullable|date_format:Y-m|after_or_equal:valid_from',
        ]);

        $levelMapping = [
            'GM'    => 1,
            'RM'    => 2,
            'PM'    => 2,
            'SAM'   => 3,
            'AM'    => 4,
            'MR/SR' => 5,
        ];
        $autoLevel = $levelMapping[$request->cov_level_desc] ?? null;

        if (!$this->isNikActive($request->cov_nik)) {
            return response()->json(['success' => false, 'message' => 'PIC yang dipilih berstatus nonaktif di HRMS.'], 422);
        }

        DB::connection('db_dwh')->beginTransaction();
        try {
            $today = Carbon::today()->toDateString();
            $validFrom = $this->monthStart($request->valid_from);
            $validTo = $this->monthEnd($request->valid_to);
            $willBeActive = !$this->validToIsExpired($validTo);
            $alertService = app(CoverageReplacementAlertService::class);
            $closedCoverages = collect();

            if ($willBeActive) {
                $closedCoverages = $this->closeActiveMappingsByCode($request->id_coverage, $today);
            }

            // Validasi Single/Combo setelah coverage lama ditutup
            $validation = Mapping::validateCoverage(
                $request->cov_nik,
                $request->cov_pilar,
                $request->tanggung_jwb_pilar,
                null,
                $willBeActive
            );

            if (!$validation['valid']) {
                DB::connection('db_dwh')->rollBack();
                return response()->json(['success' => false, 'message' => $validation['message']], 422);
            }

            $coverage = Mapping::create([
                'id_coverage'        => $request->id_coverage,
                'cov_desc'           => $request->cov_desc,
                'cov_divisi'         => $request->cov_divisi,
                'cov_ket_cbg'        => $request->cov_ket_cbg,
                'cov_pilar'          => $request->cov_pilar,
                'cov_level'          => $autoLevel,
                'cov_level_desc'     => $request->cov_level_desc,
                'cov_nik'            => $request->cov_nik,
                'tanggung_jwb_pilar' => $request->tanggung_jwb_pilar,
                'valid_from'         => $validFrom,
                'valid_to'           => $validTo,
                'is_active'          => $willBeActive,
            ]);

            DB::connection('db_dwh')->commit();
            Cache::flush();

            foreach ($closedCoverages as $item) {
                if ($alertService->shouldNotify($item['original_state'], $item['coverage'])) {
                    $alertService->notify($item['coverage']);
                }
            }
            $alertService->syncReplacementFlag($coverage);

            return response()->json(['success' => true, 'message' => 'Coverage berhasil ditambahkan.']);
        } catch (\Exception $e) {
            DB::connection('db_dwh')->rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $coverage = Mapping::with('user')->findOrFail($id);
        return response()->json($coverage);
    }

    public function update(Request $request, $id)
    {
        $coverage = Mapping::findOrFail($id);
        $originalState = $coverage->only(['is_active', 'valid_to']);

        $request->validate([
            'cov_nik'            => 'required|string',
            'cov_desc'           => 'required|string',
            'cov_divisi'         => 'required|string',
            'cov_pilar'          => 'nullable|in:ETHIPROM,ETHICOM,ETHICAL,OGB,OTC',
            'cov_level_desc'     => 'required|in:MR/SR,AM,SAM,RM,PM,GM',
            'tanggung_jwb_pilar' => 'required|in:Single,Combo',
            'valid_from'         => 'required|date_format:Y-m',
            'valid_to'           => 'nullable|date_format:Y-m|after_or_equal:valid_from',
        ]);

        $levelMapping = [
            'GM'    => 1,
            'RM'    => 2,
            'PM'    => 2,
            'SAM'   => 3,
            'AM'    => 4,
            'MR/SR' => 5,
        ];
        $autoLevel = $levelMapping[$request->cov_level_desc] ?? null;

        if (!$this->isNikActive($request->cov_nik)) {
            return response()->json(['success' => false, 'message' => 'PIC yang dipilih berstatus nonaktif di HRMS.'], 422);
        }

        $validFrom = $this->monthStart($request->valid_from);
        $validTo = $this->monthEnd($request->valid_to);
        $willBeActive = (bool) $coverage->is_active && !$this->validToIsExpired($validTo);

        // Validasi Single/Combo (exclude diri sendiri)
        $validation = Mapping::validateCoverage(
            $request->cov_nik,
            $request->cov_pilar,
            $request->tanggung_jwb_pilar,
            $id,
            $willBeActive
        );

        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['message']], 422);
        }

        DB::connection('db_dwh')->beginTransaction();
        try {
            $coverage->update([
                'cov_desc'           => $request->cov_desc,
                'cov_divisi'         => $request->cov_divisi,
                'cov_ket_cbg'        => $request->cov_ket_cbg,
                'cov_pilar'          => $request->cov_pilar,
                'cov_level'          => $autoLevel,
                'cov_level_desc'     => $request->cov_level_desc,
                'cov_nik'            => $request->cov_nik,
                'tanggung_jwb_pilar' => $request->tanggung_jwb_pilar,
                'valid_from'         => $validFrom,
                'valid_to'           => $validTo,
                'is_active'          => $willBeActive,
            ]);

            DB::connection('db_dwh')->commit();
            Cache::flush();

            app(CoverageReplacementAlertService::class)->syncReplacementFlag($coverage);
            $this->dispatchCoverageReplacementAlertIfNeeded($coverage, $originalState);

            return response()->json(['success' => true, 'message' => 'Coverage berhasil diupdate.']);
        } catch (\Exception $e) {
            DB::connection('db_dwh')->rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal update: ' . $e->getMessage()], 500);
        }
    }

    // Toggle is_active
    public function toggleActive($id)
    {
        $coverage = Mapping::findOrFail($id);
        $originalState = $coverage->only(['is_active', 'valid_to']);
        $today = Carbon::today()->toDateString();
        $alertService = app(CoverageReplacementAlertService::class);

        DB::connection('db_dwh')->beginTransaction();
        try {
            $newStatus = !$this->coverageIsEffectivelyActive($coverage);
            $closedCoverages = collect();

            // Jika dinonaktifkan
            if (!$newStatus) {
                // Auto-fill valid_to jika masih null
                if (is_null($coverage->valid_to)) {
                    $coverage->valid_to = $today;
                }
                $coverage->is_active = false;
                $coverage->save();
            } else {
                $closedCoverages = $this->closeActiveMappingsByCode($coverage->id_coverage, $today);

                $validation = Mapping::validateCoverage(
                    $coverage->cov_nik,
                    $coverage->cov_pilar,
                    $coverage->tanggung_jwb_pilar,
                    $coverage->id,
                    true
                );

                if (!$validation['valid']) {
                    DB::connection('db_dwh')->rollBack();
                    return response()->json(['success' => false, 'message' => $validation['message']], 422);
                }

                $coverage->is_active = true;
                $coverage->valid_to  = null;
                $coverage->save();
            }

            DB::connection('db_dwh')->commit();
            Cache::flush();
            foreach ($closedCoverages as $item) {
                if ($alertService->shouldNotify($item['original_state'], $item['coverage'])) {
                    $alertService->notify($item['coverage']);
                }
            }
            $alertService->syncReplacementFlag($coverage);
            $this->dispatchCoverageReplacementAlertIfNeeded($coverage, $originalState);

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'Coverage diaktifkan.' : 'Coverage dinonaktifkan. Notifikasi dikirim ke admin.',
                'is_active' => $newStatus,
            ]);
        } catch (\Exception $e) {
            DB::connection('db_dwh')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $coverage = Mapping::withTrashed()->findOrFail($id);

        if ($coverage->trashed()) {
            throw ValidationException::withMessages([
                'id' => 'Coverage ini sudah dihapus.',
            ]);
        }

        // Hanya blok jika record aktif masih dipakai outlet
        $mappingCount = 0;
        if ($coverage->is_active) {
            $mappingCount = Outlet::where('id_coverage', $coverage->id_coverage)->count();
        }

        if ($mappingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Coverage ini masih digunakan oleh {$mappingCount} outlet. Hapus mapping terlebih dahulu."
            ], 422);
        }

        $coverage->delete();
        Cache::flush();
        return response()->json(['success' => true, 'message' => 'Coverage berhasil dihapus.']);
    }

    protected function levelNumToDesc($level): ?string
    {
        $levelMap = [
            1 => 'GM',
            2 => 'RM',
            3 => 'SAM',
            4 => 'AM',
            5 => 'MR/SR',
        ];

        return $levelMap[(int) $level] ?? null;
    }

    protected function dispatchCoverageReplacementAlertIfNeeded(Mapping $coverage, array $originalState): void
    {
        $alertService = app(CoverageReplacementAlertService::class);

        if (!$alertService->shouldNotify($originalState, $coverage)) {
            return;
        }

        try {
            $alertService->notify($coverage);
        } catch (\Throwable $notificationException) {
            report($notificationException);
        }
    }

    protected function isNikActive(string $nik): bool
    {
        $anggota = DB::connection('db_master')
            ->table('mst_anggota')
            ->where('nik', $nik)
            ->first();

        if (!$anggota) {
            return true;
        }

        return $this->hrmsIsActive((array) $anggota) === 1;
    }

    protected function hrmsIsActive(array $anggota): int
    {
        if (array_key_exists('is_active', $anggota)) {
            return (int) $anggota['is_active'] === 1 ? 1 : 0;
        }

        if (array_key_exists('status_hapus', $anggota)) {
            return (int) $anggota['status_hapus'] === 1 ? 1 : 0;
        }

        return 1;
    }

    protected function hrmsDate(array $anggota, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($anggota[$key])) {
                return date('Y-m-d', strtotime($anggota[$key]));
            }
        }

        return null;
    }

    protected function inferLevelDesc(?string $jabatan): ?string
    {
        if (!$jabatan) {
            return null;
        }

        $upper = strtoupper($jabatan);

        if (str_contains($upper, 'GM')) {
            return 'GM';
        }

        if (str_contains($upper, 'RM') || str_contains($upper, 'PM')) {
            return 'RM';
        }

        if (str_contains($upper, 'SAM')) {
            return 'SAM';
        }

        if (str_contains($upper, 'AM')) {
            return 'AM';
        }

        if (str_contains($upper, 'MR') || str_contains($upper, 'SR')) {
            return 'MR/SR';
        }

        return null;
    }

    protected function closeActiveMappingsByCode(string $idCoverage, string $today): \Illuminate\Support\Collection
    {
        $closed = collect();

        Mapping::where('id_coverage', $idCoverage)
            ->where('is_active', true)
            ->get()
            ->each(function (Mapping $mapping) use ($today, $closed) {
                $originalState = $mapping->only(['is_active', 'valid_to']);
                $mapping->update([
                    'valid_to'  => $today,
                    'is_active' => false,
                ]);

                $closed->push([
                    'coverage' => $mapping,
                    'original_state' => $originalState,
                ]);
            });

        return $closed;
    }
}
