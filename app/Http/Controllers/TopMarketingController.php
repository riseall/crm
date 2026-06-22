<?php

namespace App\Http\Controllers;

use App\Models\TopMarketing;
use App\Services\TopMarketingImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class TopMarketingController extends Controller
{
    public function index()
    {
        $totalData = TopMarketing::count();
        $currentMonthValue = TopMarketing::query()
            ->where('bulan', now()->month)
            ->where('tahun', (string) now()->year)
            ->sum('val_net');
        $latestUpload = TopMarketing::query()->max('updated_at');
        $latestPeriod = TopMarketing::query()
            ->select('tahun', 'bulan')
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->first();

        return view('page.top_marketing.index', [
            'totalData' => $totalData,
            'currentMonthValue' => $currentMonthValue,
            'latestUpload' => $latestUpload,
            'latestPeriod' => $latestPeriod,
        ]);
    }

    public function data(Request $request)
    {
        $query = TopMarketing::query()->select([
            'id',
            'tahun',
            'bulan',
            'divisi',
            'region',
            'pm',
            'pilar',
            'kode_ph',
            'nama_prod',
            'segment',
            'cbg_new',
            'unit',
            'val_net',
            'updated_at',
        ]);

        if ($request->filled('tahun')) {
            $query->where('tahun', (string) $request->get('tahun'));
        }

        if ($request->filled('bulan')) {
            $query->where('bulan', (int) $request->get('bulan'));
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('periode', function ($row) {
                return Carbon::create((int) $row->tahun, (int) $row->bulan, 1)->translatedFormat('F Y');
            })
            ->editColumn('unit', function ($row) {
                return number_format((float) $row->unit, 2, ',', '.');
            })
            ->editColumn('val_net', function ($row) {
                return number_format((float) $row->val_net, 2, ',', '.');
            })
            ->editColumn('updated_at', function ($row) {
                return optional($row->updated_at)->format('Y-m-d H:i');
            })
            ->make(true);
    }

    public function upload(Request $request, TopMarketingImportService $service)
    {
        $validated = $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
            'file' => 'required|file|max:5120|mimes:xlsx,csv,txt',
        ]);

        try {
            $result = $service->import(
                $request->file('file'),
                (int) $validated['bulan'],
                (int) $validated['tahun']
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Upload top marketing berhasil. ' . $result['inserted'] . ' baris tersimpan.',
            'data' => $result,
        ]);
    }
}
