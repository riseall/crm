<?php

namespace App\Http\Controllers;

use App\Models\Mapping;
use App\Models\MPart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('permission:pm-list|pm-create|pm-edit|pm-delete');
    // }

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
        $totalData = Product::count();
        $totalProdDist = MPart::where('dist', '!=', 'Nusindo')->count();
        $newData = Product::where('created_at', '>=', now()->subDays(7))->count();
        $updatedToday = Product::whereDate('updated_at', today())->count();

        $coverage = Mapping::active()->where('cov_level_desc', 'PM')->with('user')->get();

        return view('page.product.index', compact('totalData', 'newData', 'totalProdDist', 'updatedToday', 'coverage'));
    }

    public function data()
    {
        $partQuery = MPart::query()
            ->select('pt_ph', DB::raw('MIN(pt_desc) as pt_desc'))
            ->groupBy('pt_ph');

        $query = Product::query()
            ->leftJoinSub($partQuery, 'parts', function ($join) {
                $join->on('crm_mst_products.pt_ph', '=', 'parts.pt_ph');
            })
            ->select([
                'crm_mst_products.id',
                'crm_mst_products.pt_ph as c_ph',
                'crm_mst_products.id_coverage',
                DB::raw("COALESCE(parts.pt_desc, '-') as p_desc"),
            ]);

        return DataTables::of($query)
            ->addIndexColumn()
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
            ->rawColumns(['action'])
            ->make(true);
    }

    public function dataDistPM()
    {
        $query = MPart::where('dist', '!=', 'Nusindo')
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_products') . ' as crm_mst_products'), function ($join) {
                $join->on('v_pt_mstr_dist.pt_ph', '=', 'crm_mst_products.pt_ph')
                    ->whereNull('crm_mst_products.deleted_at');
            })
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_mappings') . ' as crm_mst_mappings'), function ($join) {
                $join->on('crm_mst_products.id_coverage', '=', 'crm_mst_mappings.id_coverage')
                    ->whereNull('crm_mst_mappings.deleted_at')
                    ->where('crm_mst_mappings.is_active', true);
            })
            ->leftJoin(DB::raw($this->authTable('users') . ' as users'), 'crm_mst_mappings.cov_nik', '=', 'users.nik')
            ->select([
                'v_pt_mstr_dist.pt_dist as c_dist',
                'v_pt_mstr_dist.pt_ph as c_ph',
                'v_pt_mstr_dist.pt_desc as p_desc',
                'v_pt_mstr_dist.dist',
                'v_pt_mstr_dist.pilar_sub as pilar',
                'crm_mst_mappings.id_coverage as cov',
                DB::raw("COALESCE(crm_mst_mappings.cov_nik, '-') as cov_pm"),
                DB::raw("COALESCE(users.name, '-') as nama_pm"),
            ]);

        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

    // Ambil produk dari ph_pt_mstr (HRMS) untuk dropdown
    public function getProduk(Request $request)
    {
        $search = $request->get('q', '');
        $produk = MPart::where(function ($q) use ($search) {
            $q->where('pt_ph', 'like', "%$search%")
                ->orWhere('pt_desc', 'like', "%$search%");
        })
            ->select('pt_ph as id', DB::raw("CONCAT(pt_ph, ' - ', MIN(pt_desc)) as text"))
            ->groupBy('pt_ph')
            ->limit(30)
            ->get();

        return response()->json(['results' => $produk]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pt_ph'       => 'required|string',
            'id_coverage' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
        ]);

        // Cek duplikat
        $exists = Product::where('pt_ph', $request->pt_ph)
            ->where('id_coverage', $request->id_coverage)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Produk ini sudah di-assign ke coverage tersebut.'], 422);
        }

        Product::create([
            'pt_ph'       => $request->pt_ph,
            'id_coverage' => $request->id_coverage,
            'needs_replacement' => false,
        ]);

        return response()->json(['success' => true, 'message' => 'PM berhasil ditambahkan.']);
    }

    public function show($id)
    {
        return response()->json(Product::with(['mpart', 'coverage.user'])->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'pt_ph'       => 'required|string',
            'id_coverage' => 'required|string|exists:db_dwh.crm_mst_mappings,id_coverage',
        ]);

        Product::findOrFail($id)->update([
            'pt_ph'       => $request->pt_ph,
            'id_coverage' => $request->id_coverage,
            'needs_replacement' => false,
        ]);

        return response()->json(['success' => true, 'message' => 'PM berhasil diupdate.']);
    }

    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'PM berhasil dihapus.']);
    }
}
