@extends('layouts.app', ['title' => 'Dashboard Performance Selling Out'])

@push('styles')
    <link href="{{ asset('css/datatables.bundle.css') }}" rel="stylesheet" />
    <link rel="stylesheet"
        href="{{ asset('css/dashboard/index.css') }}?v={{ filemtime(public_path('css/dashboard/index.css')) }}" />
@endpush


@section('content')
    {{-- ===== FILTER BAR ===== --}}
    <x-filter-bar :months="$months" :years="range(now()->year, now()->year - 3)" :pilars="$pilars" title="Monitoring Selling Out" icon="fa-shopping-cart"
        :show-bulan="false" :show-tahun="false" :show-period="true" :show-distributor="true" :show-reset="true"
        id-reset="btnResetSellingOut">
        <div class="col-12 col-sm-auto mb-2 p-0" style="min-width: 160px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Produk</label>
            <select id="filter_product" class="form-control form-control-sm select2-product" style="width: 100%;"
                multiple></select>
        </div>
    </x-filter-bar>

    {{-- ===== SUMMARY CARDS ===== --}}
    <div class="row">
        <div class="col-md-4 mb-5">
            <x-stat-card title="Total Netto" value="" valueId="card_netto" subtitle="-" subtitleId="card_growth"
                subtitleColor="metric-growth" icon="fas fa-coins" color="success" />
        </div>
        <div class="col-md-4 mb-5">
            <x-stat-card title="TOP" value="" valueId="card_top" icon="fas fa-bullseye" color="primary" />
        </div>
        <div class="col-md-4 mb-5">
            <x-stat-card title="Achievement" value="" valueId="card_top_achievement" icon="fas fa-chart-line"
                color="warning" />
        </div>
    </div>


    {{-- ===== ACHIEVEMENT RACE ===== --}}
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-custom">
                <div class="card-body">
                    <div id="chartAchievementRace" class="achievement-race">
                        <div class="achievement-race__head">
                            <div class="achievement-race__title-group">
                                <div class="btn btn-light-primary font-weight-bold"><i class="fas fa-trophy"></i></div>
                                <div class="achievement-race__title">Achievement RM</div>
                            </div>
                            <div class="achievement-race__legend">
                                <span class="achievement-race__legend-swatch"></span>
                                <span>Achievement</span>
                            </div>
                        </div>
                        <div class="achievement-race__body">
                            <div id="achievementRaceRows">
                                <div class="achievement-race__empty">Memuat achievement...</div>
                            </div>
                            <div class="achievement-race__target-line"></div>
                            <div class="achievement-race__target-label">Finish</div>
                        </div>
                    </div>
                </div>
            </div>
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
                        <div class="btn-group" role="group">
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

    {{-- ===== ROW 2: Segment + Divisi + Pilar ===== --}}
    <div class="row">
        <div class="col-lg-4 col-md-12 mb-5">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label font-size-h5 font-size-xl-h4">Netto per Divisi</h3>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center p-2 p-xl-3">
                    <div id="chartPerDivisi" class="dashboard-donut-chart"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12 mb-5">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label font-size-h5 font-size-xl-h4">Netto per Pilar</h3>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center p-2 p-xl-3">
                    <div id="chartPerPilar" style="width:100%"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12 mb-5">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label font-size-h5 font-size-xl-h4">Netto per Segment</h3>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center p-2 p-xl-3">
                    <div id="chartPerSegment" style="width:100%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== ROW 5 & 6: Tabel Detail ===== --}}
    <x-datatable-card title="Detail Performance per Anggota" table-id="tblAnggota" table-class="table-sm" :load-assets="false"
        :collapsible="true" :collapsed="false">
        <th>No</th>
        <th>NIK</th>
        <th>Nama</th>
        <th>Level</th>
        <th>Pilar</th>
        <th>Divisi</th>
        <th>TOP</th>
        <th>Real Netto</th>
        <th>% Ach</th>
        <th>Aksi</th>
    </x-datatable-card>
@endsection

@push('scripts')
    <script src="{{ asset('js/datatables.bundle.js') }}"></script>
    <script
        src="{{ asset('js/dashboard/date-range-filter.js') }}?v={{ filemtime(public_path('js/dashboard/date-range-filter.js')) }}">
    </script>
    @include('page.dashboard.partials.scripts')
@endpush
