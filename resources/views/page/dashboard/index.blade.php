@extends('layouts.app', ['title' => 'Dashboard Performance Selling Out'])

@push('styles')
    <link href="{{ asset('css/datatables.bundle.css') }}" rel="stylesheet" />
    <style>
        /* ===== Level Badges ===== */
        .level-badge {
            font-size: .75rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        .level-GM {
            background: #1BC5BD22;
            color: #1BC5BD;
        }

        .level-RM {
            background: #3699FF22;
            color: #3699FF;
        }

        .level-SAM {
            background: #8950FC22;
            color: #8950FC;
        }

        .level-AM {
            background: #FFA80022;
            color: #FFA800;
        }

        .level-MR {
            background: #F64E6022;
            color: #F64E60;
        }

        /* ===== Chart Toggle ===== */
        .chart-mode-toggle .btn {
            font-size: .78rem;
            padding: 3px 12px;
        }

        .chart-mode-toggle .btn.active {
            background: #3699FF;
            color: #fff;
            border-color: #3699FF;
        }

        /* ===== Drill-down ===== */
        .drill-path {
            font-size: .82rem;
            color: #7E8299;
        }

        .drill-path .separator {
            margin: 0 6px;
        }

        .drill-path .crumb {
            cursor: pointer;
            color: #3699FF;
        }

        .drill-path .crumb:hover {
            text-decoration: underline;
        }

        .drill-path .crumb.active {
            color: #181C32;
            font-weight: 600;
            cursor: default;
        }

        /* ===== Growth ===== */
        .metric-growth.up {
            color: #1BC5BD;
        }

        .metric-growth.down {
            color: #F64E60;
        }

        .dashboard-donut-chart {
            width: 100%;
            min-height: 260px;
        }

        @media (min-width: 768px) {
            .dashboard-donut-chart {
                min-height: 300px;
            }
        }

        @media (max-width: 575.98px) {
            .dashboard-donut-chart {
                min-height: 340px;
            }
        }
    </style>
@endpush

@section('content')
    {{-- ===== FILTER BAR ===== --}}
    <x-filter-bar :months="$months" :years="range(now()->year, now()->year - 3)" :pilars="$pilars" title="Monitoring Selling Out" icon="fa-shopping-cart"
        :show-bulan="false" :show-tahun="false" :show-period="true" :show-distributor="true" />

    {{-- ===== SUMMARY CARDS ===== --}}
    <div class="row mb-5">
        <div class="col-lg-6 col-md-6">
            <x-stat-card title="Total Netto" value="" valueId="card_netto" subtitle="-" subtitleId="card_growth"
                subtitleColor="metric-growth" icon="fas fa-coins" color="success" />
        </div>
        <div class="col-lg-3 col-md-3">
            <x-stat-card title="Total Outlet" value="" valueId="card_outlet" icon="fas fa-store" color="primary" />
        </div>
        {{-- <div class="col col-lg col-md-6">
            <x-stat-card title="Total Anggota" value="" valueId="card_anggota" icon="fas fa-users" color="danger" />
        </div> --}}
        <div class="col-lg-3 col-md-3">
            <x-stat-card title="Total Transaksi" value="" valueId="card_transaksi" icon="fas fa-exchange-alt"
                color="warning" />
        </div>
    </div>

    {{-- ===== ROW 1: Chart Trend ===== --}}
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-custom">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label" id="chartTrendTitle">Netto per Hari</h3>
                    </div>
                    <div class="card-toolbar">
                        <div class="btn-group chart-mode-toggle" role="group">
                            <button id="btnModeHari" class="btn btn-sm btn-outline-primary active">Per Hari</button>
                            <button id="btnModeBulan" class="btn btn-sm btn-outline-primary">Per Bulan</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chartPerBulan"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== ROW 2: Divisi + Pilar ===== --}}
    <div class="row mb-5">
        <div class="col-md-6 mb-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Netto per Divisi</h3>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div id="chartPerDivisi" class="dashboard-donut-chart"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Netto per Pilar</h3>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div id="chartPerPilar" style="width:100%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== ROW 3: Top 10 Coverage ===== --}}
    {{-- <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-custom">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Top 10 Coverage by Netto</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chartPerCoverage"></div>
                </div>
            </div>
        </div>
    </div> --}}

    {{-- ===== ROW 4: Drill-Down Hierarki ===== --}}
    @if ($dashboardAccess['can_view_drilldown'] ?? true)
        @include('page.dashboard.partials.drilldown')
    @endif

    {{-- ===== ROW 5 & 6: Tabel Detail ===== --}}
    <x-datatable-card title="Detail Performance per Anggota" subtitle="Data anggota sesuai filter yang dipilih"
        table-id="tblAnggota" table-class="table-sm" :load-assets="false">
        <th>No</th>
        <th>NIK</th>
        <th>Nama</th>
        <th>Level</th>
        <th>Pilar</th>
        <th>Divisi</th>
        <th>Coverage</th>
        <th>Outlet</th>
        <th>Transaksi</th>
        <th>Netto</th>
    </x-datatable-card>
@endsection

@push('scripts')
    <script src="{{ asset('js/datatables.bundle.js') }}"></script>
    <script
        src="{{ asset('js/dashboard/date-range-filter.js') }}?v={{ filemtime(public_path('js/dashboard/date-range-filter.js')) }}">
    </script>
    @include('page.dashboard.partials.scripts')
@endpush
