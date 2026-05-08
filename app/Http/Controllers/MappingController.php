<?php

namespace App\Http\Controllers;

use App\Models\Mapping;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Notifications\CoverageNeedsReplacementNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

        return view('page.mapping.index', compact('totalData', 'newData', 'activeData', 'updatedToday'));
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
            ->addColumn('status', function ($row) {
                $badge = $row->is_active
                    ? '<span class="label label-inline label-success">Aktif</span>'
                    : '<span class="label label-inline label-danger">Nonaktif</span>';
                return $badge;
            })
            ->addColumn('action', function ($row) {
                $edit = '<button class="btn btn-sm btn-outline-warning btn-icon mr-2 btn-edit" data-id="' . $row->id_coverage . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $toggle = $row->is_active
                    ? '<button class="btn btn-sm btn-outline-primary btn-icon mr-2 btn-toggle" data-id="' . $row->id_coverage . '" title="Nonaktifkan">
                            <i class="fas fa-toggle-on"></i>
                       </button>'
                    : '<button class="btn btn-sm btn-outline-danger btn-icon mr-2 btn-toggle" data-id="' . $row->id_coverage . '" title="Aktifkan">
                            <i class="fas fa-toggle-off"></i>
                       </button>';
                $delete = '<button class="btn btn-sm btn-outline-danger btn-icon mr-2 btn-delete" data-id="' . $row->id_coverage . '">
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
            ->select('nik as id', DB::raw("CONCAT(nik, ' - ', nama) as text"))
            ->limit(30)
            ->get();

        return response()->json(['results' => $anggota]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_coverage'        => 'required|string|unique:mst_mappings,id_coverage',
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

        // Validasi Single/Combo
        $validation = Mapping::validateCoverage(
            $request->cov_nik,
            $request->cov_pilar,
            $request->tanggung_jwb_pilar
        );

        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['message']], 422);
        }

        DB::beginTransaction();
        try {
            Mapping::create([
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

        // Validasi Single/Combo (exclude diri sendiri)
        $validation = Mapping::validateCoverage(
            $request->cov_nik,
            $request->cov_pilar,
            $request->tanggung_jwb_pilar,
            $id
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
        $notificationData = null;

        DB::beginTransaction();
        try {
            $newStatus = !$coverage->is_active;

            // Jika dinonaktifkan
            if (!$newStatus) {
                // Auto-fill valid_to jika masih null
                if (is_null($coverage->valid_to)) {
                    $coverage->valid_to = Carbon::today();
                }
                $coverage->is_active = false;
                $coverage->save();

                // Flag outlet terkait butuh pengganti
                Outlet::where('id_coverage', $coverage->id_coverage)
                    ->update(['needs_replacement' => true]);
                Product::where('id_coverage', $coverage->id_coverage)
                    ->update(['needs_replacement' => true]);

                $outletCount = Outlet::where('id_coverage', $coverage->id_coverage)->count();
                $productCount = Product::where('id_coverage', $coverage->id_coverage)->count();
                $notificationData = compact('outletCount', 'productCount');
            } else {
                $coverage->is_active = true;
                $coverage->valid_to  = null;
                $coverage->save();
            }

            DB::commit();

            if ($notificationData) {
                try {
                    $admins = User::role('admin')->get();
                    Notification::send(
                        $admins,
                        new CoverageNeedsReplacementNotification(
                            $coverage,
                            $notificationData['outletCount'],
                            $notificationData['productCount']
                        )
                    );
                } catch (\Exception $notificationException) {
                    report($notificationException);
                }
            }

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

        // Cek apakah masih ada mapping aktif
        $mappingCount = Outlet::where('id_coverage', $id)->count();
        if ($mappingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Coverage ini masih digunakan oleh {$mappingCount} outlet. Hapus mapping terlebih dahulu."
            ], 422);
        }

        $coverage->delete();
        return response()->json(['success' => true, 'message' => 'Coverage berhasil dihapus.']);
    }
}
