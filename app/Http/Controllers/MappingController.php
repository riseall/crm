<?php

namespace App\Http\Controllers;

use App\Models\Mapping;
use App\Models\Outlet;
use App\Services\CoverageReplacementAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class MappingController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('permission:coverage-list|coverage-create|coverage-edit|coverage-delete', ['only' => ['index', 'data']]);
    //     $this->middleware('permission:coverage-create', ['only' => ['store']]);
    //     $this->middleware('permission:coverage-edit', ['only' => ['update', 'toggleActive']]);
    //     $this->middleware('permission:coverage-delete', ['only' => ['destroy']]);
    // }

    public function index()
    {
        $totalData = Mapping::count();
        $newData = Mapping::where('created_at', '>=', now()->subDays(7))->count();
        $activeData = Mapping::where('is_active', true)->count();
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
            ->select('mst_mappings.*')
            ->with('user');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('user_name', function ($row) {
                return $row->user->name ?? '-';
            })
            ->filterColumn('cov_nik', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('cov_nik', 'like', "%{$keyword}%")
                        ->orWhereHas('user', function ($userQuery) use ($keyword) {
                            $userQuery->where('name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->filterColumn('user_name', function ($query, $keyword) {
                $query->whereHas('user', function ($userQuery) use ($keyword) {
                    $userQuery->where('name', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('user_name', function ($query, $order) {
                $query->orderBy('cov_nik', $order);
            })
            ->addColumn('status', function ($row) {
                $badge = $row->is_active
                    ? '<span class="label label-inline label-success">Aktif</span>'
                    : '<span class="label label-inline label-danger">Nonaktif</span>';
                return $badge;
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderBy('is_active', $order);
            })
            ->addColumn('action', function ($row) {
                $edit = '<button class="btn btn-sm btn-outline-warning btn-icon mr-2 btn-edit" data-id="' . $row->id . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $toggle = $row->is_active
                    ? '<button class="btn btn-sm btn-outline-primary btn-icon mr-2 btn-toggle" data-id="' . $row->id . '" title="Nonaktifkan">
                            <i class="fas fa-toggle-on"></i>
                       </button>'
                    : '<button class="btn btn-sm btn-outline-danger btn-icon mr-2 btn-toggle" data-id="' . $row->id . '" title="Aktifkan">
                            <i class="fas fa-toggle-off"></i>
                       </button>';
                $delete = '<button class="btn btn-sm btn-outline-danger btn-icon mr-2 btn-delete" data-id="' . $row->id . '">
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
            'cov_pilar'          => 'nullable|in:ETHICAL,OGB,OTC',
            'cov_level_desc'     => 'required|in:MR/SR,AM,SAM,RM,PM,GM',
            'tanggung_jwb_pilar' => 'required|in:Single,Combo',
            'cov_nik'            => 'required|string',
            'valid_from'         => 'required|date',
            'valid_to'           => 'nullable|date|after_or_equal:valid_from',
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

        DB::beginTransaction();
        try {
            $today = Carbon::today()->toDateString();
            $alertService = app(CoverageReplacementAlertService::class);
            $closedCoverages = collect();

            $closedCoverages = $this->closeActiveMappingsByCode($request->id_coverage, $today);

            // Validasi Single/Combo setelah coverage lama ditutup
            $validation = Mapping::validateCoverage(
                $request->cov_nik,
                $request->cov_pilar,
                $request->tanggung_jwb_pilar
            );

            if (!$validation['valid']) {
                DB::rollBack();
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
                'valid_from'         => $request->valid_from,
                'valid_to'           => $request->valid_to,
                'is_active'          => true,
            ]);

            DB::commit();

            foreach ($closedCoverages as $item) {
                if ($alertService->shouldNotify($item['original_state'], $item['coverage'])) {
                    $alertService->notify($item['coverage']);
                }
            }
            $alertService->syncReplacementFlag($coverage);

            return response()->json(['success' => true, 'message' => 'Coverage berhasil ditambahkan.']);
        } catch (\Exception $e) {
            DB::rollBack();
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
            'cov_pilar'          => 'nullable|in:ETHICAL,OGB,OTC',
            'cov_level_desc'     => 'required|in:MR/SR,AM,SAM,RM,PM,GM',
            'tanggung_jwb_pilar' => 'required|in:Single,Combo',
            'valid_from'         => 'required|date',
            'valid_to'           => 'nullable|date|after_or_equal:valid_from',
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

        // Validasi Single/Combo (exclude diri sendiri)
        $validation = Mapping::validateCoverage(
            $request->cov_nik,
            $request->cov_pilar,
            $request->tanggung_jwb_pilar,
            $id,
            (bool) $coverage->is_active
        );

        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['message']], 422);
        }

        DB::beginTransaction();
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
                'valid_from'         => $request->valid_from,
                'valid_to'           => $request->valid_to,
            ]);

            DB::commit();

            app(CoverageReplacementAlertService::class)->syncReplacementFlag($coverage);
            $this->dispatchCoverageReplacementAlertIfNeeded($coverage, $originalState);

            return response()->json(['success' => true, 'message' => 'Coverage berhasil diupdate.']);
        } catch (\Exception $e) {
            DB::rollBack();
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

        DB::beginTransaction();
        try {
            $newStatus = !$coverage->is_active;
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
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => $validation['message']], 422);
                }

                $coverage->is_active = true;
                $coverage->valid_to  = null;
                $coverage->save();
            }

            DB::commit();
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
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $coverage = Mapping::findOrFail($id);

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
