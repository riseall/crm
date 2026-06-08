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
    'bulanMultiple' => false,
    'btnText' => 'Terapkan',
    'btnIcon' => 'fa-filter',
    'idBulan' => 'filter_bulan',
    'idTahun' => 'filter_tahun',
    'idPilar' => 'filter_pilar',
    'idDistributor' => 'filter_distributor',
    'idCoverage' => 'filter_coverage_id',
    'idOutlet' => 'filter_outlet_id',
    'idApply' => 'btnApplyFilter',
])

@php
    $activeMonths = collect((array) ($selectedMonth ?? now()->month))->map(fn($m) => (string) (int) $m)->all();
    $activeYear = (int) ($selectedYear ?? now()->year);
    $yearOptions = count($years) ? $years : range(now()->year, now()->year - 3);
@endphp

<div class="card card-custom gutter-b">
    <div class="card-body">
        <div class="row align-items-start justify-content-between">
            @if ($showHeader)
                <div class="col-12 col-xl-4 mb-4 mb-xl-0">
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

            <div class="col-12 {{ $showHeader ? 'col-xl-8' : '' }}">
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-5 g-2 align-items-end justify-content-xl-end">
                    @if ($showBulan)
                        <div class="col mb-4">
                            <label class="small text-muted mb-1">Bulan</label>
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
                        <div class="col mb-4">
                            <label class="small text-muted mb-1">Tahun</label>
                            <select id="{{ $idTahun }}" class="form-control selectpicker" data-width="100%">
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}" {{ (int) $y === $activeYear ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($showPilar)
                        <div class="col mb-4">
                            <label class="small text-muted mb-1">Pilar</label>
                            <select id="{{ $idPilar }}" class="form-control selectpicker" data-width="100%">
                                <option value="">Semua pilar</option>
                                @foreach ($pilars as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($showDistributor)
                        <div class="col mb-4">
                            <label class="small text-muted mb-1">Distributor</label>
                            <select id="{{ $idDistributor }}" class="form-control selectpicker" data-width="100%">
                                <option value="">Semua</option>
                                @foreach ($distributors as $d)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($showCoverage)
                        <div class="col mb-4">
                            <label class="small text-muted mb-1">PIC</label>
                            <select id="{{ $idCoverage }}" class="form-control select2-coverage"
                                style="width: 100%;">
                                <option value="">Semua PIC</option>
                            </select>
                        </div>
                    @endif

                    @if ($showOutlet)
                        <div class="col mb-4">
                            <label class="small text-muted mb-1">Outlet</label>
                            <select id="{{ $idOutlet }}" class="form-control select2-outlet" style="width: 100%;">
                                <option value="">Semua Outlet</option>
                            </select>
                        </div>
                    @endif

                    {{ $slot }}

                    <div class="col mb-4 text-right">
                        <label class="small text-muted mb-1 d-block invisible">Aksi</label>
                        <button class="btn btn-primary font-weight-bolder" id="{{ $idApply }}">
                            <i class="fas {{ $btnIcon }} mr-1"></i> {{ $btnText }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
