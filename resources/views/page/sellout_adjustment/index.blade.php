@extends('layouts.app', ['title' => 'Adjust Data Sellout'])

@section('content')
    <!-- Stat Cards Summary -->
    <div class="row" id="statsContainer">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL TRANSAKSI" value="{{ number_format($totalData, 0, ',', '.') }}" subtitle="Bulan Berjalan"
                subtitleColor="text-primary" icon="far fa-folder-open" color="primary" id="cardTotalData">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL NETTO" value="Rp {{ number_format($totalNetto, 0, ',', '.') }}"
                subtitle="Bulan Berjalan" subtitleColor="text-success" icon="fas fa-coins" color="success"
                id="cardTotalNetto">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="DIVISI REGULER" value="{{ number_format($countReguler, 0, ',', '.') }}" subtitle="Transaksi"
                subtitleColor="text-info" icon="fas fa-layer-group" color="info" id="cardCountReguler">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="DIVISI CMA" value="{{ number_format($countCma, 0, ',', '.') }}" subtitle="Transaksi"
                subtitleColor="text-warning" icon="fas fa-check-circle" color="warning" id="cardCountCma">
            </x-stat-card>
        </div>
    </div>

    <!-- Filter Bar Component -->
    <x-filter-bar :pilars="$pilarOptions" :distributors="$distOptions" title="Adjust Data Sellout"
        subtitle="Filter data sellout berdasarkan periode dan produk" icon="fa-sliders-h"
        :show-header="false" :show-bulan="false" :show-tahun="false" :show-period="true" :show-distributor="false" :show-pilar="false"
        :show-reset="true" id-period="filter_period" id-start-date="filter_start_date" id-end-date="filter_end_date"
        id-distributor="filter_dist" id-pilar="filter_pilar" id-apply="btnApplyFilter" id-reset="btnResetFilter"
        selected-start-date="{{ $startOfMonth }}" selected-end-date="{{ $endOfMonth }}">

        <div class="filter-item filter-item-product">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Produk</label>
            <select id="filter_product_code" class="form-control form-control-sm select2-filter-product"
                style="width: 100%;"></select>
        </div>
    </x-filter-bar>

    <!-- DataTable Card -->
    <x-datatable-card title="Data Sellout & Penyesuaian Divisi"
        subtitle="Kelola dan ubah divisi data transaksi sellout secara single maupun bulk"
        table-id="tblSelloutAdjustment">
        <x-slot name="beforeTable">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 p-3 bg-light rounded">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <span id="selectionBadge"
                        class="badge badge-light-primary text-primary font-weight-bolder py-2 px-3 mr-3"
                        style="display: none;">
                        <i class="fas fa-check-square mr-1"></i> <span id="selectionCount">0</span> baris dipilih
                    </span>
                    <button type="button" class="btn btn-sm btn-primary font-weight-bolder mr-2" id="btnOpenBulkEdit"
                        disabled>
                        <i class="fas fa-edit"></i> Adjust Terpilih
                    </button>
                </div>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-light-primary font-weight-bolder" id="btnRefreshTable">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </x-slot>

        <th width="30" class="text-center">
            <div class="custom-control custom-checkbox text-center">
                <input type="checkbox" class="custom-control-input" id="checkAllRows">
                <label class="custom-control-label" for="checkAllRows"></label>
            </div>
        </th>
        <th>No</th>
        <th>Tgl Faktur</th>
        <th>No Faktur</th>
        <th>Dist</th>
        <th>Cabang</th>
        <th>Outlet</th>
        <th>Kode Produk</th>
        <th>Nama Produk</th>
        <th>Pilar</th>
        <th>Divisi</th>
        <th>Sub Divisi</th>
        <th>Qty</th>
        <th>Netto</th>
        <th width="60" class="text-center">Aksi</th>
    </x-datatable-card>

    <!-- Modals -->
    @include('page.sellout_adjustment.modal')
@endsection

@push('scripts')
    <script
        src="{{ asset('js/dashboard/date-range-filter.js') }}?v={{ filemtime(public_path('js/dashboard/date-range-filter.js')) }}">
    </script>
    <script>
        window.SelloutAdjustmentConfig = {
            csrfToken: '{{ csrf_token() }}',
            divisiSubDivisiMap: @json($divisiSubDivisiMap),
            routes: {
                data: '{{ route('sellout-adjustment.data') }}',
                stats: '{{ route('sellout-adjustment.stats') }}',
                products: '{{ route('sellout-adjustment.products') }}',
                updateSingle: '{{ url('sellout-adjustment') }}/:id',
                updateBulk: '{{ route('sellout-adjustment.update-bulk') }}',
            }
        };
    </script>
    <script src="{{ asset('js/sellout_adjustment/index.js') }}?v={{ filemtime(public_path('js/sellout_adjustment/index.js')) }}">
    </script>
@endpush
