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
    $headerColumnClass = $showPeriod ? 'col-xl-3' : 'col-xl-4';
    $filterColumnClass = $showPeriod ? 'col-xl-9' : 'col-xl-8';
    $controlColumnClass = 'col-6 col-sm-4 col-lg-3';
@endphp

<div class="card card-custom gutter-b">
    <div class="card-body">
        <div class="row align-items-start justify-content-between">
            @if ($showHeader)
                <div class="col-12 {{ $headerColumnClass }} mb-4 mb-xl-0">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-50 symbol-light-primary mr-5">
                            <span class="symbol-label">
                                <i class="fas {{ $icon }} text-primary icon-lg"></i>
                            </span>
                        </div>
                        <div class="card-title mb-0">
                            <h4 class="card-label font-weight-bolder mb-0">{{ $title }}</h4>
                            @if ($subtitle)
                                <span class="d-block text-muted font-size-sm font-weight-bold mt-1">
                                    {{ $subtitle }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-12 {{ $showHeader ? $filterColumnClass : '' }}">
                <div class="row align-items-end justify-content-xl-end">
                    @if ($showPeriod)
                        <div class="col-12 col-sm-8 col-lg-4 mb-4">
                            <label class="small text-muted mb-1">{{ $periodLabel }}</label>
                            <div class="input-group">
                                <input type="text" id="{{ $idPeriod }}"
                                    class="form-control dashboard-date-range bg-white"
                                    data-start-input="#{{ $idStartDate }}" data-end-input="#{{ $idEndDate }}"
                                    data-required="{{ $periodRequired ? 'true' : 'false' }}" readonly
                                    placeholder="Pilih interval tanggal">
                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        <i class="far fa-calendar-alt"></i>
                                    </span>
                                </div>
                            </div>
                            <input type="hidden" id="{{ $idStartDate }}" value="{{ $activeStartDate }}">
                            <input type="hidden" id="{{ $idEndDate }}" value="{{ $activeEndDate }}">
                        </div>
                    @endif

                    @if ($showBulan)
                        <div class="{{ $controlColumnClass }} mb-4">
                            <label class="small text-muted mb-1">Bulan</label>
                            <select id="{{ $idBulan }}"
                                class="form-control {{ $bulanMultiple ? 'select2-month' : 'selectpicker' }}"
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
                        <div class="{{ $controlColumnClass }} mb-4">
                            <label class="small text-muted mb-1">Tahun</label>
                            <select id="{{ $idTahun }}" class="form-control selectpicker" data-width="100%">
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
                        <div class="{{ $controlColumnClass }} mb-4">
                            <label class="small text-muted mb-1">Pilar</label>
                            <select id="{{ $idPilar }}" class="form-control selectpicker" data-width="100%"
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
                        <div class="{{ $controlColumnClass }} mb-4">
                            <label class="small text-muted mb-1">Distributor</label>
                            <select id="{{ $idDistributor }}" class="form-control selectpicker" data-width="100%"
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
                        <div class="{{ $controlColumnClass }} mb-4">
                            <label class="small text-muted mb-1">PIC</label>
                            <select id="{{ $idCoverage }}" class="form-control select2-coverage"
                                style="width: 100%;">
                                <option value="">Semua PIC</option>
                            </select>
                        </div>
                    @endif

                    @if ($showOutlet)
                        <div class="{{ $controlColumnClass }} mb-4">
                            <label class="small text-muted mb-1">Outlet</label>
                            <select id="{{ $idOutlet }}" class="form-control select2-outlet" style="width: 100%;">
                                <option value="">Semua Outlet</option>
                            </select>
                        </div>
                    @endif

                    {{ $slot }}

                    <div class="col-6 col-sm-4 col-lg-auto mb-4 text-right">
                        <label class="small text-muted mb-1 d-block invisible">Aksi</label>
                        <div class="d-flex justify-content-end" style="gap: 8px;">
                            <button class="btn btn-primary font-weight-bolder text-nowrap" id="{{ $idApply }}">
                                <i class="fas {{ $btnIcon }} mr-1"></i> {{ $btnText }}
                            </button>
                            @if ($showReset)
                                <button type="button" class="btn btn-light-danger font-weight-bolder text-nowrap"
                                    id="{{ $idReset }}">
                                    <i class="fas {{ $btnResetIcon }} mr-1"></i> {{ $btnResetText }}
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
