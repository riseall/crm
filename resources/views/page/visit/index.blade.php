@extends('layouts.app', ['title' => 'Monitoring Kunjungan'])

@push('styles')
    <link href="{{ asset('css/datatables.bundle.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/visit/index.css') }}?v={{ filemtime(public_path('css/visit/index.css')) }}" />
@endpush

@section('content')
    @if (isset($anggota))
        <div class="d-flex align-items-center justify-content-between mb-5">
            <h3 class="font-weight-bolder text-dark mb-0">Detail Kunjungan Sales</h3>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard.index') }}"
                class="btn btn-light-danger font-weight-bolder mr-2">
                <i class="fas fa-arrow-circle-left"></i> Kembali
            </a>
        </div>

        <div class="card card-custom gutter-b bg-primary shadow-sm"
            style="background: linear-gradient(90deg, #241b53 20%, #582fa1 100%); border: none; border-radius: var(--visit-radius);">
            <div class="card-body d-flex align-items-center p-5">
                <div class="symbol symbol-70 symbol-light-primary mr-5">
                    <span class="symbol-label font-size-h2 font-weight-bold text-primary bg-white shadow-sm"
                        style="border-radius: 14px;">
                        {{ mb_substr($anggota->name ?? '?', 0, 1) }}
                    </span>
                </div>
                <div class="d-flex flex-column text-white">
                    <span class="font-weight-bolder font-size-h4 mb-1">{{ $anggota->name ?? 'Tanpa Nama' }}</span>
                    <span class="font-weight-bold font-size-sm opacity-80">
                        NIK: {{ $anggota->nik }} &nbsp;|&nbsp;
                        Level: {{ $anggota->level ?? '-' }} &nbsp;|&nbsp;
                        Divisi: {{ $anggota->divisi ?? '-' }} &nbsp;|&nbsp;
                        Pilar: {{ $anggota->pilar ?? '-' }}
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== HEADER & FILTER BAR ===== --}}
    <x-filter-bar :pilars="$pilars"
        title="{{ isset($anggota) ? 'Filter Data Kunjungan' : 'Monitoring Kunjungan dan Heatmap' }}"
        subtitle="{{ isset($anggota) ? 'Sesuaikan tanggal pencarian untuk sales ini' : 'Visualisasi Sebaran dan Ringkasan Kunjungan Sales' }}"
        icon="{{ isset($anggota) ? 'fa-calendar-day' : 'fa-map-marked-alt' }}" :show-bulan="false" :show-tahun="false"
        :show-pilar="!isset($anggota)" id-pilar="filter_pilar" :show-reset="true" id-reset="btnResetVisit" id-apply="btnFilterVisit">
        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 135px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Tanggal Kunjungan</label>
            <input type="date" id="filter_visit_date" class="form-control form-control-sm bg-white"
                value="{{ date('Y-m-d') }}">
        </div>
    </x-filter-bar>

    {{-- ===== SUMMARY CARDS ===== --}}
    <div class="row mb-3">
        @php
            $colClass = isset($anggota) ? 'col-lg-4 col-md-4' : 'col-lg-3 col-md-6';
        @endphp
        <div class="{{ $colClass }} mb-3">
            <x-stat-card title="Plan Kunjungan" value="0" valueId="card_plan_visits" icon="fas fa-calendar-check"
                color="info" />
        </div>
        <div class="{{ $colClass }} mb-3">
            <x-stat-card title="Total Kunjungan" value="0" valueId="card_total_visits" icon="fas fa-walking"
                color="primary" />
        </div>
        <div class="{{ $colClass }} mb-3">
            <x-stat-card title="Efektivitas (%)" value="0" valueId="card_efektivitas" icon="fas fa-chart-pie"
                color="success" />
        </div>
        @if (!isset($anggota))
            <div class="col-lg-3 col-md-6 mb-3">
                <x-stat-card title="Produktivitas (Call/Sales)" value="0" valueId="card_produktivitas"
                    icon="fas fa-chart-line" color="warning" />
            </div>
        @endif
    </div>

    {{-- ===== HEATMAP VISUALIZATION ===== --}}
    <div class="row mb-5">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-header border-0 pt-4 pb-3">
                    <h3 class="card-title align-items-start flex-column mb-0">
                        <span class="card-label font-weight-bolder text-dark font-size-h5">
                            Peta Sebaran Heatmap Kunjungan
                        </span>
                        <span class="text-muted mt-1 font-weight-bold font-size-xs">
                            Visualisasi Kepadatan Koordinat Kunjungan Sales di Indonesia
                        </span>
                    </h3>
                    <div class="card-toolbar">
                        <button id="btnToggleHeatmap" class="btn btn-sm btn-outline-primary mr-2 active">
                            <i class="fas fa-fire"></i> Heatmap
                        </button>
                        <button id="btnToggleMarkers" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-map-marker-alt"></i> Markers
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 p-xl-4 position-relative">
                    <div id="visitMap"></div>

                    {{-- Floating Google Maps AQI-style Indicator Card --}}
                    <div class="gmaps-aqi-card">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="aqi-card-title">Intensitas kunjungan di area ini</span>
                            <label class="aqi-toggle-switch mb-0" title="Aktif/Nonaktifkan Heatmap">
                                <input type="checkbox" id="chkAqiHeatmapToggle" checked>
                                <span class="aqi-slider"></span>
                            </label>
                        </div>
                        <div class="aqi-card-subtitle mb-1">Tap / arahkan kursor pada peta untuk detail</div>
                        <div class="aqi-color-bar mb-1"></div>
                        <div class="aqi-scale-labels d-flex justify-content-between">
                            <span>0</span>
                            <span>5</span>
                            <span>10</span>
                            <span>15</span>
                            <span>20+</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== DETAIL DATATABLE ===== --}}
    <x-datatable-card title="Detail Keseluruhan Kunjungan" subtitle="Riwayat sebaran dan log kunjungan sales di lapangan"
        table-id="tblVisit" table-class="table-separate table-head-custom table-checkable text-nowrap table-sm"
        :load-assets="false" :collapsible="true">
        <th>No</th>
        <th>Waktu</th>
        <th>Sales / NIK</th>
        <th>Outlet / Cust ID</th>
        <th class="text-center">Aksi</th>
    </x-datatable-card>

    {{-- Modal Detail --}}
    @include('page.visit.modal')
@endsection

@push('scripts')
    <script src="{{ asset('js/datatables.bundle.js') }}"></script>
    <script src="{{ asset('js/leaflet.js') }}"></script>
    <script src="{{ asset('js/leaflet-heat.js') }}"></script>
    <script>
        window.VisitPageConfig = {
            initialNik: @json($initialNik ?? ''),
            initialName: @json($anggota->name ?? ''),
            isDetail: {{ isset($anggota) ? 'true' : 'false' }},
            today: @json(date('Y-m-d')),
            routes: {
                data: "{{ url('visits/data') }}",
                dataBySales: "{{ url('visits/data-by-sales') }}"
            }
        };
    </script>
    <script src="{{ asset('js/visit/index.js') }}?v={{ filemtime(public_path('js/visit/index.js')) }}"></script>
@endpush
