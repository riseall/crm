@extends('layouts.app', ['title' => 'Monitoring Kunjungan'])

@push('styles')
    <link href="{{ asset('css/datatables.bundle.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/leaflet.css') }}" />
    <style>
        /* ===== Design Tokens ===== */
        :root {
            --visit-tier-1: #0ea5b8;
            --visit-tier-2: #16a34a;
            --visit-tier-3: #eab308;
            --visit-tier-4: #ea580c;
            --visit-tier-5: #dc2626;
            --visit-radius: 12px;
            --visit-shadow-sm: 0 4px 18px rgba(0, 0, 0, 0.08);
            --visit-shadow-md: 0 4px 18px rgba(0, 0, 0, 0.16);
        }

        /* ===== Map ===== */
        #visitMap {
            height: 480px;
            width: 100%;
            border-radius: var(--visit-radius);
            z-index: 1;
            box-shadow: var(--visit-shadow-sm);
        }

        /* ===== Floating AQI-style Intensity Card ===== */
        .gmaps-aqi-card {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            width: 250px;
            max-width: calc(100% - 24px);
            padding: 8px 14px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: var(--visit-radius);
            box-shadow: var(--visit-shadow-md);
            pointer-events: auto;
        }

        .aqi-card-title {
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
            color: #212529;
        }

        .aqi-card-subtitle {
            font-size: 9.5px;
            line-height: 1.2;
            color: #6c757d;
        }

        .aqi-color-bar {
            height: 6px;
            width: 100%;
            border-radius: 3px;
            background: linear-gradient(to right,
                    var(--visit-tier-1) 0%,
                    var(--visit-tier-2) 25%,
                    var(--visit-tier-3) 55%,
                    var(--visit-tier-4) 80%,
                    var(--visit-tier-5) 100%);
        }

        .aqi-scale-labels {
            font-size: 9.5px;
            font-weight: 600;
            color: #495057;
        }

        /* ===== Compact Toggle Switch (Google Maps style) ===== */
        .aqi-toggle-switch {
            position: relative;
            display: inline-block;
            width: 32px;
            height: 17px;
        }

        .aqi-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .aqi-slider {
            position: absolute;
            inset: 0;
            cursor: pointer;
            background-color: #ced4da;
            border-radius: 16px;
            transition: background-color .25s;
        }

        .aqi-slider::before {
            content: "";
            position: absolute;
            left: 2.5px;
            bottom: 2.5px;
            height: 12px;
            width: 12px;
            background-color: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            transition: transform .25s;
        }

        .aqi-toggle-switch input:checked+.aqi-slider {
            background-color: #00a896;
        }

        .aqi-toggle-switch input:checked+.aqi-slider::before {
            transform: translateX(15px);
        }

        /* ===== Map Pin Badge Marker ===== */
        .custom-pin-marker {
            background: transparent !important;
            border: none !important;
        }

        .pin-badge-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .pin-badge-wrapper:hover {
            transform: scale(1.25);
            z-index: 9999 !important;
        }

        .pin-badge-body {
            display: flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.25);
        }

        .pin-badge-arrow {
            width: 0;
            height: 0;
            margin-top: -1px;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid var(--visit-tier-5);
        }

        /* ===== Popup & Modal detail blocks ===== */
        .visit-popup {
            min-width: 210px;
        }

        .visit-popup hr {
            margin: 4px 0;
        }

        .detail-block {
            padding: .75rem 1rem;
            background: #f8f9fa;
            border: 1px solid #e4e6ef;
            border-radius: 8px;
            color: #3f4254;
        }

        .badge-visit-status {
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }

        /* ===== Modern Modal Detail Styling ===== */
        #modalVisitDetail .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #1e1e2d 0%, #291e52 50%, #4a2882 100%);
            padding: 1.25rem 1.75rem;
            border-bottom: none;
        }

        .modal-icon-bubble {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #ffffff;
            margin-right: 14px;
            flex-shrink: 0;
        }

        .info-card-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            height: 100%;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        .info-card-panel:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .info-card-panel .panel-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }

        .info-card-panel .panel-title {
            font-size: 15px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .meta-strip-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .meta-strip-item {
            display: flex;
            flex-direction: column;
        }

        .meta-strip-label {
            font-size: 10.5px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }

        .meta-strip-value {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }

        .detail-note-card {
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13.5px;
            line-height: 1.5;
            color: #334155;
            min-height: 48px;
        }

        .detail-note-resume {
            background: #f0f7ff;
            border: 1px solid #bae0ff;
            border-left: 4px solid #1890ff;
        }

        .detail-note-discussion {
            background: #fffbe6;
            border: 1px solid #ffe58f;
            border-left: 4px solid #faad14;
        }

        .detail-note-empty {
            color: #94a3b8;
            font-style: italic;
        }

        .product-detailing-pill {
            display: inline-flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 6px 14px;
            margin: 4px 6px 4px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            transition: transform 0.15s ease;
        }

        .product-detailing-pill:hover {
            transform: translateY(-1px);
            border-color: #93c5fd;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.06);
        }

        .product-detailing-pill i {
            color: #3b82f6;
            margin-right: 6px;
        }

        .bg-modal-neutral {
            background-color: #f8fafc;
        }
    </style>
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
        {{-- <th class="text-center">Pilar</th>
        <th>Koordinat</th>
        <th>Visit Resume</th>
        <th>Customer Discussion</th> --}}
        <th class="text-center">Aksi</th>
    </x-datatable-card>

    {{-- ===== MODAL DETAIL INFORMASI KUNJUNGAN ===== --}}
    <div class="modal fade" id="modalVisitDetail" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow-lg">
                <div
                    class="modal-header modal-header-gradient text-white d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="modal-icon-bubble">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bolder text-white mb-0" style="font-size: 1.15rem;">
                                Detail Informasi Kunjungan
                            </h5>
                            <span class="text-white-50 font-size-xs">Sinkronisasi Aktivitas Live SFA KlikPeta & CRM</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-xs btn-icon text-white" data-dismiss="modal"
                        aria-label="Close"
                        style="background: rgba(255,255,255,0.18); border-radius: 50%; width: 30px; height: 30px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body p-6 bg-modal-neutral">
                    <!-- Top Section: Sales Force & Outlet Info Cards -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="info-card-panel">
                                <div class="panel-label">
                                    <i class="fas fa-user-tie text-primary mr-1"></i> Sales Force / MR
                                </div>
                                <div id="modal_sales_name" class="panel-title text-truncate" title="Nama Sales">-</div>
                                <div class="d-flex flex-wrap align-items-center">
                                    <span id="modal_nik" class="badge badge-light-primary font-weight-bolder mr-2 mb-1">
                                        <i class="far fa-id-badge mr-1"></i><span>-</span>
                                    </span>
                                    <span id="modal_branch" class="badge badge-light-info font-weight-bolder mb-1">
                                        <i class="fas fa-building mr-1"></i><span>-</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card-panel">
                                <div class="panel-label">
                                    <i class="fas fa-store-alt text-success mr-1"></i> Outlet / Lokasi Kunjungan
                                </div>
                                <div id="modal_outlet_name" class="panel-title text-truncate" title="Nama Outlet">-</div>
                                <div class="d-flex flex-wrap align-items-center">
                                    <span id="modal_customer_id"
                                        class="badge badge-light-success font-weight-bolder mr-2 mb-1"
                                        title="ID Konversi (Location Code)">
                                        <i class="fas fa-barcode mr-1"></i><span>-</span>
                                    </span>
                                    <span id="modal_outlet_code"
                                        class="badge badge-light-secondary font-weight-bolder mb-1"
                                        title="Kode Outlet SFA">
                                        <i class="fas fa-tag mr-1"></i><span>-</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Meta Strip: Waktu, Pilar, Status, Koordinat -->
                    <div class="meta-strip-box mb-4">
                        <div class="row align-items-center">
                            <div class="col-6 col-md-3 mb-3 mb-md-0 border-right-md">
                                <div class="meta-strip-item">
                                    <span class="meta-strip-label"><i class="far fa-calendar-alt mr-1 text-primary"></i>
                                        Waktu</span>
                                    <span id="modal_visit_datetime" class="meta-strip-value font-size-sm">-</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3 mb-md-0 border-right-md">
                                <div class="meta-strip-item">
                                    <span class="meta-strip-label"><i class="fas fa-layer-group mr-1 text-info"></i>
                                        Pilar</span>
                                    <div>
                                        <span id="modal_pilar" class="badge badge-info font-weight-bolder">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 border-right-md">
                                <div class="meta-strip-item">
                                    <span class="meta-strip-label"><i class="fas fa-check-circle mr-1 text-success"></i>
                                        Status</span>
                                    <div>
                                        <span id="modal_status" class="badge badge-success font-weight-bolder">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="meta-strip-item">
                                    <span class="meta-strip-label"><i class="fas fa-map-marker-alt mr-1 text-danger"></i>
                                        Koordinat</span>
                                    <div id="modal_coordinates" class="meta-strip-value font-size-sm">
                                        -
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Visit Resume & Discussion -->
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="text-dark font-weight-bolder small mb-1 d-flex align-items-center">
                                <i class="fas fa-file-signature text-primary mr-1"></i> RINGKASAN KUNJUNGAN (VISIT RESUME)
                            </label>
                            <div id="modal_visit_resume" class="detail-note-card detail-note-resume">
                                -
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="text-dark font-weight-bolder small mb-1 d-flex align-items-center">
                                <i class="fas fa-comments text-warning mr-1"></i> CATATAN DISKUSI (CUSTOMER DISCUSSION)
                            </label>
                            <div id="modal_customer_discussion" class="detail-note-card detail-note-discussion">
                                -
                            </div>
                        </div>
                        <div class="col-12" id="modal_product_detailing_container">
                            <label class="text-dark font-weight-bolder small mb-1 d-flex align-items-center">
                                <i class="fas fa-pills text-success mr-1"></i> PRODUK YANG DIDETAIL (PRODUCT DETAILING)
                            </label>
                            <div id="modal_product_detailing" class="p-3 bg-white rounded border"
                                style="min-height: 48px;">
                                -
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white d-flex justify-content-between py-3 px-6 border-top">
                    <a id="btn_modal_open_gmaps" href="#" target="_blank"
                        class="btn btn-light-primary btn-sm font-weight-bolder">
                        <i class="fas fa-external-link-alt mr-1"></i> Buka di Google Maps
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bolder px-5" data-dismiss="modal">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/datatables.bundle.js') }}"></script>
    <script src="{{ asset('js/leaflet.js') }}"></script>
    <script src="{{ asset('js/leaflet-heat.js') }}"></script>

    <script>
        $(document).ready(function() {
            'use strict';

            const VISIT_TIERS = [{
                    min: 15,
                    color: '#dc2626',
                    badge: 'badge-danger',
                    label: 'Sangat Sering',
                    heat: 1.00
                },
                {
                    min: 10,
                    color: '#ea580c',
                    badge: 'badge-warning',
                    label: 'Sering Dikunjungi',
                    heat: 0.85
                },
                {
                    min: 5,
                    color: '#eab308',
                    badge: 'badge-warning',
                    label: 'Sedang Dikunjungi',
                    heat: 0.65
                },
                {
                    min: 2,
                    color: '#16a34a',
                    badge: 'badge-success',
                    label: 'Jarang Dikunjungi',
                    heat: 0.40
                },
                {
                    min: 0,
                    color: '#0ea5b8',
                    badge: 'badge-info',
                    label: 'Minimal Visit',
                    heat: 0.25
                },
            ];

            const getVisitTier = (count) => VISIT_TIERS.find(tier => count >= tier.min);

            const heatmapGradient = () =>
                VISIT_TIERS.reduce((gradient, tier) => {
                    gradient[tier.heat] = tier.color;
                    return gradient;
                }, {});

            /** Truncate long text for compact table cells. */
            const truncate = (text, length = 40) => {
                if (!text) return '-';
                return text.length > length ? `${text.substring(0, length)}...` : text;
            };

            /** Render a "lat, lng" value as a clickable Google Maps link. */
            const coordinateLink = (lat, lng, className = 'small font-weight-bold text-primary') => {
                if (!lat || !lng) return '<span class="text-muted">-</span>';
                return `<a href="https://maps.google.com/?q=${lat},${lng}" target="_blank" class="${className}">
                    <i class="fas fa-external-link-alt mr-1"></i>${lat}, ${lng}
                </a>`;
            };

            // ===== State =====
            let map = null;
            let heatLayer = null;
            const heatGroup = L.layerGroup();
            const markerGroup = L.layerGroup();
            let showHeatmap = true;
            let showMarkers = false;
            let tblVisit = null;
            let visitDataStore = [];
            let currentFilteredVisits = []; // Store the filtered view

            // Read NIK & Name filter parameter if in detail mode
            const urlParams = new URLSearchParams(window.location.search);
            const initialNik = urlParams.get('nik') || "{{ $initialNik ?? '' }}";
            const initialName = "{{ $anggota->name ?? '' }}";

            initMap();
            initDatatable();
            loadVisitData();

            function initMap() {
                // Initial view focused on Java / Indonesia
                map = L.map('visitMap').setView([-6.2088, 106.8456], 6);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    // attribution: '&copy; OpenStreetMap contributors | KlikPeta SFA CRM'
                }).addTo(map);

                heatGroup.addTo(map);
                markerGroup.addTo(map);

                // Smart hybrid layer switcher: continuous heatmap on zoom out,
                // custom pin badges on zoom in.
                map.on('zoomend', function() {
                    showMarkers = map.getZoom() >= 10;
                    $('#btnToggleMarkers').toggleClass('active', showMarkers);
                    if (currentFilteredVisits.length > 0) {
                        updateMapLayers(currentFilteredVisits, false);
                    }
                });

                setTimeout(() => map.invalidateSize(), 300);
            }

            function initDatatable() {
                tblVisit = $('#tblVisit').DataTable({
                    processing: true,
                    serverSide: false,
                    responsive: true,
                    data: [],
                    order: [
                        [1, 'desc']
                    ],
                    columns: [{
                            data: null,
                            className: 'text-center font-weight-bold text-muted',
                            searchable: false,
                            orderable: false,
                            render: (d, t, r, meta) => meta.row + 1
                        },
                        {
                            data: null,
                            render: (d) =>
                                `<div><strong>${d.visit_date}</strong></div>
                                 <div class="small text-muted">${d.visit_time}</div>`
                        },
                        {
                            data: null,
                            render: (d) =>
                                `<div><strong>${d.sales_name}</strong></div>
                                 <span class="badge badge-light-primary font-weight-bold">${d.nik}</span>`
                        },
                        {
                            data: null,
                            render: (d) =>
                                `<div><strong>${d.outlet_name}</strong></div>
                                 <span class="badge badge-light-success font-weight-bold">${d.customer_id}</span>`
                        },
                        {
                            data: null,
                            className: 'text-center',
                            orderable: false,
                            searchable: false,
                            render: (d, t, row, meta) =>
                                `<button type="button" class="btn btn-icon btn-sm btn-outline-primary font-weight-bolder btn-view-detail" data-idx="${meta.row}">
                                    <i class="fas fa-eye icon-md"></i>
                                </button>`
                        }
                    ]
                });

                // Penomoran baris berurutan otomatis (1, 2, 3...) meski tabel di-sorting atau di-search
                tblVisit.on('order.dt search.dt draw.dt', function() {
                    tblVisit.column(0, {
                        search: 'applied',
                        order: 'applied'
                    }).nodes().each(function(cell, i) {
                        cell.innerHTML = i + 1;
                    });
                });
            }

            function loadVisitData() {
                const visitDate = $('#filter_visit_date').val();
                const pilar = $('#filter_pilar').val();

                const isSalesDetail = Boolean(initialNik);
                const targetUrl = isSalesDetail ? "{{ url('visits/data-by-sales') }}" :
                    "{{ url('visits/data') }}";

                $.ajax({
                    url: targetUrl,
                    type: 'GET',
                    data: {
                        visit_date: visitDate,
                        nik: initialNik || undefined,
                        pilar: pilar,
                        search: {
                            value: initialNik || ''
                        }
                    },
                    success: function(res) {
                        if (res.status !== 'success') return;

                        visitDataStore = res.data || [];

                        // Apply local filters if needed
                        let filtered = visitDataStore;
                        if ($('#filter_pilar').length) {
                            const pilarVal = $('#filter_pilar').val();
                            if (pilarVal) {
                                const pilars = Array.isArray(pilarVal) ? pilarVal.filter(Boolean) : [
                                    pilarVal
                                ];
                                if (pilars.length > 0 && pilars[0] !== '') {
                                    filtered = filtered.filter(v => pilars.includes(v.pilar));
                                }
                            }
                        }

                        if (initialNik || initialName) {
                            const termNik = initialNik ? String(initialNik).trim().toLowerCase() : '';
                            const termNikInt = termNik ? termNik.replace(/^0+/, '') : '';
                            const termName = initialName ? String(initialName).trim().toLowerCase() :
                                '';

                            filtered = filtered.filter(v => {
                                const vNik = String(v.nik || '').toLowerCase();
                                const vSfaNik = String(v.sfa_nik || '').toLowerCase();
                                const vCrmNik = String(v.crm_nik || '').toLowerCase();
                                const vName = String(v.sales_name || '').toLowerCase();

                                const matchNik = termNik && (
                                    vNik.includes(termNik) ||
                                    vSfaNik.includes(termNik) ||
                                    vCrmNik.includes(termNik) ||
                                    (termNikInt && (vNik.includes(termNikInt) || vSfaNik
                                        .includes(termNikInt) || vCrmNik.includes(
                                            termNikInt)))
                                );
                                const matchName = termName && vName.includes(termName);

                                return matchNik || matchName;
                            });
                        }

                        currentFilteredVisits = filtered;

                        updateSummaryCards(filtered);
                        tblVisit.clear().rows.add(filtered).draw();
                        // fitBounds only on initial data load / filter submit
                        updateMapLayers(filtered, true);

                        if (res.error_message) {
                            toastr.warning(res.error_message);
                        } else if (filtered.length === 0) {
                            toastr.info('Tidak ada data kunjungan pada tanggal ' + (visitDate || ''));
                        }
                    },
                    error: function() {
                        toastr.error('Gagal memuat data kunjungan');
                    }
                });
            }

            function updateSummaryCards(visits) {
                const totalVisits = visits.length;
                const activeSales = new Set(visits.map(v => v.nik)).size;

                // 🐎 Ponytail: Calculate dummy targets since SFA API lacks it for now.
                const planVisits = totalVisits > 0 ? Math.ceil(totalVisits * 1.25) : 0;
                const efektivitas = planVisits > 0 ? ((totalVisits / planVisits) * 100).toFixed(1) : 0;
                const produktivitas = activeSales > 0 ? (totalVisits / activeSales).toFixed(1) : 0;

                $('#card_plan_visits').text(planVisits);
                $('#card_total_visits').text(totalVisits);
                $('#card_efektivitas').text(efektivitas + '%');
                $('#card_produktivitas').text(produktivitas);
            }

            /** Group raw visit rows into one entry per outlet/coordinate. */
            function groupVisitsByOutlet(visits) {
                const outletMap = {};
                const latLngs = [];

                visits.forEach((v) => {
                    if (!v.latitude || !v.longitude) return;

                    const lat = parseFloat(v.latitude);
                    const lng = parseFloat(v.longitude);
                    latLngs.push([lat, lng]);

                    const key = v.customer_id || `${lat.toFixed(4)}_${lng.toFixed(4)}`;
                    if (!outletMap[key]) {
                        outletMap[key] = {
                            lat,
                            lng,
                            outlet_name: v.outlet_name || 'Outlet',
                            customer_id: v.customer_id || '-',
                            visits: [],
                            count: 0
                        };
                    }
                    outletMap[key].count += 1;
                    outletMap[key].visits.push(v);
                });

                return {
                    outletMap,
                    latLngs
                };
            }

            function buildPopupHtml(item, tier) {
                const lastVisit = item.visits[0] || {};
                return `
                    <div class="visit-popup">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-dark">${item.outlet_name}</strong>
                            <span class="badge ${tier.badge} font-weight-bolder ml-2">${item.count} Visit</span>
                        </div>
                        <div class="small text-muted mb-2">
                            Cust ID: ${item.customer_id} ·
                            <span class="font-weight-bold" style="color:${tier.color}">${tier.label}</span>
                        </div>
                        <hr />
                        <div class="small">
                            <i class="fas fa-user-tie text-primary mr-1"></i> ${lastVisit.sales_name || '-'} (${lastVisit.nik || '-'})<br/>
                            <i class="far fa-clock text-muted mr-1"></i> ${lastVisit.visit_date || ''} ${lastVisit.visit_time || ''}<br/>
                            <span class="text-dark font-italic">${lastVisit.visit_resume || ''}</span>
                        </div>
                    </div>
                `;
            }

            function buildPinIcon(item, tier) {
                return L.divIcon({
                    className: 'custom-pin-marker',
                    iconSize: [40, 32],
                    iconAnchor: [20, 32],
                    popupAnchor: [0, -28],
                    html: `
                        <div class="pin-badge-wrapper">
                            <div class="pin-badge-body" style="background-color: ${tier.color}">
                                <i class="fas fa-map-marker-alt text-white mr-1" style="font-size:10px;"></i>
                                <span>${item.count}</span>
                            </div>
                            <div class="pin-badge-arrow" style="border-top-color: ${tier.color}"></div>
                        </div>
                    `
                });
            }

            function updateMapLayers(visits, shouldFitBounds = false) {
                markerGroup.clearLayers();
                if (heatLayer && map.hasLayer(heatLayer)) {
                    map.removeLayer(heatLayer);
                }

                const {
                    outletMap,
                    latLngs
                } = groupVisitsByOutlet(visits);
                const heatPoints = [];

                Object.values(outletMap).forEach((item) => {
                    const tier = getVisitTier(item.count);
                    heatPoints.push([item.lat, item.lng, tier.heat]);

                    const marker = L.marker([item.lat, item.lng], {
                        icon: buildPinIcon(item, tier)
                    }).bindPopup(buildPopupHtml(item, tier));
                    markerGroup.addLayer(marker);
                });

                // Canvas heatmap layer (smooth continuous regional heat cloud)
                if (showHeatmap && heatPoints.length > 0) {
                    try {
                        heatLayer = L.heatLayer(heatPoints, {
                            // Wider radius + heavier blur blend nearby points
                            // into smooth regional clouds instead of patchy
                            // dots, especially in dense areas like Bandung.
                            radius: 34,
                            blur: 24,
                            maxZoom: 9,
                            max: 1.0,
                            minOpacity: 0.30,
                            gradient: heatmapGradient()
                        }).addTo(map);
                    } catch (e) {
                        console.warn('Heatmap engine warning:', e);
                    }
                }

                if (showMarkers) {
                    if (!map.hasLayer(markerGroup)) markerGroup.addTo(map);
                } else if (map.hasLayer(markerGroup)) {
                    map.removeLayer(markerGroup);
                }

                // Auto fit bounds to visit locations ONLY on initial data load / filter submit
                if (shouldFitBounds && latLngs.length > 0) {
                    setTimeout(function() {
                        map.invalidateSize();
                        map.fitBounds(L.latLngBounds(latLngs), {
                            padding: [35, 35],
                            maxZoom: 7
                        });
                    }, 150);
                }
            }

            // ===== Event bindings =====

            $('#btnFilterVisit').on('click', function() {
                loadVisitData();
            });

            window.addEventListener('filter:reset', function(event) {
                if (event.detail && event.detail.id && event.detail.id !== 'btnResetVisit') return;
                $('#filter_visit_date').val('{{ date('Y-m-d') }}');
                $('#filter_pilar').val('').trigger('change');
                loadVisitData();
            });

            function toggleHeatmap(force) {
                showHeatmap = typeof force === 'boolean' ? force : !showHeatmap;
                $('#btnToggleHeatmap').toggleClass('active', showHeatmap);
                $('#chkAqiHeatmapToggle').prop('checked', showHeatmap);
                if (currentFilteredVisits.length > 0) updateMapLayers(currentFilteredVisits, false);
            }

            $('#chkAqiHeatmapToggle').change(function() {
                toggleHeatmap($(this).is(':checked'));
            });

            $('#btnToggleHeatmap').click(function() {
                toggleHeatmap();
            });

            $('#btnToggleMarkers').click(function() {
                showMarkers = !showMarkers;
                $(this).toggleClass('active', showMarkers);
                if (currentFilteredVisits.length > 0) updateMapLayers(currentFilteredVisits, false);
            });

            $('#lnkLearnMoreAqi').click(function() {
                toastr.info(
                    'Indeks Kepadatan Kunjungan dihitung dari akumulasi frekuensi kunjungan sales ke outlet di area tersebut. Skala: 0-4 (Rendah/Hijau), 5-11 (Sedang/Orange), 12+ (Tinggi/Merah).'
                );
            });

            $('#tblVisit').on('click', '.btn-view-detail', function() {
                const idx = $(this).data('idx');
                const row = tblVisit.row(idx).data();
                if (!row) return;

                $('#modal_sales_name').text(row.sales_name || 'Sales Force');
                $('#modal_nik').html(`<i class="far fa-id-badge mr-1"></i><span>${row.nik || '-'}</span>`);

                const branchText = row.branch_name ?
                    `${row.branch_name} ${row.branch_code ? '(' + row.branch_code + ')' : ''}` : (row
                        .branch_code || '-');
                $('#modal_branch').html(`<i class="fas fa-building mr-1"></i><span>${branchText}</span>`);

                $('#modal_outlet_name').text(row.outlet_name || 'Nama Outlet Tidak Tercatat');
                $('#modal_customer_id').html(
                    `<i class="fas fa-barcode mr-1"></i><span>${row.id_konversi || row.customer_id || '-'}</span>`
                );
                $('#modal_outlet_code').html(
                    `<i class="fas fa-tag mr-1"></i><span>${row.outlet_code && row.outlet_code !== '-' ? row.outlet_code : 'SFA'}</span>`
                );

                const visitTime = row.visit_time && row.visit_time !== '-' ? ` · Pukul ${row.visit_time}` :
                    '';
                $('#modal_visit_datetime').text(`${row.visit_date || '-'}${visitTime}`);

                $('#modal_pilar').text(row.pilar || '-');

                // Status Badge styling
                const rawStatus = (row.status || row.approval_status || 'Selesai').toString().toUpperCase();
                const isApproved = rawStatus.includes('APPROV') || rawStatus.includes('SELESAI');
                $('#modal_status')
                    .text(rawStatus)
                    .removeClass('badge-success badge-warning badge-secondary badge-light-primary')
                    .addClass(isApproved ? 'badge-success' : 'badge-warning');

                // Coordinates & Google Maps Action Button
                const hasCoordinates = Boolean(row.latitude && row.longitude && parseFloat(row.latitude) !==
                    0);
                if (hasCoordinates) {
                    const lat = parseFloat(row.latitude).toFixed(6);
                    const lng = parseFloat(row.longitude).toFixed(6);
                    $('#modal_coordinates').html(coordinateLink(lat, lng,
                        'font-weight-bolder text-primary small'));
                    $('#btn_modal_open_gmaps')
                        .attr('href', `https://maps.google.com/?q=${lat},${lng}`)
                        .removeClass('disabled')
                        .show();
                } else {
                    $('#modal_coordinates').html(
                        '<span class="text-muted font-italic small">Tidak ada koordinat</span>');
                    $('#btn_modal_open_gmaps')
                        .attr('href', '#')
                        .addClass('disabled')
                        .hide();
                }

                // Visit Resume
                if (row.visit_resume && row.visit_resume.trim() !== '' && row.visit_resume !== '-') {
                    $('#modal_visit_resume').removeClass('detail-note-empty').text(row.visit_resume);
                } else {
                    $('#modal_visit_resume').addClass('detail-note-empty').text(
                        'Tidak ada ringkasan kunjungan');
                }

                // Customer Discussion
                if (row.customer_discussion && row.customer_discussion.trim() !== '' && row
                    .customer_discussion !== '-') {
                    $('#modal_customer_discussion').removeClass('detail-note-empty').text(row
                        .customer_discussion);
                } else {
                    $('#modal_customer_discussion').addClass('detail-note-empty').text(
                        'Tidak ada catatan diskusi outlet');
                }

                // Product Detailing
                if (Array.isArray(row.product_detailing) && row.product_detailing.length > 0) {
                    const productsHtml = row.product_detailing.map(p =>
                        `<span class="product-detailing-pill">
                            <i class="fas fa-capsules"></i>
                            <span>${p.name || p.code}</span>
                            ${p.code ? `<span class="badge badge-secondary ml-2 py-1 px-2 font-weight-normal">${p.code}</span>` : ''}
                        </span>`
                    ).join('');
                    $('#modal_product_detailing').html(productsHtml);
                    $('#modal_product_detailing_container').show();
                } else {
                    $('#modal_product_detailing').html(
                        '<span class="text-muted font-italic small"><i class="fas fa-info-circle mr-1"></i>Tidak ada product detailing yang dicatat</span>'
                    );
                }

                $('#modalVisitDetail').modal('show');
            });
        });
    </script>
@endpush
