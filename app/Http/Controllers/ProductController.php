<?php

namespace App\Http\Controllers;

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

    public function index()
    {
        $totalData = Product::count();
        $newData = Product::where('created_at', '>=', now()->subDays(7))->count();
        // $activeData = Product::where('is_active', true)->count();
        $updatedToday = Product::whereDate('updated_at', today())->count();

        return view('page.product.index', compact('totalData', 'newData', 'updatedToday'));
    }

    public function data()
    {
        $query = Product::with('coverage')
            ->join('mst_mappings', 'mst_products.id_coverage', '=', 'mst_mappings.id_coverage')
            ->select('mst_products.*', 'mst_mappings.cov_desc', 'mst_mappings.cov_pilar', 'mst_mappings.cov_nik');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                $edit = '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $row->id_pm . '">
                            <i class="fas fa-edit"></i>
                         </button>';
                $delete = '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $row->id_pm . '">
                               <i class="fas fa-trash"></i>
                           </button>';
                return $edit . ' ' . $delete;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    // Ambil produk dari ph_pt_mstr (HRMS) untuk dropdown
    public function getProduk(Request $request)
    {
        $search = $request->get('q', '');
        $produk = DB::connection('db_master')
            ->table('ph_pt_mstr')
            ->where(function ($q) use ($search) {
                $q->where('pt_part', 'like', "%$search%")
                    ->orWhere('pt_desc', 'like', "%$search%");
            })
            ->select('pt_part as id', DB::raw("CONCAT(pt_part, ' - ', pt_desc) as text"))
            ->limit(30)
            ->get();

        return response()->json(['results' => $produk]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pt_ph'       => 'required|string',
            'id_coverage' => 'required|string|exists:mst_mappings,id_coverage',
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
        return response()->json(Product::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'pt_ph'       => 'required|string',
            'id_coverage' => 'required|string|exists:mst_mappings,id_coverage',
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
