@props([
    'showHeader' => true,
    'title' => 'Dashboard',
    'subtitle' => '',
    'icon' => 'fa-chart-line',
    'months' => [],
    'years' => [],
    'pilars' => [],
    'distributors' => ['KFTD', 'AAM', 'METD'],
    'selectedMonth' => null,
    'selectedYear' => null,
    'showBulan' => true,
    'showTahun' => true,
    'showPilar' => true,
    'showDistributor' => false,
    'showCoverage' => false,
    'showOutlet' => false,
    'showPeriod' => false,
    'periodLabel' => 'Periode',
    'selectedStartDate' => null,
    'selectedEndDate' => null,
    'periodRequired' => true,
    'bulanMultiple' => false,
    'btnText' => 'Terapkan',
    'btnIcon' => 'fa-filter',
    'idBulan' => 'filter_bulan',
    'idTahun' => 'filter_tahun',
    'idPilar' => 'filter_pilar',
    'idDistributor' => 'filter_distributor',
    'idCoverage' => 'filter_coverage_id',
    'idOutlet' => 'filter_outlet_id',
    'idPeriod' => 'filter_period',
    'idStartDate' => 'filter_start_date',
    'idEndDate' => 'filter_end_date',
    'idApply' => 'btnApplyFilter',
    'showReset' => false,
    'btnResetText' => 'Reset',
    'btnResetIcon' => 'fa-undo',
    'idReset' => 'btnResetFilter',
])

@php
    $activeMonths = collect((array) ($selectedMonth ?? now()->month))->map(fn($m) => (string) (int) $m)->all();
    $activeYear = (int) ($selectedYear ?? now()->year);
    $yearOptions = count($years) ? $years : range(now()->year, now()->year - 3);
    $activeStartDate = $selectedStartDate ?? ($periodRequired ? now()->startOfMonth()->toDateString() : '');
    $activeEndDate = $selectedEndDate ?? ($periodRequired ? now()->endOfMonth()->toDateString() : '');
    $controlColumnClass = 'col-6 col-sm-auto';
@endphp

<div class="card card-custom gutter-b filter-bar-responsive">
    <div class="card-body p-3 p-xl-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            @if ($showHeader)
                <div class="col-auto mr-auto mb-2 mb-xl-0 pl-0">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-35 symbol-light-primary mr-3 flex-shrink-0">
                            <span class="symbol-label">
                                <i class="fas {{ $icon }} text-primary icon-nm"></i>
                            </span>
                        </div>
                        <div class="card-title mb-0">
                            <h4 class="card-label font-weight-bolder mb-0 font-size-h5 text-nowrap">{{ $title }}</h4>
                            @if ($subtitle)
                                <span class="d-block text-muted font-size-xs font-weight-bold mt-1 text-nowrap">
                                    {{ $subtitle }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-auto flex-grow-1 pl-0">
                <div class="d-flex flex-wrap align-items-end justify-content-end" style="gap: 10px;">
                    @if ($showPeriod)
                        <div class="col-12 col-sm-auto mb-2 p-0" style="min-width: 210px;">
                            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">{{ $periodLabel }}</label>
                            <div class="input-group">
                                <input type="text" id="{{ $idPeriod }}"
                                    class="form-control form-control-sm dashboard-date-range bg-white"
                                    data-start-input="#{{ $idStartDate }}" data-end-input="#{{ $idEndDate }}"
                                    data-required="{{ $periodRequired ? 'true' : 'false' }}" readonly
                                    placeholder="Pilih interval tanggal">
                                <div class="input-group-append">
                                    <span class="input-group-text py-0 px-2">
                                        <i class="far fa-calendar-alt font-size-xs"></i>
                                    </span>
                                </div>
                            </div>
                            <input type="hidden" id="{{ $idStartDate }}" value="{{ $activeStartDate }}">
                            <input type="hidden" id="{{ $idEndDate }}" value="{{ $activeEndDate }}">
                        </div>
                    @endif

                    @if ($showBulan)
                        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 120px;">
                            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Bulan</label>
                            <select id="{{ $idBulan }}"
                                class="form-control form-control-sm {{ $bulanMultiple ? 'select2-month' : 'selectpicker' }}"
                                data-width="100%" style="width: 100%;"
                                @if ($bulanMultiple) multiple @endif>
                                @foreach ($months as $num => $name)
                                    <option value="{{ $num }}"
                                        {{ in_array((string) (int) $num, $activeMonths, true) ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($showTahun)
                        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 90px;">
                            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Tahun</label>
                            <select id="{{ $idTahun }}" class="form-control form-control-sm selectpicker" data-width="100%">
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}"
                                        {{ (int) $y === $activeYear ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($showPilar)
                        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 130px;">
                            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Pilar</label>
                            <select id="{{ $idPilar }}" class="form-control form-control-sm selectpicker" data-width="100%"
                                multiple data-actions-box="true" data-selected-text-format="count > 1"
                                data-none-selected-text="Semua pilar" data-select-all-text="Pilih semua"
                                data-deselect-all-text="Hapus semua" data-count-selected-text="{0} dipilih">
                                @foreach ($pilars as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($showDistributor)
                        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 145px;">
                            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Distributor</label>
                            <select id="{{ $idDistributor }}" class="form-control form-control-sm selectpicker" data-width="100%"
                                multiple data-actions-box="true" data-selected-text-format="count > 1"
                                data-none-selected-text="Semua distributor" data-select-all-text="Pilih semua"
                                data-deselect-all-text="Hapus semua" data-count-selected-text="{0} dipilih">
                                @foreach ($distributors as $d)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($showCoverage)
                        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 125px; max-width: 155px;">
                            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">PIC</label>
                            <select id="{{ $idCoverage }}" class="form-control form-control-sm select2-coverage"
                                style="width: 100%;">
                                <option value="">Semua PIC</option>
                            </select>
                        </div>
                    @endif

                    @if ($showOutlet)
                        <div class="col-6 col-sm-auto mb-2 p-0" style="min-width: 135px; max-width: 165px;">
                            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Outlet</label>
                            <select id="{{ $idOutlet }}" class="form-control form-control-sm select2-outlet" style="width: 100%;">
                                <option value="">Semua Outlet</option>
                            </select>
                        </div>
                    @endif

                    {{ $slot }}

                    <div class="col-auto mb-2 p-0 align-self-end">
                        <div class="d-flex justify-content-end" style="gap: 6px;">
                            <button class="btn btn-sm btn-primary font-weight-bolder text-nowrap px-3" id="{{ $idApply }}">
                                <i class="fas {{ $btnIcon }} mr-1 font-size-xs"></i> {{ $btnText }}
                            </button>
                            @if ($showReset)
                                <button type="button" class="btn btn-sm btn-light-danger font-weight-bolder text-nowrap px-3"
                                    id="{{ $idReset }}">
                                    <i class="fas {{ $btnResetIcon }} mr-1 font-size-xs"></i> {{ $btnResetText }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const resetBtn = document.getElementById(@json($idReset));
                if (!resetBtn) return;
                resetBtn.addEventListener('click', function() {
                    const root = resetBtn.closest('.card');
                    if (!root) return;

                    root.querySelectorAll('input[type="text"], input[type="search"], input[type="hidden"]')
                        .forEach(function(el) {
                            if (el.dataset.skipReset === 'true') return;
                            if (el.type === 'hidden') {
                                if (el.id === @json($idStartDate)) {
                                    el.value = @json($activeStartDate);
                                } else if (el.id === @json($idEndDate)) {
                                    el.value = @json($activeEndDate);
                                } else {
                                    el.value = '';
                                }
                            } else {
                                el.value = '';
                            }
                        });

                    root.querySelectorAll('select').forEach(function(el) {
                        if (el.dataset.skipReset === 'true') return;
                        if (el.multiple) {
                            Array.from(el.options).forEach(function(opt) {
                                opt.selected = false;
                            });
                        } else {
                            el.selectedIndex = 0;
                        }
                    });

                    ['.selectpicker', '.select2-product', '.select2-pm', '.select2-coverage', '.select2-outlet',
                        '.select2-month'
                    ]
                    .forEach(function(selector) {
                        const $el = window.jQuery && window.jQuery(root.querySelector(selector));
                        if ($el && $el.length) {
                            if ($el.hasClass('select2-initialized')) {
                                $el.val(null).trigger('change');
                            } else {
                                $el.selectpicker && $el.selectpicker('refresh');
                            }
                        }
                    });

                    window.dispatchEvent(new CustomEvent('filter:reset', {
                        detail: {
                            id: @json($idReset)
                        }
                    }));
                });
            });
        </script>
    @endpush
@endonce

@once
    @push('styles')
        <style>
            /* ===== GLOBAL ULTRA-COMPACT DROPDOWN MENUS (Select2, Selectpicker, Datepicker) ===== */
            .select2-dropdown {
                font-size: 0.76rem !important;
                border-color: #E4E6EF !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
            }
            .select2-results__option {
                padding: 4px 8px !important;
                font-size: 0.76rem !important;
                line-height: 1.35 !important;
            }
            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #3699FF !important;
                color: #ffffff !important;
            }
            .select2-search--dropdown {
                padding: 4px 6px !important;
            }
            .select2-search--dropdown .select2-search__field {
                padding: 3px 6px !important;
                font-size: 0.76rem !important;
                height: 28px !important;
                border-radius: 4px !important;
                border-color: #E4E6EF !important;
            }

            /* Select2 Multi-Select Compact Choice Pills */
            .select2-container--default .select2-selection--multiple {
                min-height: 32px !important;
                max-height: 64px !important;
                overflow-y: auto !important;
                height: auto !important;
                padding: 1px 4px !important;
                border-color: #E4E6EF !important;
                font-size: 0.75rem !important;
            }
            .select2-container--default .select2-selection--multiple::-webkit-scrollbar {
                width: 4px;
            }
            .select2-container--default .select2-selection--multiple::-webkit-scrollbar-thumb {
                background: #CBD5E1;
                border-radius: 4px;
            }
            .select2-container--default .select2-selection--multiple .select2-selection__rendered {
                padding: 0 2px !important;
                margin: 0 !important;
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                gap: 3px !important;
            }
            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                background-color: #F3F6F9 !important;
                border: 1px solid #E4E6EF !important;
                border-radius: 4px !important;
                padding: 1px 6px !important;
                margin: 2px 0 !important;
                font-size: 0.70rem !important;
                font-weight: 500 !important;
                color: #3F4254 !important;
                line-height: 1.3 !important;
                display: inline-flex !important;
                align-items: center !important;
                max-width: 170px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
                color: #7E8299 !important;
                font-size: 0.75rem !important;
                line-height: 1 !important;
                margin-right: 4px !important;
                border: none !important;
                background: transparent !important;
                cursor: pointer !important;
                float: none !important;
                position: static !important;
                display: inline-block !important;
            }
            .select2-container--default .select2-selection--multiple .select2-selection__choice__remove::before,
            .select2-container--default .select2-selection--multiple .select2-selection__choice__remove::after {
                content: "" !important;
                display: none !important;
            }
            .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
                color: #F64E60 !important;
                background: transparent !important;
            }
            .select2-container--default .select2-selection--multiple .select2-search--inline {
                margin: 0 !important;
                padding: 0 !important;
            }
            .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
                margin: 2px 0 !important;
                padding: 0 4px !important;
                height: 22px !important;
                font-size: 0.75rem !important;
                line-height: 1.3 !important;
            }

            /* Bootstrap Selectpicker Popup Menu */
            .bootstrap-select .dropdown-menu {
                font-size: 0.76rem !important;
                padding: 4px 0 !important;
                border-color: #E4E6EF !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
            }
            .bootstrap-select .dropdown-menu.inner {
                padding: 2px 0 !important;
            }
            .bootstrap-select .dropdown-menu li a {
                padding: 4px 10px !important;
                font-size: 0.76rem !important;
                line-height: 1.35 !important;
                color: #3F4254 !important;
            }
            .bootstrap-select .dropdown-menu li a:hover,
            .bootstrap-select .dropdown-menu li.active a {
                background-color: #F3F6F9 !important;
                color: #3699FF !important;
            }
            .bootstrap-select .bs-actionsbox {
                padding: 4px 8px !important;
                background: #F8FAFC !important;
                border-bottom: 1px solid #E2E8F0 !important;
            }
            .bootstrap-select .bs-actionsbox .btn {
                padding: 2px 8px !important;
                font-size: 0.70rem !important;
                font-weight: 600 !important;
                height: 24px !important;
                line-height: 1.2 !important;
            }
            .bootstrap-select .bs-searchbox {
                padding: 4px 8px !important;
            }
            .bootstrap-select .bs-searchbox .form-control {
                padding: 3px 8px !important;
                font-size: 0.76rem !important;
                height: 28px !important;
                border-radius: 4px !important;
            }

            /* DateRangePicker Popup Menu */
            .daterangepicker {
                font-size: 0.75rem !important;
                border-color: #E4E6EF !important;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
                padding: 6px !important;
            }
            .daterangepicker .calendar-table th,
            .daterangepicker .calendar-table td {
                font-size: 0.72rem !important;
                min-width: 24px !important;
                height: 24px !important;
                line-height: 24px !important;
                padding: 0 !important;
            }
            .daterangepicker .drp-buttons {
                padding: 4px 8px !important;
            }
            .daterangepicker .drp-buttons .btn {
                font-size: 0.72rem !important;
                padding: 2px 8px !important;
                height: 26px !important;
            }
            .daterangepicker .ranges li {
                font-size: 0.75rem !important;
                padding: 4px 10px !important;
            }

            /* ===== 14-inch Laptop Responsive Compact Optimizations (1200px - 1536px) ===== */
            @media (min-width: 1200px) and (max-width: 1536px) {
                .gutter-b {
                    margin-bottom: 0.85rem !important;
                }
                .card-custom .card-header {
                    min-height: 44px !important;
                    padding-top: 0.5rem !important;
                    padding-bottom: 0.5rem !important;
                }
                .card-custom .card-body {
                    padding: 0.85rem 1rem !important;
                }
                .card-custom .card-title .card-label {
                    font-size: 0.95rem !important;
                }
                /* Stat Cards Compact Sizing */
                .stat-card-responsive .card-body {
                    padding: 0.6rem 0.85rem !important;
                }
                .stat-card-responsive .font-size-h3,
                .stat-card-responsive .font-size-h4 {
                    font-size: 1.15rem !important;
                    line-height: 1.2;
                }
                .stat-card-responsive .symbol-40,
                .stat-card-responsive .symbol-35 {
                    width: 32px !important;
                    height: 32px !important;
                }
                .stat-card-responsive .symbol-40 .symbol-label,
                .stat-card-responsive .symbol-35 .symbol-label {
                    width: 32px !important;
                    height: 32px !important;
                }
                /* Filter Bar Compact Optimizations */
                .filter-bar-responsive label {
                    font-size: 0.72rem !important;
                    font-weight: 700 !important;
                    color: #3F4254 !important;
                    margin-bottom: 0.2rem !important;
                }
                .filter-bar-responsive .form-control,
                .filter-bar-responsive .selectpicker,
                .filter-bar-responsive .select2-container .select2-selection--single {
                    font-size: 0.78rem !important;
                    min-height: 32px !important;
                    height: 32px !important;
                    border-color: #E4E6EF !important;
                }
                .filter-bar-responsive .select2-container .select2-selection--multiple {
                    font-size: 0.75rem !important;
                    min-height: 32px !important;
                    max-height: 64px !important;
                    overflow-y: auto !important;
                    height: auto !important;
                    border-color: #E4E6EF !important;
                }
                .filter-bar-responsive .bootstrap-select .dropdown-toggle .filter-option-inner-inner {
                    font-size: 0.78rem !important;
                    font-weight: 500 !important;
                    line-height: 1.4 !important;
                }
                .filter-bar-responsive .btn {
                    padding: 0.35rem 0.75rem !important;
                    font-size: 0.78rem !important;
                    height: 32px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                }
                /* Datatable & Tables */
                .datatable-card-responsive .card-header {
                    padding-top: 0.65rem !important;
                    padding-bottom: 0.65rem !important;
                }
                .table-responsive .table th,
                .table-responsive .table td {
                    padding: 0.4rem 0.55rem !important;
                    font-size: 0.78rem !important;
                }
                .table-responsive .table td .badge {
                    font-size: 0.72rem !important;
                }
                .dataTables_wrapper .dataTables_length,
                .dataTables_wrapper .dataTables_filter,
                .dataTables_wrapper .dataTables_info,
                .dataTables_wrapper .dataTables_paginate {
                    font-size: 0.78rem !important;
                }
            }
        </style>
    @endpush
@endonce

