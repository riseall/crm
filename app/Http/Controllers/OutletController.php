<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class OutletController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('permission:mapping-list|mapping-create|mapping-edit|mapping-delete');
    // }

    public function index()
    {
        $totalData = Outlet::count();
        $newData = Outlet::where('created_at', '>=', now()->subDays(7))->count();
        // $activeData = Outlet::where('is_active', true)->count();
        $updatedToday = Outlet::whereDate('updated_at', today())->count();

        return view('page.outlet.index', compact('totalData', 'newData', 'updatedToday'));
    }

    public function data(Request $request)
    {
        $query = Outlet::with('coverage')
            ->join('mst_mappings', 'mst_outlets.id_coverage', '=', 'mst_mappings.id_coverage')
            ->select(
                'mst_outlets.*',
                'mst_mappings.cov_desc',
                'mst_mappings.cov_pilar',
                'mst_mappings.cov_level',
                'mst_mappings.cov_nik'
            );

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('needs_replacement_badge', function ($row) {
                return $row->needs_replacement
                    ? '<span class="badge badge-warning">Butuh Pengganti</span>'
                    : '<span class="badge badge-success">OK</span>';
            })
            ->addColumn('action', function ($row) {
                $edit = '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $row->id_mapping . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $delete = '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $row->id_mapping . '">
                               <i class="fas fa-trash"></i>
                           </button>';
                return $edit . ' ' . $delete;
            })
            ->rawColumns(['needs_replacement_badge', 'action'])
            ->make(true);
    }

    // Ambil outlet dari mst_cust_dist (HRMS) untuk dropdown/search
    public function getOutlet(Request $request)
    {
        $search = $request->get('q', '');
        $outlets = DB::connection('db_master')
            ->table('mst_cust_dist')
            ->where(function ($q) use ($search) {
                $q->where('id_outlet', 'like', "%$search%")
                    ->orWhere('nama_outlet', 'like', "%$search%");
            })
            ->select('id_outlet as id', DB::raw("CONCAT(id_outlet, ' - ', nama_outlet) as text"))
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
        $mapping = Outlet::findOrFail($id);
        return response()->json($mapping);
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
            'outlet_ids'      => 'required|array|min:1',
            'outlet_ids.*'    => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            Outlet::where('id_coverage', $request->old_coverage_id)
                ->whereIn('id_outlet', $request->outlet_ids)
                ->update([
                    'id_coverage'       => $request->new_coverage_id,
                    'needs_replacement' => false,
                ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => count($request->outlet_ids) . ' outlet berhasil dipindahkan ke coverage baru.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Ambil outlet yang terdampak dari 1 coverage (untuk bulk update)
    public function getOutletByCoverage($coverageId)
    {
        $outlets = Outlet::where('id_coverage', $coverageId)
            ->get(['id_mapping', 'id_outlet', 'needs_replacement']);

        return response()->json($outlets);
    }

    public function destroy($id)
    {
        Outlet::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Mapping berhasil dihapus.']);
    }
}
