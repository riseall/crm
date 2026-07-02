<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellingInController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TopMarketingController;
use App\Http\Controllers\UserController;
use App\Notifications\CoverageNeedsReplacementNotification;
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
        Route::get('/consolidated-data', [DashboardController::class, 'getConsolidatedData'])->name('consolidated');
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

    // Top Marketing
    Route::prefix('top-marketing')->name('top-marketing.')->group(function () {
        Route::get('/', [TopMarketingController::class, 'index'])->name('index');
        Route::get('/data', [TopMarketingController::class, 'data'])->name('data');
        Route::post('/upload', [TopMarketingController::class, 'upload'])->name('upload');
    });

    // Outlet
    Route::prefix('outlet')->name('outlet.')->group(function () {
        Route::get('/', [OutletController::class, 'index'])->name('index');
        Route::get('/history', [OutletController::class, 'history'])->name('history');
        Route::get('/history/data', [OutletController::class, 'historyData'])->name('history.data');
        Route::get('/data', [OutletController::class, 'data'])->name('data');
        Route::get('/outlet', [OutletController::class, 'getOutlet'])->name('outlet');
        Route::get('/coverage', [OutletController::class, 'getCoverage'])->name('coverage');
        Route::get('/outlet-by-coverage/{coverageId}', [OutletController::class, 'getOutletByCoverage'])->name('outlet.by.coverage');
        Route::post('/', [OutletController::class, 'store'])->name('store');
        Route::get('/{id}', [OutletController::class, 'show'])->name('show');
        Route::put('/{id}', [OutletController::class, 'update'])->name('update');
        Route::post('/bulk-update', [OutletController::class, 'bulkUpdateCoverage'])->name('bulk.update');
        Route::delete('/{id}', [OutletController::class, 'destroy'])->name('destroy');
    });

    // Selling In
    Route::prefix('selling-in')->name('selling-in.')->group(function () {
        Route::get('/', [SellingInController::class, 'index'])->name('index');
        Route::post('summary',      [SellingInController::class, 'getSummaryCards'])->name('summary');
        Route::post('chart/hari',   [SellingInController::class, 'getChartPerHari'])->name('chart.hari');
        Route::post('chart/bulan',  [SellingInController::class, 'getChartPerBulan'])->name('chart.bulan');
        Route::post('chart/pilar',  [SellingInController::class, 'getChartPerPilar'])->name('chart.pilar');
        Route::post('top-pm',       [SellingInController::class, 'getTopPm'])->name('top-pm');
        Route::post('top-produk',   [SellingInController::class, 'getTopProduk'])->name('top-produk');
        Route::post('table',        [SellingInController::class, 'getTableDetail'])->name('table');
        Route::get('pm-lookup',     [SellingInController::class, 'getPmLookup'])->name('pm-lookup');
    });

    //Stock
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/',  [StockController::class, 'index'])->name('index');

        Route::prefix('api')->name('api.')->group(function () {
            Route::post('dashboard',         [StockController::class, 'getDashboard'])->name('dashboard');
            Route::post('table',              [StockController::class, 'getTableDetail'])->name('table');
            Route::get('filter-options',      [StockController::class, 'getFilterOptions'])->name('filter-options');
        });
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
