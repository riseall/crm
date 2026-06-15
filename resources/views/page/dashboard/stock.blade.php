@extends('layouts.app', ['title' => 'Stock GPJ vs Cabang'])

@section('content')
    <x-filter-bar title="Monitoring Stock GPJ vs Cabang"
        subtitle="Pantau stock GPJ dan distributor cabang berdasarkan aging ED" icon="fa-database" :show-bulan="false"
        :show-tahun="false" :show-pilar="false" id-apply="btnApply" btn-icon="fa-sync-alt">
        <div class="col-6 col-sm-4 col-lg-3 mb-4">
            <label class="small text-muted mb-1">Distributor</label>
            <select class="form-control selectpicker" id="filterSource" data-width="100%" multiple
                data-actions-box="true" data-selected-text-format="count > 1"
                data-none-selected-text="Semua distributor" data-select-all-text="Pilih semua"
                data-deselect-all-text="Hapus semua" data-count-selected-text="{0} dipilih">
                <option value="GPJ">GPJ</option>
                <option value="AAM">AAM</option>
                <option value="KFTD">KFTD</option>
                <option value="METD">METD</option>
            </select>
        </div>

        <div class="col-6 col-sm-4 col-lg-3 mb-4">
            <label class="small text-muted mb-1">Pilar</label>
            <select class="form-control selectpicker" id="filterPilar" data-width="100%" multiple
                data-actions-box="true" data-selected-text-format="count > 1"
                data-none-selected-text="Semua pilar" data-select-all-text="Pilih semua"
                data-deselect-all-text="Hapus semua" data-count-selected-text="{0} dipilih"></select>
        </div>

        <div class="col-6 col-sm-4 col-lg-3 mb-4">
            <label class="small text-muted mb-1">Cabang</label>
            <select class="form-control selectpicker" id="filterCabang" data-width="100%" data-live-search="true"
                multiple data-actions-box="true" data-selected-text-format="count > 1"
                data-none-selected-text="Semua cabang" data-select-all-text="Pilih semua"
                data-deselect-all-text="Hapus semua" data-count-selected-text="{0} dipilih"></select>
        </div>

        <div class="col-6 col-sm-4 col-lg-3 mb-4">
            <label class="small text-muted mb-1">Aging</label>
            <select class="form-control selectpicker" id="filterKelEd" data-width="100%" multiple
                data-actions-box="true" data-selected-text-format="count > 1" data-none-selected-text="Semua aging"
                data-select-all-text="Pilih semua" data-deselect-all-text="Hapus semua"
                data-count-selected-text="{0} dipilih">
                <option value="sudah_ed">Sudah ED</option>
                <option value="1_3">1-3 Bulan</option>
                <option value="4_7">4-7 Bulan</option>
                <option value="8_12">8-12 Bulan</option>
                <option value="13_17">13-17 Bulan</option>
                <option value="gt_18">&gt;18 Bulan</option>
            </select>
        </div>

    </x-filter-bar>

    <div class="sk-page">
        @php
            $stockKpis = [
                ['id' => 'kpiGpj', 'label' => 'Stock GPJ', 'icon' => 'fa-industry', 'color' => 'primary'],
                ['id' => 'kpiCabang', 'label' => 'Stock Cabang', 'icon' => 'fa-truck', 'color' => 'success'],
                [
                    'id' => 'kpiKritis',
                    'label' => 'Kritis (ED 1-3 bulan)',
                    'icon' => 'fa-exclamation-triangle',
                    'color' => 'warning',
                ],
                ['id' => 'kpiEd', 'label' => 'Sudah ED', 'icon' => 'fa-calendar-times', 'color' => 'danger'],
            ];
        @endphp

        <div class="row g-3 mb-4">
            @foreach ($stockKpis as $card)
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat-card title="{{ $card['label'] }}" value="" valueId="{{ $card['id'] }}" subtitle="-"
                        subtitleId="{{ $card['id'] }}Sub" subtitleColor="text-muted" icon="fas {{ $card['icon'] }}"
                        color="{{ $card['color'] }}" />
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4 mb-4">
                <div class="card card-custom h-100 sk-equal-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Per distributor</h3>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="sourceList" class="sk-list-stack"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card card-custom h-100 sk-equal-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Aging stock keseluruhan</h3>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="chartAging" class="sk-chart"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card card-custom h-100 sk-equal-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Top cabang</h3>
                            <span class="d-block text-muted pt-2 font-size-sm">Nilai gabungan AAM, KFTD, METD</span>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="chartTopCabang" class="sk-chart"></div>
                    </div>
                </div>
            </div>
        </div>

        <x-datatable-card title="Detail Stock" subtitle="GPJ vs Cabang, diurutkan dari aging paling kritis"
            table-id="tableStock" :show-export="true" table-class="table-separate table-head-custom table-checkable table-sm">
            <th>Dist</th>
            <th>Kode</th>
            <th>Nama Produk</th>
            <th>Pilar</th>
            <th>Cabang</th>
            <th>Stock</th>
            <th>Nilai</th>
            <th>Exp. Date</th>
            <th>Kelompok ED</th>
        </x-datatable-card>
    </div>
@endsection

@push('styles')
    <style>
        .sk-bar-track {
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #EEF2FF;
        }

        .sk-bar-fill {
            height: 100%;
            border-radius: inherit;
            transition: width .3s ease;
        }

        .sk-equal-card .card-body {
            min-height: 330px;
        }

        .sk-chart {
            min-height: 280px;
        }

        .sk-list-stack {
            display: flex;
            min-height: 280px;
            flex-direction: column;
            justify-content: space-between;
        }

        .sk-list-stack .sk-list-item:last-child {
            margin-bottom: 0 !important;
        }

        .sk-src-badge {
            display: flex;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .sk-list-item {
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            background: #fff;
        }
    </style>
@endpush

@push('scripts')
    @php
        $stockDashboardConfig = [
            'routes' => [
                'summary' => route('stock.api.summary'),
                'chartAging' => route('stock.api.chart.aging'),
                'perSource' => route('stock.api.per-source'),
                'topCabang' => route('stock.api.top-cabang'),
                'table' => route('stock.api.table'),
                'filterOptions' => route('stock.api.filter-options'),
            ],
            'csrf' => csrf_token(),
        ];
    @endphp

    <script>
        window.StockDashboardConfig = @json($stockDashboardConfig);
    </script>
    <script
        src="{{ asset('js/dashboard/1_helper_rupiah.js') }}?v={{ filemtime(public_path('js/dashboard/1_helper_rupiah.js')) }}">
    </script>
    <script src="{{ asset('js/dashboard/stock.js') }}?v={{ filemtime(public_path('js/dashboard/stock.js')) }}"></script>
@endpush
