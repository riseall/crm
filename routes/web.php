<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use App\Http\Requests\DashboardFilterRequest;
use App\Notifications\CoverageNeedsReplacementNotification;
use App\Services\SelloutQueryService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    // Dashboard
    // Route::get('/dashboard', function () {
    //     return view('dashboard');
    // })->name('dashboard');

    Route::middleware(['auth'])->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/',                  [DashboardController::class, 'index'])->name('index');
        Route::get('/summary',           [DashboardController::class, 'getSummaryCards'])->name('summary');
        Route::get('/chart/bulan',       [DashboardController::class, 'getChartPerBulan'])->name('chart.bulan');
        Route::get('/chart/hari',        [DashboardController::class, 'getChartPerHari'])->name('chart.hari');
        Route::get('/chart/pilar',       [DashboardController::class, 'getChartPerPilar'])->name('chart.pilar');
        Route::get('/chart/divisi',      [DashboardController::class, 'getChartPerDivisi'])->name('chart.divisi');
        Route::get('/chart/coverage',    [DashboardController::class, 'getChartPerCoverage'])->name('chart.coverage');
        Route::get('/drilldown',         [DashboardController::class, 'getDrillDown'])->name('drilldown');
        Route::get('/table/anggota',     [DashboardController::class, 'getTableAnggota'])->name('table.anggota');
        Route::get('/table/outlet',      [DashboardController::class, 'getTableOutlet'])->name('table.outlet');
    });

    // Users
    Route::prefix('users')->name('users.')->group(function () {
        Route::resource('/', UserController::class)->only('index');
        Route::get('/data', [UserController::class, 'getData'])->name('data');
    });

    // Mapping Coverage
    Route::prefix('mapping')->name('mapping.')->group(function () {
        Route::get('/', [MappingController::class, 'index'])->name('index');
        Route::get('/data', [MappingController::class, 'getData'])->name('data');
        Route::get('/anggota', [MappingController::class, 'getAnggota'])->name('anggota');
        Route::get('/lookup-coverage', [MappingController::class, 'lookupByCoverage'])->name('lookup.coverage');
        Route::post('/', [MappingController::class, 'store'])->name('store');
        Route::get('/{id}', [MappingController::class, 'show'])->name('show');
        Route::put('/{id}', [MappingController::class, 'update'])->name('update');
        Route::patch('/{id}/toggle', [MappingController::class, 'toggleActive'])->name('toggle');
        Route::delete('/{id}', [MappingController::class, 'destroy'])->name('destroy');
    });

    // Product
    Route::prefix('product')->name('product.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/data', [ProductController::class, 'data'])->name('data');
        Route::get('/data/dist', [ProductController::class, 'dataDistPM'])->name('dataDist');
        Route::get('/produk', [ProductController::class, 'getProduk'])->name('produk');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/{id}', [ProductController::class, 'show'])->name('show');
        Route::put('/{id}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->name('destroy');
    });

    // Outlet
    Route::prefix('outlet')->name('outlet.')->group(function () {
        Route::get('/', [OutletController::class, 'index'])->name('index');
        Route::get('/history', [OutletController::class, 'history'])->name('history');
        Route::get('/history/data', [OutletController::class, 'historyData'])->name('history.data');
        Route::get('/data', [OutletController::class, 'data'])->name('data');
        Route::get('/outlet', [OutletController::class, 'getOutlet'])->name('outlet');
        Route::get('/outlet-by-coverage/{coverageId}', [OutletController::class, 'getOutletByCoverage'])->name('outlet.by.coverage');
        Route::post('/', [OutletController::class, 'store'])->name('store');
        Route::get('/{id}', [OutletController::class, 'show'])->name('show');
        Route::put('/{id}', [OutletController::class, 'update'])->name('update');
        Route::post('/bulk-update', [OutletController::class, 'bulkUpdateCoverage'])->name('bulk.update');
        Route::delete('/{id}', [OutletController::class, 'destroy'])->name('destroy');
    });

    Route::post('/notifications/coverage/read', function () {
        $user = request()->user();
        $alertSignature = request()->input('coverage_alert_signature');

        if ($user) {
            $user->notifications()
                ->where('type', CoverageNeedsReplacementNotification::class)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        if ($alertSignature) {
            session(['coverage_alert_signature' => $alertSignature]);
        }

        return back()->with('status', 'Notifikasi coverage sudah ditandai dibaca.');
    })->name('notifications.coverage.read');
});


// Route::get('/debug-sellout', function (Request $request, SelloutQueryService $service) {
//     $results = $this->service->baseQuery(new \App\Http\Requests\DashboardFilterRequest([
//         'bulan' => 5,
//         'tahun' => 2026
//     ]))
//         ->selectRaw('
//         so.dist as distributor,
//         SUM(so.netto) as total_netto,
//         COUNT(DISTINCT so.id_outlet) as total_outlet,
//         COUNT(so.id) as total_transaksi
//     ')
//         ->groupBy('so.dist')
//         ->get();

//     // Jika ingin melihat hasilnya langsung
//     dd($results->toArray());
// });

Route::get('/debug-sellout', function (SelloutQueryService $service) {
    // 1. Buat Mock Request untuk bulan Mei 2026
    $mockRequest = new \App\Http\Requests\DashboardFilterRequest([
        'bulan' => 1,
        'tahun' => 2026
    ]);

    // 2. Ambil Base Query
    // baseQuery sudah mencakup Union 3 tabel dan applyStandardJoins
    $query = $service->baseQuery($mockRequest);

    // 3. Agregasi per Distributor
    $dataPerDist = (clone $query)
        ->selectRaw('
            so.dist,
            SUM(so.netto) as total_netto,
            COUNT(*) as total_baris_transaksi,
            COUNT(DISTINCT so.id_outlet) as total_outlet
        ')
        ->groupBy('so.dist')
        ->orderBy('total_netto', 'desc')
        ->get();

    // 4. Hitung Total Keseluruhan (Grand Total)
    $grandTotal = [
        'netto' => $dataPerDist->sum('total_netto'),
        'baris' => $dataPerDist->sum('total_baris_transaksi'),
        'outlet' => $query->distinct('so.id_outlet')->count('so.id_outlet')
    ];

    // 5. Dump untuk pengecekan
    dd([
        'periode' => $mockRequest->bulan  . '/' . '2026',
        'grand_total' => $grandTotal,
        'data_per_distributor' => $dataPerDist->toArray(),
        'sql_debug' => [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]
    ]);
});


Route::get('/debug-unmapped-pilar', function () {
    // Kita ambil data mentah dari tabel transaksi (so) 
    // yang tidak punya pasangan di tabel pilar/produk (vp)
    $data = DB::table('selling_out_aam as so') // Ganti 'sellouts' dengan nama tabel transaksi kamu
        ->leftJoin('v_pt_mstr_dist as vp', 'so.material', '=', 'vp.pt_dist') // Ganti dengan key join kamu
        ->whereNull('vp.pilar')
        ->orWhere('vp.pilar', '')
        ->select(
            'so.material',
            // 'so.nama_produk',
            'so.nilai_netto',
            'vp.pilar as status_pilar'
        )
        ->limit(100)
        ->get();

    dd([
        'jumlah_item_bermasalah' => $data->count(),
        'sampel_data' => $data,
        'query_log' => DB::getQueryLog() // Pastikan DB::enableQueryLog() jika ingin intip SQL-nya
    ]);
});
