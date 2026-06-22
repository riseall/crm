<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Cache::flush();

$rawTotal = DB::connection('db_dwh')
    ->table('crm_top_marketing')
    ->where('tahun', 2026)
    ->where('bulan', 6)
    ->sum('val_net');

$rawRegion1 = DB::connection('db_dwh')
    ->table('crm_top_marketing')
    ->where('tahun', 2026)
    ->where('bulan', 6)
    ->where('divisi', 'REGULER')
    ->where('region', '1')
    ->sum('val_net');

$request = App\Http\Requests\DashboardFilterRequest::create('/dashboard/summary', 'GET', [
    'start_date' => '2026-06-01',
    'end_date' => '2026-06-30',
]);
$request->setContainer($app)->setRedirector($app->make('redirect'));
$request->validateResolved();

$sellout = $app->make(App\Services\SelloutQueryService::class);

echo 'raw_total=' . number_format((float) $rawTotal, 0, '.', '') . PHP_EOL;
echo 'service_total=' . number_format($sellout->topMarketingTarget($request), 0, '.', '') . PHP_EOL;
echo 'raw_region_1=' . number_format((float) $rawRegion1, 0, '.', '') . PHP_EOL;

$regionRows = $sellout->topMarketingByRegion($request)
    ->filter(fn($row) => $row->divisi === 'REGULER' && (string) $row->region === '1')
    ->values();

echo 'service_region_1=' . number_format((float) ($regionRows[0]->top_value ?? 0), 0, '.', '') . PHP_EOL;

echo PHP_EOL . 'all_months_2026=' . PHP_EOL;
DB::connection('db_dwh')
    ->table('crm_top_marketing')
    ->selectRaw('bulan, SUM(val_net) as total')
    ->where('tahun', 2026)
    ->groupBy('bulan')
    ->orderBy('bulan')
    ->get()
    ->each(function ($row) {
        echo $row->bulan . '=' . number_format((float) $row->total, 0, '.', '') . PHP_EOL;
    });

echo PHP_EOL . 'region_1_without_divisi=' . PHP_EOL;
DB::connection('db_dwh')
    ->table('crm_top_marketing')
    ->selectRaw('tahun, bulan, SUM(val_net) as total')
    ->where('region', '1')
    ->groupBy('tahun', 'bulan')
    ->orderBy('tahun')
    ->orderBy('bulan')
    ->get()
    ->each(function ($row) {
        echo $row->tahun . '-' . $row->bulan . '=' . number_format((float) $row->total, 0, '.', '') . PHP_EOL;
    });

echo PHP_EOL . 'by_region_month_6=' . PHP_EOL;
DB::connection('db_dwh')
    ->table('crm_top_marketing')
    ->selectRaw('divisi, region, SUM(val_net) as total')
    ->where('tahun', 2026)
    ->where('bulan', 6)
    ->groupBy('divisi', 'region')
    ->orderBy('divisi')
    ->orderBy('region')
    ->get()
    ->each(function ($row) {
        echo $row->divisi . '|' . $row->region . '=' . number_format((float) $row->total, 0, '.', '') . PHP_EOL;
    });

echo PHP_EOL . 'reguler_breakdown_month_6=' . PHP_EOL;
DB::connection('db_dwh')
    ->table('crm_top_marketing')
    ->selectRaw('divisi, region, pilar, segment, unit, pm, COUNT(*) as rows_count, SUM(val_net) as total')
    ->where('tahun', 2026)
    ->where('bulan', 6)
    ->where('divisi', 'REGULER')
    ->groupBy('divisi', 'region', 'pilar', 'segment', 'unit', 'pm')
    ->orderBy('region')
    ->orderByDesc('total')
    ->limit(50)
    ->get()
    ->each(function ($row) {
        echo implode('|', [
            $row->divisi,
            $row->region,
            $row->pilar,
            $row->segment,
            $row->unit,
            $row->pm,
            $row->rows_count,
            number_format((float) $row->total, 0, '.', ''),
        ]) . PHP_EOL;
    });

echo PHP_EOL . 'tables_like_top=' . PHP_EOL;
foreach (DB::connection('db_dwh')->select("SHOW TABLES LIKE '%top%'") as $row) {
    echo implode('|', (array) $row) . PHP_EOL;
}

echo PHP_EOL . 'top_marketing_columns=' . PHP_EOL;
print_r(Schema::connection('db_dwh')->getColumnListing('top_marketing'));

echo PHP_EOL . 'top_marketing_month_6=' . PHP_EOL;
DB::connection('db_dwh')
    ->table('top_marketing')
    ->selectRaw('divisi, region, SUM(val_net) as total')
    ->where('tahun', 2026)
    ->where('bulan', 6)
    ->groupBy('divisi', 'region')
    ->orderBy('divisi')
    ->orderBy('region')
    ->get()
    ->each(function ($row) {
        echo $row->divisi . '|' . $row->region . '=' . number_format((float) $row->total, 0, '.', '') . PHP_EOL;
    });

echo 'top_marketing_total_6=' . number_format((float) DB::connection('db_dwh')
    ->table('top_marketing')
    ->where('tahun', 2026)
    ->where('bulan', 6)
    ->sum('val_net'), 0, '.', '') . PHP_EOL;

echo PHP_EOL . 'region_1_all_months_2026=' . PHP_EOL;
DB::connection('db_dwh')
    ->table('crm_top_marketing')
    ->selectRaw('bulan, SUM(val_net) as total')
    ->where('tahun', 2026)
    ->where('divisi', 'REGULER')
    ->where('region', '1')
    ->groupBy('bulan')
    ->orderBy('bulan')
    ->get()
    ->each(function ($row) {
        echo $row->bulan . '=' . number_format((float) $row->total, 0, '.', '') . PHP_EOL;
    });
