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
            ->with([]);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status', function ($row) {
                $badge = $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-danger">Nonaktif</span>';
                return $badge;
            })
            ->addColumn('action', function ($row) {
                $edit = '<button class="btn btn-sm btn-warning btn-edit btn-icon mr-2" data-id="' . $row->id_coverage . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $toggle = $row->is_active
                    ? '<button class="btn btn-sm btn-danger btn-toggle btn-icon mr-2" data-id="' . $row->id_coverage . '" title="Nonaktifkan">
                            <i class="fas fa-toggle-on"></i>
                       </button>'
                    : '<button class="btn btn-sm btn-success btn-toggle btn-icon mr-2" data-id="' . $row->id_coverage . '" title="Aktifkan">
                            <i class="fas fa-toggle-off"></i>
                       </button>';
                $delete = '<button class="btn btn-sm btn-danger btn-delete btn-icon mr-2" data-id="' . $row->id_coverage . '">
                               <i class="fas fa-trash"></i>
                           </button>';
                return $edit . ' ' . $toggle . ' ' . $delete;
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
            ->where('is_active', 1)
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
            'cov_nik'            => 'required|string',
            'cov_pilar'          => 'required|in:ETHICAL,OGB,OTC',
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
        $coverage = Mapping::findOrFail($id);
        return response()->json($coverage);
    }

    public function update(Request $request, $id)
    {
        $coverage = Mapping::findOrFail($id);

        $request->validate([
            'cov_nik'            => 'required|string',
            'cov_pilar'          => 'required|in:ETHICAL,OGB,OTC',
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

                // Kirim notifikasi ke admin
                $admins = User::role('admin')->get();
                $outletCount = Outlet::where('id_coverage', $coverage->id_coverage)->count();
                $productCount = Product::where('id_coverage', $coverage->id_coverage)->count();
                Notification::send($admins, new CoverageNeedsReplacementNotification($coverage, $outletCount, $productCount));
            } else {
                $coverage->is_active = true;
                $coverage->valid_to  = null;
                $coverage->save();
            }

            DB::commit();
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
