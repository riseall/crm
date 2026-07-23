@extends('layouts.app', ['title' => 'Stock GPJ vs Cabang'])

@section('content')
    <x-filter-bar title="Monitoring Stock GPJ vs Cabang"
        subtitle="Pantau stock GPJ dan distributor cabang berdasarkan aging ED" icon="fa-database" :show-bulan="false"
        :show-tahun="false" :show-pilar="false" id-apply="btnApply" :show-reset="true" id-reset="btnResetStock">
        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 140px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Distributor</label>
            <select class="form-control form-control-sm selectpicker" id="filterSource" data-width="100%" multiple data-actions-box="true"
                data-selected-text-format="count > 1" data-none-selected-text="Semua distributor"
                data-select-all-text="Pilih semua" data-deselect-all-text="Hapus semua"
                data-count-selected-text="{0} dipilih">
                <option value="GPJ">GPJ</option>
                <option value="AAM">AAM</option>
                <option value="KFTD">KFTD</option>
                <option value="METD">METD</option>
            </select>
        </div>

        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 130px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Pilar</label>
            <select class="form-control form-control-sm selectpicker" id="filterPilar" data-width="100%" multiple data-actions-box="true"
                data-selected-text-format="count > 1" data-none-selected-text="Semua pilar"
                data-select-all-text="Pilih semua" data-deselect-all-text="Hapus semua"
                data-count-selected-text="{0} dipilih"></select>
        </div>

        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 135px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Cabang</label>
            <select class="form-control form-control-sm selectpicker" id="filterCabang" data-width="100%" data-live-search="true" multiple
                data-actions-box="true" data-selected-text-format="count > 1" data-none-selected-text="Semua cabang"
                data-select-all-text="Pilih semua" data-deselect-all-text="Hapus semua"
                data-count-selected-text="{0} dipilih"></select>
        </div>

        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 125px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Aging</label>
            <select class="form-control form-control-sm selectpicker" id="filterKelEd" data-width="100%" multiple data-actions-box="true"
                data-selected-text-format="count > 1" data-none-selected-text="Semua aging"
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
        <div role="status" aria-live="polite"
            style="
                display:flex; align-items:stretch;
                border-radius:8px; overflow:hidden;
                border:0.5px solid #FBBF24;
                background:#ffffff;
                margin-bottom:0.85rem;
                box-shadow:0 1px 3px rgba(0,0,0,0.05);
            ">
            {{-- Label kiri --}}
            <div
                style="
                    display:flex; flex-direction:column; align-items:center; justify-content:center;
                    gap:4px; background:#F59E0B; color:#451a03;
                    padding:8px 12px; min-width:75px; text-align:center; flex-shrink:0;
                ">
                <i class="fas fa-exclamation-triangle text-dark" style="font-size:15px"></i>
                <span style="font-size:9.5px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase;">
                    Stok ED
                </span>
            </div>

            {{-- Ticker viewport --}}
            <div class="sk-warning-viewport" style="flex:1; overflow:hidden; position:relative;">
                <div class="sk-warning-track" id="stockWarningTrack">
                    <div class="sk-warning-group"
                        style="display:inline-flex; align-items:center; padding:0 16px; height:100%;">
                        <span style="font-size:12px; color:#64748b;">
                            Memuat peringatan stok&hellip;
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card card-custom h-100 sk-equal-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label font-size-h5">Per distributor</h3>
                        </div>
                    </div>
                    <div class="card-body p-2 p-xl-3">
                        <div id="sourceList" class="sk-list-stack"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card card-custom h-100 sk-equal-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label font-size-h5">Aging stock</h3>
                        </div>
                    </div>
                    <div class="card-body p-2 p-xl-3">
                        <div id="chartAging" class="sk-chart"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card card-custom h-100 sk-equal-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label font-size-h5">Top cabang</h3>
                        </div>
                    </div>
                    <div class="card-body p-2 p-xl-3">
                        <div id="chartTopCabang" class="sk-chart"></div>
                    </div>
                </div>
            </div>
        </div>

        <x-datatable-card title="Detail Stock" table-id="tableStock"
            table-class="table-separate table-head-custom table-checkable table-sm" :collapsible="true" :collapsed="true">
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
            justify-content: start;
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

        .sk-warning-bar {
            display: flex;
            min-height: 58px;
            overflow: hidden;
            align-items: stretch;
            border: 1px solid #F6C343;
            border-radius: 8px;
            background: #FFFBEB;
        }

        .sk-warning-label {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            padding: 0 20px;
            background: #F59E0B;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            z-index: 1;
        }

        .sk-warning-viewport {
            display: flex;
            min-width: 0;
            flex: 1;
            overflow: hidden;
            align-items: center;
        }

        .sk-warning-track {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            will-change: transform;
        }

        .sk-warning-track.is-running {
            animation-name: stock-warning-scroll;
            animation-duration: 30s;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        .sk-warning-viewport:hover .sk-warning-track {
            animation-play-state: paused;
        }

        .sk-warning-group {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
        }

        .sk-warning-item {
            display: flex;
            min-height: 56px;
            align-items: center;
            padding: 0 28px;
            color: #78350F;
            font-size: 13px;
            white-space: nowrap;
        }

        .sk-warning-item::after {
            width: 5px;
            height: 5px;
            margin-left: 28px;
            border-radius: 50%;
            background: #F59E0B;
            content: '';
        }

        .sk-warning-item strong {
            margin: 0 4px;
            color: #92400E;
        }

        @keyframes stock-warning-scroll {
            from {
                transform: translateX(0);
            }

            to {
                transform: translate3d(-50%, 0, 0);
            }
        }

        @media (max-width: 767.98px) {
            .sk-warning-bar {
                display: block;
            }

            .sk-warning-label {
                min-height: 42px;
                justify-content: center;
            }
        }
    </style>
@endpush

@push('scripts')
    @php
        $stockDashboardConfig = [
            'routes' => [
                'dashboard' => route('stock.api.dashboard'),
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
