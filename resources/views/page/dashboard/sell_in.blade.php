@extends('layouts.app', ['title' => 'Monitoring Selling In'])

@section('content')
    <x-filter-bar :months="$months" :years="$years" :pilars="$pilars" title="Monitoring Selling In"
        subtitle="Pantau performa selling in berdasarkan data transaksi terbaru" icon="fa-chart-line" id-bulan="filterBulan"
        id-tahun="filterTahun" id-pilar="filterPilar" id-apply="btnApply">
        <div class="col mb-4">
            <label class="small text-muted mb-1">Produk</label>
            <select id="filterProduct" class="form-control select2-product" style="width: 100%;">
                <option value="">Semua produk</option>
            </select>
        </div>
    </x-filter-bar>

    @php
        $sellingInKpis = [
            ['id' => 'kpiNetto', 'label' => 'Total Selling In', 'icon' => 'fa-money-bill-wave'],
            ['id' => 'kpiSku', 'label' => 'SKU Terjual', 'icon' => 'fa-box'],
            ['id' => 'kpiTransaksi', 'label' => 'Total Transaksi', 'icon' => 'fa-receipt'],
        ];
    @endphp

    <div class="row g-3 mb-4" id="kpiCards">
        @foreach ($sellingInKpis as $card)
            <div class="col">
                <x-stat-card title="{{ $card['label'] }}" value="" valueId="{{ $card['id'] }}" subtitle="-"
                    subtitleId="{{ $card['id'] }}Sub" subtitleColor="metric-growth" icon="fas {{ $card['icon'] }}"
                    color="success" />
            </div>
        @endforeach
    </div>

    <div class="card card-custom mb-4">
        <div class="card-header">
            <div class="card-title">
                <h3 class="card-label">Tren Selling In</h3>
            </div>
            <div class="card-toolbar">
                <div class="btn-group" id="chartModeToggle">
                    <button class="btn btn-sm btn-outline-primary active font-size-xs" data-mode="hari">Per Hari</button>
                    <button class="btn btn-sm btn-outline-primary font-size-xs" data-mode="bulan">Per Bulan</button>
                </div>
            </div>
        </div>
        <div class="card-body p-3">
            <div id="chartTrend" style="height:220px"></div>
        </div>
    </div>

    <div class="row g-3 mb-4 h-auto">
        <div class="col-md-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">by PM</h3>
                    </div>
                </div>
                <div class="card-body p-3 si-chart-card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small" id="topPmPeriod"></span>
                    </div>
                    <div id="chartTopPm" class="si-chart-fill"></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">by Pilar</h3>
                    </div>
                </div>
                <div class="card-body p-3 si-chart-card-body">
                    <div id="chartPilar" class="mb-3"></div>
                    <div id="legendPilar" class="row no-gutters" style="gap: 8px 0;"></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">by Produk</h3>
                    </div>
                </div>
                <div class="card-body p-3 si-chart-card-body">
                    <div id="chartTopProduk" class="si-funnel-wrap"></div>
                </div>
            </div>
        </div>
    </div>

    <x-datatable-card title="Detail transaksi" subtitle="" table-id="tableDetail" table-class="table-sm">
        <th>No</th>
        <th>Tanggal</th>
        <th>Kode</th>
        <th>Nama Produk</th>
        <th>Pilar</th>
        <th class="text-end">Qty</th>
        <th class="text-end">Harga</th>
        <th class="text-end">Netto</th>
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
            ],
            'csrf' => csrf_token(),
        ];
    @endphp

    <script>
        window.SellingInDashboardConfig = @json($sellingInDashboardConfig);
    </script>
    <script src="{{ asset('js/dashboard/selling-in.js') }}?v={{ filemtime(public_path('js/dashboard/selling-in.js')) }}">
    </script>
@endpush
