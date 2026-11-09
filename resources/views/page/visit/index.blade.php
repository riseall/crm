@extends('layouts.app', ['title' => 'Kunjungan VS Realisasi'])

@push('styles')
    <link href="{{ asset('css/datatables.bundle.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/visit/index.css') }}?v={{ filemtime(public_path('css/visit/index.css')) }}" />
    <style>
        .filter-card-custom {
            border: none;
            border-radius: var(--visit-radius, 12px);
            background: #ffffff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }

        .filter-label-header {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #1e1e2d;
        }

        .kpi-card {
            border: none;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(0, 0, 0, 0.08);
        }

        .kpi-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #5e6278;
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: #181c32;
            line-height: 1.2;
        }

        .kpi-subtitle {
            font-size: 0.8rem;
            font-weight: 600;
            color: #7e8299;
            margin-top: 6px;
        }

        .table-child-row-wrapper {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #e9ecef;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .btn-expand-row {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.2s;
        }

        .btn-expand-row.expanded {
            background-color: #f64e60 !important;
            color: #ffffff !important;
            transform: rotate(45deg);
        }
    </style>
@endpush

@section('content')
    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="font-weight-bolder text-dark mb-1">KUNJUNGAN VS REALISASI</h3>
            <span class="text-muted font-size-sm font-weight-bold">Monitoring sebaran kunjungan lapangan SFA dan realisasi
                selling out</span>
        </div>
        @if (isset($anggota))
            <a href="{{ route('visits.index') }}" class="btn btn-sm btn-light-primary font-weight-bolder">
                <i class="fas fa-globe-asia mr-1"></i> Lihat Data Nasional
            </a>
        @endif
    </div>

    {{-- Filter Bar --}}
    <div class="card filter-card-custom mb-5">
        <div class="card-body p-4">
            <form id="formVisitFilter" class="row align-items-end">
                {{-- Periode: Date Range --}}
                <div class="col-12 col-md-4 col-xl-3 mb-3">
                    <label class="font-size-xs font-weight-bolder text-dark mb-1 d-block">
                        <i class="far fa-calendar-alt text-primary mr-1"></i> Periode (MTD / Range)
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="date" id="filter_start_date" class="form-control form-control-sm bg-light"
                            value="{{ $defaultStartDate ?? date('Y-m-01') }}">
                        <div class="input-group-prepend input-group-append">
                            <span class="input-group-text bg-white px-2">s/d</span>
                        </div>
                        <input type="date" id="filter_end_date" class="form-control form-control-sm bg-light"
                            value="{{ $defaultEndDate ?? date('Y-m-d') }}">
                    </div>
                </div>

                {{-- Divisi --}}
                <div class="col-6 col-md-2 col-xl-2 mb-3">
                    <label class="font-size-xs font-weight-bolder text-dark mb-1 d-block">Divisi</label>
                    <select id="filter_divisi" class="form-control form-control-sm selectpicker" data-live-search="true"
                        title="All">
                        <option value="All" selected>All</option>
                        @foreach ($divisis as $div)
                            <option value="{{ $div }}">{{ $div }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Level --}}
                <div class="col-6 col-md-2 col-xl-2 mb-3">
                    <label class="font-size-xs font-weight-bolder text-dark mb-1 d-block">Level</label>
                    <select id="filter_level" class="form-control form-control-sm selectpicker" data-live-search="true"
                        title="All">
                        <option value="All" selected>All</option>
                        @foreach ($levels as $lvl)
                            <option value="{{ $lvl }}">{{ $lvl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Employee --}}
                <div class="col-6 col-md-2 col-xl-2 mb-3">
                    <label class="font-size-xs font-weight-bolder text-dark mb-1 d-block">Employee</label>
                    <select id="filter_employee" class="form-control form-control-sm selectpicker" data-live-search="true"
                        title="All">
                        <option value="All" selected>All</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->nik }}"
                                {{ isset($initialNik) && $initialNik == $emp->nik ? 'selected' : '' }}>
                                {{ $emp->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Outlet --}}
                <div class="col-6 col-md-2 col-xl-2 mb-3">
                    <label class="font-size-xs font-weight-bolder text-dark mb-1 d-block">Outlet</label>
                    <select id="filter_outlet" class="form-control form-control-sm selectpicker" data-live-search="true"
                        title="All">
                        <option value="All" selected>All</option>
                        @foreach ($outlets as $out)
                            <option value="{{ $out->id_konversi }}">{{ $out->nama_outlet }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Actions --}}
                <div class="col-12 col-xl-auto mb-3 text-right">
                    <button type="button" id="btnFilterVisit" class="btn btn-sm btn-primary font-weight-bolder px-4 mr-1">
                        <i class="fas fa-filter icon-sm"></i> Terapkan
                    </button>
                    <button type="button" id="btnResetVisit" class="btn btn-sm btn-light-danger font-weight-bolder px-3">
                        <i class="fas fa-undo icon-sm"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary Cards (Gambar 1: 3 Cards) --}}
    <div class="row mb-5">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card kpi-card p-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-title mb-0">Jumlah User yang Ter-kunjungi</span>
                    <span class="symbol symbol-35 symbol-light-primary">
                        <span class="symbol-label"><i class="fas fa-user-md text-primary"></i></span>
                    </span>
                </div>
                <div class="kpi-value" id="card_user_terkunjungi">0</div>
                <div class="kpi-subtitle">Dokter & Spesialis terdata di SFA</div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card kpi-card p-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-title mb-0">Total Realisasi</span>
                    <span class="symbol symbol-35 symbol-light-success">
                        <span class="symbol-label"><i class="fas fa-coins text-success"></i></span>
                    </span>
                </div>
                <div class="kpi-value text-success" id="card_total_realisasi">Rp 0</div>
                <div class="kpi-subtitle">Akumulasi Selling Out MTD / Periode</div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12 mb-3">
            <div class="card kpi-card p-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-title mb-0">Produktivitas per Level</span>
                    <span class="symbol symbol-35 symbol-light-warning">
                        <span class="symbol-label"><i class="fas fa-chart-line text-warning"></i></span>
                    </span>
                </div>
                <div class="kpi-value text-warning" id="card_produktivitas_level">Rp 0</div>
                <div class="kpi-subtitle" id="card_produktivitas_ff_container">
                    Total per FF: <span id="card_produktivitas_ff" class="font-weight-bolder text-dark">Rp 0</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Map Section --}}
    <div class="row mb-5">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-header border-0 pt-4 pb-3">
                    <h3 class="card-title align-items-start flex-column mb-0">
                        <span class="card-label font-weight-bolder text-dark font-size-h5">
                            Peta Sebaran Kunjungan vs Realisasi Selling Out
                        </span>
                        <span class="text-muted mt-1 font-weight-bold font-size-xs">
                            Visualisasi Sebaran Koordinat Outlet, Intensitas Kunjungan & Realisasi Selling Out
                        </span>
                    </h3>
                    <div class="card-toolbar">
                        <button id="btnToggleHeatmap" class="btn btn-sm btn-outline-primary mr-2 active">
                            <i class="fas fa-fire"></i> Heatmap
                        </button>
                        <button id="btnToggleMarkers" class="btn btn-sm btn-outline-info active">
                            <i class="fas fa-map-marker-alt"></i> Markers
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 p-xl-4 position-relative">
                    <div id="visitMap"></div>

                    {{-- Floating Inverted Indicator Card --}}
                    <div class="gmaps-aqi-card">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="aqi-card-title">Intensitas Kunjungan Area</span>
                            <label class="aqi-toggle-switch mb-0" title="Aktif/Nonaktifkan Heatmap">
                                <input type="checkbox" id="chkAqiHeatmapToggle" checked>
                                <span class="aqi-slider"></span>
                            </label>
                        </div>
                        <div class="aqi-card-subtitle mb-1">Hijau: kunjungan tinggi | Merah: rendah</div>
                        <div class="aqi-color-bar mb-1"></div>
                        <div class="aqi-scale-labels d-flex justify-content-between">
                            <span>0</span>
                            <span>3</span>
                            <span>7</span>
                            <span>12</span>
                            <span>15+</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table Section (Gambar 2: Detail Kunjungan Vs Realisasi Selling Out) --}}
    <div class="row">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-header border-0 pt-4 pb-3">
                    <h3 class="card-title align-items-start flex-column mb-0">
                        <span class="card-label font-weight-bolder text-dark font-size-h5">
                            Detail Kunjungan Vs Realisasi Selling Out
                        </span>
                        <span class="text-muted mt-1 font-weight-bold font-size-xs">
                            Klik tombol (+) pada baris untuk mem-breakdown nama dokter / user yang dikunjungi
                        </span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-head-custom table-vertical-center table-hover text-nowrap"
                            id="tblVisit">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">No</th>
                                    <th>Employee</th>
                                    <th>Outlet</th>
                                    <th class="text-center">Total Kunjungan</th>
                                    <th class="text-right">Realisasi SellingOut</th>
                                    <th style="width: 50px;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Populated via AJAX --}}
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bolder text-dark bg-light border-top">
                                    <th class="text-left font-weight-bolder pl-4" colspan="3">Total</th>
                                    <th class="text-center font-weight-bolder" id="foot_total_kunjungan">0</th>
                                    <th class="text-right font-weight-bolder text-success" id="foot_total_selling_out">Rp
                                        0</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Detail jika dibutuhkan --}}
    @include('page.visit.modal')
@endsection

@push('scripts')
    <script src="{{ asset('js/datatables.bundle.js') }}"></script>
    <script src="{{ asset('js/leaflet.js') }}"></script>
    <script src="{{ asset('js/leaflet-heat.js') }}?v={{ filemtime(public_path('js/leaflet-heat.js')) }}"></script>
    <script>
        window.VisitPageConfig = {
            initialNik: @json($initialNik ?? ''),
            initialName: @json($anggota->name ?? ''),
            today: @json(date('Y-m-d')),
            routes: {
                data: "{{ route('visits.data') }}",
                detailDoctors: "{{ route('visits.detail-doctors') }}"
            }
        };
    </script>
    <script src="{{ asset('js/visit/index.js') }}?v={{ filemtime(public_path('js/visit/index.js')) }}"></script>
@endpush
