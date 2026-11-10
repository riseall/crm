@extends('layouts.app', ['title' => 'Monitoring Selling In'])

@section('content')
    <x-filter-bar :months="$months" :years="$years" :pilars="$pilars" title="Monitoring Selling In"
        subtitle="Pantau performa selling in berdasarkan data transaksi terbaru" icon="fa-chart-line" :show-bulan="false"
        :show-tahun="false" :show-period="true" id-start-date="filterStartDate" id-end-date="filterEndDate" id-pilar="filterPilar"
        id-apply="btnApply" :show-reset="true" id-reset="btnResetSellingIn">
        <div class="filter-item filter-item-product">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Produk</label>
            <select id="filterProduct" class="form-control form-control-sm select2-product" style="width: 100%;" multiple></select>
        </div>
        <div class="filter-item">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">PM</label>
            <select id="filterPm" class="form-control form-control-sm select2-pm" style="width: 100%;" multiple></select>
        </div>
    </x-filter-bar>

    <div class="row mb-3">
        <div class="col-lg-4 col-md-12 mb-3">
            <x-stat-card title="Total Selling In" value="" valueId="kpiNetto" subtitle="-" subtitleId="kpiNettoSub"
                subtitleColor="metric-growth" icon="fas fa-money-bill-wave" color="success" />
        </div>
        <div class="col-lg-4 col-md-12 mb-3">
            <x-stat-card title="Selling In YTD" value="" valueId="kpiYtd" subtitle="-" subtitleId="kpiYtdSub"
                subtitleColor="metric-growth" icon="fas fa-calendar-check" color="primary" />
        </div>
        <div class="col-lg-4 col-md-12 mb-3">
            <x-stat-card title="Growth YTD" value="" valueId="kpiYtdGrowth" subtitle="-"
                subtitleId="kpiYtdGrowthSub" subtitleColor="metric-growth" icon="fas fa-chart-line" color="warning" />
        </div>
    </div>

    <div class="card card-custom mb-3">
        <div class="card-header">
            <div class="card-title">
                <h3 class="card-label font-size-h5">Tren Selling In</h3>
            </div>
            <div class="card-toolbar">
                <div class="btn-group" id="chartModeToggle">
                    <button class="btn btn-sm btn-outline-primary active font-size-xs" data-mode="hari">Per Hari</button>
                    <button class="btn btn-sm btn-outline-primary font-size-xs" data-mode="bulan">Per Bulan</button>
                </div>
            </div>
        </div>
        <div class="card-body p-2 p-xl-3">
            <div id="chartTrend" style="height:220px"></div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-4 col-md-12 mb-3">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label font-size-h5">by PM</h3>
                    </div>
                </div>
                <div class="card-body p-2 p-xl-3 si-chart-card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small" id="topPmPeriod"></span>
                    </div>
                    <div id="chartTopPm" class="si-chart-fill"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12 mb-3">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label font-size-h5">by Pilar</h3>
                    </div>
                </div>
                <div class="card-body p-2 p-xl-3 si-chart-card-body">
                    <div id="chartPilar" class="mb-2"></div>
                    <div id="legendPilar" class="row no-gutters" style="gap: 6px 0;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12 mb-3">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label font-size-h5">by Produk</h3>
                    </div>
                </div>
                <div class="card-body p-2 p-xl-3 si-chart-card-body">
                    <div id="chartTopProduk" class="si-funnel-wrap"></div>
                </div>
            </div>
        </div>
    </div>

    <x-datatable-card title="Detail transaksi" subtitle="" table-id="tableDetail" table-class="table-sm"
        :collapsible="true" :collapsed="true">
        <th>No</th>
        <th>Tanggal</th>
        <th>Kode</th>
        <th>Nama Produk</th>
        <th>Pilar</th>
        <th>PM</th>
        <th class="text-end">Qty</th>
        <th class="text-end">Harga</th>
        <th class="text-end">Value</th>
    </x-datatable-card>
@endsection

@push('styles')
    <link rel="stylesheet"
        href="{{ asset('css/dashboard/selling-in.css') }}?v={{ filemtime(public_path('css/dashboard/selling-in.css')) }}">
@endpush

@push('scripts')
    @php
        $sellingInDashboardConfig = [
            'routes' => [
                'summary' => route('selling-in.summary'),
                'chartHari' => route('selling-in.chart.hari'),
                'chartBulan' => route('selling-in.chart.bulan'),
                'chartPilar' => route('selling-in.chart.pilar'),
                'topPm' => route('selling-in.top-pm'),
                'topProduk' => route('selling-in.top-produk'),
                'table' => route('selling-in.table'),
                'productLookup' => route('product.produk'),
                'pmLookup' => route('selling-in.pm-lookup'),
            ],
            'csrf' => csrf_token(),
        ];
    @endphp

    <script>
        window.SellingInDashboardConfig = @json($sellingInDashboardConfig);
    </script>
    <script
        src="{{ asset('js/dashboard/date-range-filter.js') }}?v={{ filemtime(public_path('js/dashboard/date-range-filter.js')) }}">
    </script>
    <script src="{{ asset('js/dashboard/selling-in.js') }}?v={{ filemtime(public_path('js/dashboard/selling-in.js')) }}">
    </script>
@endpush
