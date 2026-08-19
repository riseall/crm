<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\SelloutQueryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class OutletPerformanceController extends Controller
{
    private SelloutQueryService $sellout;

    public function __construct(SelloutQueryService $sellout)
    {
        $this->sellout = $sellout;
    }

    public function index()
    {
        return view('page.outlet.history');
    }

    public function data(DashboardFilterRequest $request)
    {
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $base = $this->sellout->outletHistoryAggregateQuery($request, $search);
        $outletNameOrderSql = $search !== '' ? 'so.outlet_name' : 'COALESCE(vcd.outlet_name, so.id_outlet)';
        $userNameOrderSql = $search !== '' ? 'so.user_name' : 'ma.name';

        $grandTotalNetto = (float) DB::connection('db_dwh')
            ->query()
            ->fromSub($base, 'agg')
            ->sum('total_netto');

        $datatable = DataTables::of($base)
            ->skipTotalRecords()
            ->with('grand_total_netto', $grandTotalNetto)
            ->orderColumn('dist', 'so.dist $1')
            ->orderColumn('id_outlet', 'so.id_outlet $1')
            ->orderColumn('id_cbg', 'so.id_cbg $1')
            ->orderColumn('outlet_name', $outletNameOrderSql . ' $1')
            ->orderColumn('coverage_id', 'so.coverage_id $1')
            ->orderColumn('coverage_desc', 'so.coverage_desc $1')
            ->orderColumn('coverage_nik', 'so.coverage_nik $1')
            ->orderColumn('user_name', $userNameOrderSql . ' $1')
            ->orderColumn('coverage_level', 'so.coverage_level $1')
            ->orderColumn('product_pilar', 'so.product_pilar $1')
            ->orderColumn('bulan', 'MIN(so.tgl_faktur) $1')
            ->orderColumn('status_label', function ($query, $order) {
                $query->orderByRaw("(CASE WHEN so.coverage_id IS NULL OR so.coverage_nik IS NULL OR so.coverage_nik = '' THEN 0 WHEN so.coverage_valid_to IS NULL OR so.coverage_valid_to >= CURDATE() THEN 2 ELSE 1 END) {$order}");
            })
            ->orderColumn('total_netto', 'SUM(so.netto) $1')
            ->addColumn('bulan', function ($row) {
                $months = [
                    1  => 'Januari',
                    2  => 'Februari',
                    3  => 'Maret',
                    4  => 'April',
                    5  => 'Mei',
                    6  => 'Juni',
                    7  => 'Juli',
                    8  => 'Agustus',
                    9  => 'September',
                    10 => 'Oktober',
                    11 => 'November',
                    12 => 'Desember',
                ];

                $date = Carbon::parse($row->bulan_date);

                return ($months[(int) $date->month] ?? $date->format('F')) . ' ' . $date->year;
            })
            ->editColumn('coverage_nik', function ($row) {
                return empty($row->coverage_nik) || in_array(trim((string) $row->coverage_nik), ['-', 'N/A', 'Unmapped'], true) ? 'Unmapped' : $row->coverage_nik;
            })
            ->editColumn('user_name', function ($row) {
                return empty($row->user_name) || in_array(trim((string) $row->user_name), ['-', 'N/A', 'Unmapped'], true) ? 'Unmapped' : $row->user_name;
            })
            ->editColumn('coverage_level', function ($row) {
                $level = $this->sellout->numToLevel($row->coverage_level);
                return empty($level) || in_array(trim((string) $level), ['-', 'N/A', 'Unmapped'], true) ? 'Unmapped' : $level;
            })
            ->editColumn('status_label', function ($row) {
                if (empty($row->coverage_id) || empty($row->coverage_nik) || $row->coverage_nik === '-') {
                    return 'Unmapped';
                }

                $isExpired = $row->coverage_valid_to && Carbon::parse($row->coverage_valid_to)->lt(now());

                return $isExpired ? 'Non-aktif' : 'Aktif';
            })
            ->addColumn('status_class', function ($row) {
                if (empty($row->coverage_id) || empty($row->coverage_nik) || $row->coverage_nik === '-') {
                    return 'badge-warning text-white';
                }

                $isExpired = $row->coverage_valid_to && Carbon::parse($row->coverage_valid_to)->lt(now());

                return $isExpired ? 'badge-danger' : 'badge-success';
            })
            ->rawColumns(['status_label'])
            ->addIndexColumn();

        return $datatable->make(true);
    }
}
