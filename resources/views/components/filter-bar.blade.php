@props([
    'showHeader' => true,
    'title' => 'Performance Selling Out',
    'subtitle' => '',
    'icon' => 'fa-shopping-cart',
    'months' => [],
    'years' => [],
    'pilars' => [],
    'distributors' => ['KFTD', 'AAM', 'METD'],
    'divisis' => ['ETHICAL', 'CONSUMER', 'OGB'],
    'regions' => ['REGION 1', 'REGION 2', 'REGION 3', 'REGION 4', 'REGION 5'],
    'pms' => [],
    'employees' => [],
    'selectedMonth' => null,
    'selectedYear' => null,
    'showBulan' => false,
    'showTahun' => false,
    'showPilar' => true,
    'showDistributor' => false,
    'showDivisi' => false,
    'showRegion' => false,
    'showPm' => false,
    'showProduct' => false,
    'showEmployee' => false,
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
    'idDivisi' => 'filter_divisi',
    'idRegion' => 'filter_region',
    'idPm' => 'filter_pm',
    'idProduct' => 'filter_product',
    'idEmployee' => 'filter_employee',
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
    'stackedActions' => true,
])

@php
    $activeMonths = collect((array) ($selectedMonth ?? now()->month))->map(fn($m) => (string) (int) $m)->all();
    $activeYear = (int) ($selectedYear ?? now()->year);
    $yearOptions = count($years) ? $years : range(now()->year, now()->year - 3);
    $activeStartDate = $selectedStartDate ?? ($periodRequired ? now()->startOfMonth()->toDateString() : '');
    $activeEndDate = $selectedEndDate ?? ($periodRequired ? now()->endOfMonth()->toDateString() : '');
@endphp

<div class="card card-custom filter-bar-responsive mb-5">
    <div class="card-body p-2 p-md-3">
        @if ($showHeader)
            <div class="filter-bar-header d-flex align-items-center justify-content-between mb-2 pb-2">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-30 symbol-light-primary mr-2 flex-shrink-0">
                        <span class="symbol-label">
                            <i class="fas {{ $icon }} text-primary icon-md font-size-sm"></i>
                        </span>
                    </div>
                    <div class="card-title mb-0">
                        <h4 class="card-label font-weight-bolder mb-0 font-size-h6 text-nowrap">{{ $title }}</h4>
                        @if ($subtitle)
                            <span class="d-block text-muted font-size-xs font-weight-bold mt-0 text-nowrap">
                                {{ $subtitle }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="filter-strip d-flex align-items-center">
            <div class="filter-tiles-container d-flex flex-wrap align-items-center flex-grow-1">
                @if ($showPeriod)
                    <div class="filter-tile filter-tile-period">
                        <span class="filter-tile-label">{{ $periodLabel }}</span>
                        <div class="input-group">
                            <input type="text" id="{{ $idPeriod }}"
                                class="form-control dashboard-date-range bg-transparent"
                                data-start-input="#{{ $idStartDate }}" data-end-input="#{{ $idEndDate }}"
                                data-required="{{ $periodRequired ? 'true' : 'false' }}" readonly placeholder="All">
                        </div>
                        <input type="hidden" id="{{ $idStartDate }}" value="{{ $activeStartDate }}">
                        <input type="hidden" id="{{ $idEndDate }}" value="{{ $activeEndDate }}">
                    </div>
                @endif

                @if ($showDistributor)
                    <div class="filter-tile">
                        <span class="filter-tile-label">Distributor</span>
                        <select id="{{ $idDistributor }}" class="form-control selectpicker" data-width="100%" multiple
                            data-actions-box="true" data-selected-text-format="count > 1" data-none-selected-text="All"
                            data-select-all-text="Semua" data-deselect-all-text="Reset"
                            data-count-selected-text="{0} terpilih">
                            @foreach ($distributors as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($showDivisi)
                    <div class="filter-tile">
                        <span class="filter-tile-label">Divisi</span>
                        <select id="{{ $idDivisi }}" class="form-control selectpicker" data-width="100%" multiple
                            data-actions-box="true" data-selected-text-format="count > 1" data-none-selected-text="All"
                            data-select-all-text="Semua" data-deselect-all-text="Reset"
                            data-count-selected-text="{0} terpilih">
                            @foreach ($divisis as $div)
                                <option value="{{ $div }}">{{ $div }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($showRegion)
                    <div class="filter-tile">
                        <span class="filter-tile-label">Region</span>
                        <select id="{{ $idRegion }}" class="form-control selectpicker" data-width="100%" multiple
                            data-actions-box="true" data-selected-text-format="count > 1" data-none-selected-text="All"
                            data-select-all-text="Semua" data-deselect-all-text="Reset"
                            data-count-selected-text="{0} terpilih">
                            @foreach ($regions as $reg)
                                <option value="{{ $reg }}">{{ $reg }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($showPilar)
                    <div class="filter-tile">
                        <span class="filter-tile-label">Pilar</span>
                        <select id="{{ $idPilar }}" class="form-control selectpicker" data-width="100%" multiple
                            data-actions-box="true" data-selected-text-format="count > 1" data-none-selected-text="All"
                            data-select-all-text="Semua" data-deselect-all-text="Reset"
                            data-count-selected-text="{0} terpilih">
                            @foreach ($pilars as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($showPm)
                    <div class="filter-tile">
                        <span class="filter-tile-label">PM</span>
                        <select id="{{ $idPm }}" class="form-control select2-pm" style="width: 100%;" multiple
                            data-placeholder="All">
                            @foreach ($pms as $pm)
                                <option value="{{ $pm }}">{{ $pm }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($showProduct)
                    <div class="filter-tile filter-tile-product">
                        <span class="filter-tile-label">Produk</span>
                        <select id="{{ $idProduct }}" class="form-control select2-product" style="width: 100%;"
                            multiple data-placeholder="All"></select>
                    </div>
                @endif

                @if ($showEmployee)
                    <div class="filter-tile filter-tile-employee">
                        <span class="filter-tile-label">Employee</span>
                        <select id="{{ $idEmployee }}" class="form-control select2-employee" style="width: 100%;"
                            multiple data-placeholder="All">
                            @foreach ($employees as $emp)
                                <option value="{{ $emp }}">{{ $emp }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Legacy filters: Bulan, Tahun, PIC, Outlet --}}
                @if ($showBulan)
                    <div class="filter-tile" style="flex: 0.9 1 95px; min-width: 90px; max-width: 125px;">
                        <span class="filter-tile-label">Bulan</span>
                        <select id="{{ $idBulan }}"
                            class="form-control {{ $bulanMultiple ? 'select2-month' : 'selectpicker' }}"
                            data-width="100%" style="width: 100%;" @if ($bulanMultiple) multiple @endif>
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
                    <div class="filter-tile" style="flex: 0.8 1 80px; min-width: 75px; max-width: 100px;">
                        <span class="filter-tile-label">Tahun</span>
                        <select id="{{ $idTahun }}" class="form-control selectpicker" data-width="100%">
                            @foreach ($yearOptions as $y)
                                <option value="{{ $y }}" {{ (int) $y === $activeYear ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($showCoverage)
                    <div class="filter-tile">
                        <span class="filter-tile-label">PIC</span>
                        <select id="{{ $idCoverage }}" class="form-control select2-coverage" style="width: 100%;">
                            <option value="">All</option>
                        </select>
                    </div>
                @endif

                @if ($showOutlet)
                    <div class="filter-tile">
                        <span class="filter-tile-label">Outlet</span>
                        <select id="{{ $idOutlet }}" class="form-control select2-outlet" style="width: 100%;">
                            <option value="">All</option>
                        </select>
                    </div>
                @endif

                {{ $slot }}
            </div>

            {{-- Stacked Action Buttons (Terapkan on top, Reset on bottom) --}}
            <div class="filter-actions-stacked d-flex flex-column justify-content-center ml-2">
                <button class="btn btn-xs btn-primary font-weight-bolder text-nowrap px-2 py-1"
                    id="{{ $idApply }}">
                    <i class="fas {{ $btnIcon }} mr-1 font-size-xs"></i> {{ $btnText }}
                </button>
                @if ($showReset)
                    <button type="button"
                        class="btn btn-xs btn-light-danger font-weight-bolder text-nowrap px-2 py-1"
                        id="{{ $idReset }}">
                        <i class="fas {{ $btnResetIcon }} mr-1 font-size-xs"></i> {{ $btnResetText }}
                    </button>
                @endif
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

                    ['.selectpicker', '.select2-product', '.select2-pm', '.select2-employee',
                        '.select2-coverage',
                        '.select2-outlet', '.select2-month'
                    ]
                    .forEach(function(selector) {
                        const $el = window.jQuery && window.jQuery(root.querySelectorAll(selector));
                        if ($el && $el.length) {
                            $el.each(function() {
                                const $item = window.jQuery(this);
                                if ($item.hasClass('select2-hidden-accessible') || $item.data(
                                        'select2')) {
                                    $item.val(null).trigger('change');
                                } else if ($item.data('selectpicker') || $item.hasClass(
                                        'selectpicker')) {
                                    $item.selectpicker && $item.selectpicker('refresh');
                                }
                            });
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
            /* ===== ULTRA-COMPACT TILE FILTER BAR (SINGLE LINE 8-FILTER ARCHITECTURE) ===== */
            .filter-bar-responsive .filter-bar-header {
                border-bottom: 1px solid #EBEDF3;
            }

            .filter-bar-responsive .filter-strip {
                width: 100%;
                display: flex;
                align-items: center;
            }

            .filter-bar-responsive .filter-tiles-container {
                display: flex;
                align-items: center;
                gap: 5px;
                flex: 1 1 auto;
                min-width: 0;
            }

            /* Single Filter Tile Box (Ultra-Compact) */
            .filter-bar-responsive .filter-tile,
            .filter-bar-responsive .filter-item {
                background: #ffffff;
                border: 1px solid #E4E6EF;
                border-radius: 6px;
                padding: 2px 6px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                min-height: 44px;
                height: 44px;
                flex: 1 1 100px;
                min-width: 88px;
                max-width: 150px;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
                box-sizing: border-box;
                position: relative;
                margin-bottom: 0 !important;
            }

            .filter-bar-responsive .filter-tile:hover,
            .filter-bar-responsive .filter-tile:focus-within {
                border-color: #B5B5C3;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            }

            .filter-bar-responsive .filter-tile-period {
                flex: 1.6 1 170px !important;
                min-width: 155px !important;
                max-width: 215px !important;
            }

            .filter-bar-responsive .filter-tile-product,
            .filter-bar-responsive .filter-tile-employee {
                flex: 1.25 1 120px !important;
                min-width: 105px !important;
                max-width: 165px !important;
            }

            /* Tile Label (Small, Uppercase, Crisp) */
            .filter-bar-responsive .filter-tile-label,
            .filter-bar-responsive .filter-tile>label,
            .filter-bar-responsive .filter-item>label {
                font-size: 0.60rem !important;
                font-weight: 700 !important;
                color: #7E8299 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.03em !important;
                margin-bottom: 0 !important;
                line-height: 1.1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                display: block !important;
                pointer-events: none;
            }

            /* Form controls inside Filter Tiles */
            .filter-bar-responsive .filter-tile .form-control,
            .filter-bar-responsive .filter-tile .bootstrap-select,
            .filter-bar-responsive .filter-tile .bootstrap-select>.btn,
            .filter-bar-responsive .filter-tile .select2-container,
            .filter-bar-responsive .filter-item .form-control,
            .filter-bar-responsive .filter-item .bootstrap-select>.btn {
                height: 20px !important;
                min-height: 20px !important;
                border: none !important;
                padding: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
                font-size: 0.74rem !important;
                font-weight: 600 !important;
                color: #181C32 !important;
                line-height: 20px !important;
                border-radius: 0 !important;
                width: 100% !important;
            }

            .filter-bar-responsive .filter-tile-period .form-control {
                font-size: 0.68rem !important;
                font-weight: 600 !important;
            }

            .filter-bar-responsive .filter-tile .bootstrap-select>.btn,
            .filter-bar-responsive .filter-item .bootstrap-select>.btn {
                padding: 0 14px 0 0 !important;
                display: flex !important;
                align-items: center !important;
                background-color: transparent !important;
            }

            .filter-bar-responsive .filter-tile .bootstrap-select .dropdown-toggle .filter-option-inner-inner,
            .filter-bar-responsive .filter-item .bootstrap-select .dropdown-toggle .filter-option-inner-inner {
                font-size: 0.74rem !important;
                font-weight: 600 !important;
                color: #181C32 !important;
                line-height: 20px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }

            .filter-bar-responsive .filter-tile .input-group {
                height: 20px !important;
            }

            .filter-bar-responsive .filter-tile .input-group-append {
                margin-left: 0 !important;
            }

            .filter-bar-responsive .filter-tile .input-group-text {
                border: none !important;
                background: transparent !important;
                padding: 0 2px !important;
                height: 20px !important;
                color: #B5B5C3 !important;
            }

            /* Select2 inside filter-tile */
            .filter-bar-responsive .filter-tile .select2-container--default .select2-selection--single,
            .filter-bar-responsive .filter-tile .select2-container--default .select2-selection--multiple,
            .filter-bar-responsive .filter-item .select2-container--default .select2-selection--multiple {
                border: none !important;
                background: transparent !important;
                min-height: 20px !important;
                height: 20px !important;
                padding: 0 !important;
                line-height: 20px !important;
                overflow: hidden !important;
            }

            .filter-bar-responsive .filter-tile .select2-container--default .select2-selection--single .select2-selection__rendered {
                padding: 0 !important;
                line-height: 20px !important;
                font-size: 0.74rem !important;
                font-weight: 600 !important;
                color: #181C32 !important;
            }

            .filter-bar-responsive .filter-tile .select2-container--default .select2-selection--multiple .select2-selection__rendered,
            .filter-bar-responsive .filter-item .select2-container--default .select2-selection--multiple .select2-selection__rendered {
                padding: 0 !important;
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
                line-height: 20px !important;
                gap: 2px !important;
            }

            .filter-bar-responsive .filter-tile .select2-container--default .select2-selection--multiple .select2-selection__choice,
            .filter-bar-responsive .filter-item .select2-container--default .select2-selection--multiple .select2-selection__choice {
                margin: 0 2px 0 0 !important;
                padding: 0 3px !important;
                font-size: 0.65rem !important;
                line-height: 18px !important;
                height: 18px !important;
                border-radius: 3px !important;
                background-color: #F3F6F9 !important;
                border: 1px solid #E4E6EF !important;
                color: #3F4254 !important;
            }

            .filter-bar-responsive .filter-tile .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field,
            .filter-bar-responsive .filter-item .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
                margin: 0 !important;
                height: 20px !important;
                font-size: 0.72rem !important;
                line-height: 20px !important;
            }

            /* Stacked Action Buttons (Terapkan + Reset) */
            .filter-bar-responsive .filter-actions-stacked {
                width: 74px;
                flex: 0 0 74px;
                height: 44px;
                gap: 4px;
            }

            .filter-bar-responsive .filter-actions-stacked .btn {
                height: 20px !important;
                min-height: 20px !important;
                padding: 0 4px !important;
                font-size: 0.68rem !important;
                line-height: 20px !important;
                font-weight: 700 !important;
                border-radius: 4px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            /* Desktop (>= 1200px): Force STRICT SINGLE LINE */
            @media (min-width: 1200px) {
                .filter-bar-responsive .filter-tiles-container {
                    flex-wrap: nowrap !important;
                }
            }

            /* Tablet Breakpoint (768px - 1199px): 2 balanced rows */
            @media (min-width: 768px) and (max-width: 1199px) {
                .filter-bar-responsive .filter-tiles-container {
                    flex-wrap: wrap !important;
                }

                .filter-bar-responsive .filter-tile,
                .filter-bar-responsive .filter-item {
                    flex: 1 1 calc(25% - 6px) !important;
                    min-width: calc(25% - 6px) !important;
                    max-width: 100% !important;
                }
            }

            /* Mobile Breakpoint (< 768px): 2 columns or 1 column */
            @media (max-width: 767px) {
                .filter-bar-responsive .filter-strip {
                    flex-direction: column !important;
                    align-items: stretch !important;
                }

                .filter-bar-responsive .filter-tiles-container {
                    flex-wrap: wrap !important;
                    width: 100% !important;
                }

                .filter-bar-responsive .filter-tile,
                .filter-bar-responsive .filter-item {
                    flex: 1 1 calc(50% - 4px) !important;
                    min-width: calc(50% - 4px) !important;
                    max-width: 100% !important;
                }

                .filter-bar-responsive .filter-actions-stacked {
                    width: 100% !important;
                    flex: 1 1 auto !important;
                    flex-direction: row !important;
                    margin-left: 0 !important;
                    margin-top: 6px !important;
                    height: 30px !important;
                }

                .filter-bar-responsive .filter-actions-stacked .btn {
                    flex: 1 1 auto !important;
                    height: 28px !important;
                    min-height: 28px !important;
                }
            }

            /* ===== ULTRA-COMPACT DROPDOWN POPUPS (DateRangePicker, Selectpicker, Select2) ===== */
            /* Select2 Dropdown */
            .select2-dropdown {
                font-size: 0.72rem !important;
                border-color: #E4E6EF !important;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
                border-radius: 6px !important;
            }

            .select2-results__option {
                padding: 3px 8px !important;
                font-size: 0.72rem !important;
                line-height: 1.3 !important;
            }

            .select2-search--dropdown {
                padding: 4px 6px !important;
            }

            .select2-search--dropdown .select2-search__field {
                padding: 2px 6px !important;
                font-size: 0.72rem !important;
                height: 26px !important;
                border-radius: 4px !important;
                border-color: #E4E6EF !important;
            }

            /* Bootstrap Selectpicker Popup & Action Buttons */
            .bootstrap-select .dropdown-menu {
                min-width: 160px !important;
                font-size: 0.72rem !important;
                padding: 4px 0 !important;
                border-color: #E4E6EF !important;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
                border-radius: 6px !important;
            }

            .bootstrap-select .dropdown-menu.inner {
                padding: 2px 0 !important;
            }

            .bootstrap-select .dropdown-menu li a {
                padding: 3px 10px !important;
                font-size: 0.72rem !important;
                line-height: 1.35 !important;
                color: #3F4254 !important;
            }

            .bootstrap-select .dropdown-menu li a:hover,
            .bootstrap-select .dropdown-menu li.active a {
                background-color: #F3F6F9 !important;
                color: #3699FF !important;
            }

            .bootstrap-select .bs-actionsbox {
                padding: 4px 6px !important;
                background: #F8FAFC !important;
                border-bottom: 1px solid #E2E8F0 !important;
            }

            .bootstrap-select .bs-actionsbox .btn-group {
                display: flex !important;
                width: 100% !important;
                gap: 4px !important;
            }

            .bootstrap-select .bs-actionsbox .btn {
                padding: 1px 6px !important;
                font-size: 0.68rem !important;
                font-weight: 600 !important;
                height: 22px !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                flex: 1 1 auto !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            /* DateRangePicker Ultra-Compact Popup */
            .daterangepicker {
                font-size: 0.72rem !important;
                border-color: #E4E6EF !important;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
                padding: 4px !important;
                border-radius: 6px !important;
            }

            .daterangepicker .ranges {
                padding: 4px !important;
            }

            .daterangepicker .ranges ul {
                width: 130px !important;
            }

            .daterangepicker .ranges li {
                font-size: 0.70rem !important;
                padding: 3px 8px !important;
                border-radius: 4px !important;
                margin-bottom: 2px !important;
                line-height: 1.3 !important;
                color: #3F4254 !important;
            }

            .daterangepicker .ranges li.active,
            .daterangepicker .ranges li:hover {
                background-color: #F3F6F9 !important;
                color: #3699FF !important;
            }

            .daterangepicker .calendar-table th,
            .daterangepicker .calendar-table td {
                font-size: 0.68rem !important;
                min-width: 22px !important;
                height: 22px !important;
                line-height: 22px !important;
                padding: 0 !important;
            }

            .daterangepicker .drp-buttons {
                padding: 4px 6px !important;
            }

            .daterangepicker .drp-buttons .btn {
                font-size: 0.68rem !important;
                padding: 2px 6px !important;
                height: 22px !important;
            }
        </style>
    @endpush
@endonce
