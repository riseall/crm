<?php

namespace App\Http\Controllers;

use App\Models\Mapping;
use App\Models\MCustDist;
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
        $coverage = Mapping::active()->with('user')->get();
        $allCoverage = Mapping::with('user')->get();

        return view('page.outlet.index', compact('totalData', 'newData', 'updatedToday', 'coverage', 'allCoverage'));
    }

    public function data(Request $request)
    {
        $query = MCustDist::where('dist', '!=', 'Nusindo')
            ->leftJoin('mst_outlets', 'v_cust_dist.id_outlet', '=', 'mst_outlets.id_outlet')
            ->leftJoin('mst_mappings', function ($join) {
                $join->on('mst_outlets.id_coverage', '=', 'mst_mappings.id_coverage')
                    ->whereNull('mst_mappings.deleted_at');
            })
            ->leftJoin('users', 'mst_mappings.cov_nik', '=', 'users.nik')
            ->select([
                'mst_outlets.id',
                'v_cust_dist.id_outlet',
                'mst_outlets.id_coverage',
                'mst_outlets.needs_replacement',
                'v_cust_dist.dist',
                'v_cust_dist.nama_outlet',
                'v_cust_dist.id_cbg_dist',
                // 'v_cust_dist.nama_cabang',
                // 'v_cust_dist.kota',
                'v_cust_dist.segment',
                'mst_mappings.cov_pilar as pilar',
                'mst_mappings.cov_nik',
                'users.name as user_name',
            ]);

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
                return $row->needs_replacement
                    ? '<span class="label label-inline label-warning">Butuh Pengganti</span>'
                    : '<span class="label label-inline label-success">OK</span>';
            })
            ->filterColumn('status', function ($query, $keyword) {
                $keyword = strtolower($keyword);

                if (str_contains('butuh pengganti', $keyword) || str_contains('pengganti', $keyword)) {
                    $query->where('mst_outlets.needs_replacement', true);
                    return;
                }

                if (str_contains('ok', $keyword)) {
                    $query->where('mst_outlets.needs_replacement', false);
                }
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderBy('mst_outlets.needs_replacement', $order);
            })
            ->addColumn('action', function ($row) {
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
        $mapping = Outlet::with(['custDist', 'coverage.user'])->findOrFail($id);

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
        $outlets = Outlet::where('id_coverage', $coverageId)
            ->get(['id', 'id_outlet', 'needs_replacement']);

        return response()->json($outlets);
    }

    public function destroy($id)
    {
        Outlet::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Mapping berhasil dihapus.']);
    }
}
