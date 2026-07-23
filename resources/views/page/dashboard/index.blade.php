@extends('layouts.app', ['title' => 'Dashboard Performance Selling Out'])

@push('styles')
    <link href="{{ asset('css/datatables.bundle.css') }}" rel="stylesheet" />
    <style>
        /* ===== Level Badges ===== */
        .level-badge {
            font-size: .75rem;
            padding: 3px 10px;
            border-radius: 100px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .level-GM {
            background: #E1F5EE;
            color: #085041;
        }

        .level-RM {
            background: #E6F1FB;
            color: #0C447C;
        }

        .level-SAM {
            background: #EEEDFE;
            color: #3C3489;
        }

        .level-AM {
            background: #FAEEDA;
            color: #633806;
        }

        .level-MR {
            background: #FCEBEB;
            color: #791F1F;
        }

        /* ===== Chart Toggle ===== */
        .chart-mode-toggle .btn {
            font-size: .8rem;
            font-weight: 600;
            padding: 5px 16px;
            border-radius: 100px !important;
            border-color: #E1E4ED;
            color: #5B6477;
        }

        .chart-mode-toggle .btn:not(.active):hover {
            background: #F5F7FB;
            color: #3F4254;
        }

        .chart-mode-toggle .btn.active {
            background: #5B7CFA;
            color: #fff;
            border-color: #5B7CFA;
            box-shadow: 0 2px 6px rgba(91, 124, 250, .35);
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
            color: #185FA5;
            font-weight: 600;
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
            color: #0F6E56;
            font-weight: 700;
        }

        .metric-growth.down {
            color: #A32D2D;
            font-weight: 700;
        }

        .dashboard-donut-chart {
            width: 100%;
            min-height: 260px;
        }

        .achievement-race {
            background: var(--ar-surface, #fff);
        }

        .achievement-race__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .achievement-race__title-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* .achievement-race__icon {
                                                                        width: 34px;
                                                                        height: 34px;
                                                                        border-radius: 10px;
                                                                        background: #E6F1FB;
                                                                        color: #185FA5;
                                                                        display: flex;
                                                                        align-items: center;
                                                                        justify-content: center;
                                                                        font-size: 17px;
                                                                    } */

        .achievement-race__title {
            color: #181C32;
            font-size: 16px;
            font-weight: 700;
        }

        .achievement-race__legend {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #7E8299;
            font-size: 12px;
        }

        .achievement-race__legend-swatch {
            width: 18px;
            height: 8px;
            border-radius: 4px;
            background: #1D9E75;
        }

        .achievement-race__body {
            position: relative;
            padding-right: 38px;
        }

        .achievement-race__target-line {
            position: absolute;
            top: -6px;
            bottom: 4px;
            left: 100%;
            width: 18px;
            border: 1px solid #D1D5DB;
            border-radius: 2px;
            background:
                repeating-conic-gradient(#111827 0% 25%, #FFFFFF 0% 50%) 50% / 10px 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .12);
            transform: translateX(-50%);
        }

        .achievement-race__target-label {
            position: absolute;
            top: -22px;
            left: 100%;
            transform: translateX(-50%);
            font-size: 10px;
            font-weight: 600;
            color: #B5B9C6;
            white-space: nowrap;
        }

        .achievement-race__row {
            display: grid;
            grid-template-columns: minmax(84px, 130px) minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            min-height: 30px;
            margin-bottom: 14px;
        }

        .achievement-race__row:last-child {
            margin-bottom: 0;
        }

        .achievement-race__label {
            color: #5B6477;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1.25;
            text-align: right;
            word-break: break-word;
        }

        .achievement-race__track {
            position: relative;
            height: 26px;
            background: #F4F6FA;
            border-radius: 100px;
        }

        .achievement-race__bar {
            position: absolute;
            top: 0;
            left: 0;
            height: 26px;
            min-width: 46px;
            border-radius: 100px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            transition: width .6s ease;
        }

        .achievement-race__bar--over {
            background: #1D9E75;
        }

        .achievement-race__bar--near {
            background: #639922;
        }

        .achievement-race__bar--mid {
            background: #EF9F27;
        }

        .achievement-race__bar--low {
            background: #D4537E;
        }

        .achievement-race__value {
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .achievement-race__bar--over .achievement-race__value {
            color: #04342C;
        }

        .achievement-race__bar--near .achievement-race__value {
            color: #173404;
        }

        .achievement-race__bar--mid .achievement-race__value {
            color: #412402;
        }

        .achievement-race__bar--low .achievement-race__value {
            color: #4B1528;
        }

        .achievement-race__marker {
            position: absolute;
            top: 50%;
            width: 22px;
            height: 22px;
            margin-top: -11px;
            margin-left: -11px;
            border-radius: 50%;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(24, 28, 50, .12);
            border: 2px solid currentColor;
            transition: left .6s ease;
        }

        .achievement-race__marker i {
            font-size: 11px;
        }

        .achievement-race__marker--over {
            color: #1D9E75;
        }

        .achievement-race__marker--near {
            color: #639922;
        }

        .achievement-race__marker--mid {
            color: #EF9F27;
        }

        .achievement-race__marker--low {
            color: #D4537E;
        }

        .achievement-race__empty {
            padding: 64px 0;
            color: #B5B9C6;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
        }

        @media (max-width: 575.98px) {
            .achievement-race__body {
                padding-right: 28px;
            }

            .achievement-race__row {
                grid-template-columns: minmax(70px, 96px) minmax(0, 1fr);
            }

            .achievement-race__label {
                font-size: 11.5px;
            }
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
        :show-bulan="false" :show-tahun="false" :show-period="true" :show-distributor="true" :show-reset="true"
        id-reset="btnResetSellingOut">
        <div class="col-12 col-sm-auto mb-2 p-0" style="min-width: 160px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Produk</label>
            <select id="filter_product" class="form-control form-control-sm select2-product" style="width: 100%;" multiple></select>
        </div>
    </x-filter-bar>

    {{-- ===== SUMMARY CARDS ===== --}}
    <div class="row mb-3">
        <div class="col-lg-4 col-md-12 mb-3">
            <x-stat-card title="Total Netto" value="" valueId="card_netto" subtitle="-" subtitleId="card_growth"
                subtitleColor="metric-growth" icon="fas fa-coins" color="success" />
        </div>
        <div class="col-lg-4 col-md-12 mb-3">
            <x-stat-card title="TOP" value="" valueId="card_top" icon="fas fa-bullseye" color="primary" />
        </div>
        <div class="col-lg-4 col-md-12 mb-3">
            <x-stat-card title="Achievement TOP" value="" valueId="card_top_achievement" subtitle="-"
                subtitleId="card_top_gap" subtitleColor="text-muted" icon="fas fa-chart-line" color="warning" />
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

    {{-- ===== ROW 2: Segment + Divisi + Pilar ===== --}}
    <div class="row mb-5">
        <div class="col-lg-4 col-md-12 mb-4">
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
        <div class="col-lg-4 col-md-12 mb-4">
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
        <div class="col-lg-4 col-md-12 mb-4">
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


    {{-- ===== ROW 5 & 6: Tabel Detail ===== --}}
    <x-datatable-card title="Detail Performance per Anggota" table-id="tblAnggota" table-class="table-sm" :load-assets="false"
        :collapsible="true" :collapsed="true">
        <th>No</th>
        <th>NIK</th>
        <th>Nama</th>
        <th>Level</th>
        <th>Pilar</th>
        <th>Divisi</th>
        <th>Kunjungan</th>
        <th>Netto</th>
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
