@extends('layouts.app', ['title' => 'Outlet'])

@push('styles')
    <link rel="stylesheet"
        href="{{ asset('css/outlet/modal.css') }}?v={{ filemtime(public_path('css/outlet/modal.css')) }}">
@endpush

@section('content')
    <div class="row mb-3">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="total master outlet" value="{{ $totalData }}" subtitle="Entitas di Coverage"
                subtitleColor="text-primary" icon="far fa-folder-open" color="primary">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="outlet tercoverage" value="{{ $totalTercoverage }}" subtitle="Sudah memiliki Sales Rep"
                subtitleColor="text-info" icon="far fa-file-alt" color="info">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="outlet belum tercoverage" value="{{ $totalBelumCoverage }}"
                subtitle="Master belum ada Sales Rep" subtitleColor="text-danger" icon="far fa-file-excel" color="danger">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="sellout belum termapping" value="{{ $totalUnmappedSellout }}" subtitle="Perlu Dipetakan"
                subtitleColor="text-danger" icon="fas fa-exclamation-triangle" color="danger">
            </x-stat-card>
        </div>
    </div>

    <div class="card card-custom gutter-b">
        <div class="card-header card-header-tabs-line border-0 pt-2">
            <div class="card-toolbar">
                <ul class="nav nav-tabs nav-bold nav-tabs-line" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#kt_tab_mapping" id="tabMappingLink">
                            <span class="nav-icon"><i class="fas fa-network-wired"></i></span>
                            <span class="nav-text font-weight-bolder">Master Mapping Outlet - Coverage</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#kt_tab_unmapped_sellout" id="tabUnmappedLink">
                            <span class="nav-icon"><i class="fas fa-exclamation-triangle text-danger"></i></span>
                            <span class="nav-text font-weight-bolder">Outlet Sellout Tanpa Master</span>
                            <span class="badge badge-danger font-weight-bolder ml-2"
                                id="badgeUnmappedCount">{{ $totalUnmappedSellout }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#kt_tab_uncovered_pilar" id="tabUncoveredLink">
                            <span class="nav-icon"><i class="fas fa-user-times text-warning"></i></span>
                            <span class="nav-text font-weight-bolder">Outlet Belum Ada Coverage Pilar</span>
                            <span class="badge badge-warning font-weight-bolder ml-2"
                                id="badgeUncoveredCount">{{ $totalUncoveredPilar }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body p-4 p-xl-6">
            <div class="tab-content">
                <!-- TAB 1: MASTER MAPPING OUTLET - COVERAGE -->
                <div class="tab-pane fade show active" id="kt_tab_mapping" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="font-weight-bolder text-dark mb-1">Daftar Mapping Master Outlet ke Coverage</h4>
                            <span class="text-muted font-size-sm">Hubungan Master Customer (ID Konversi) dengan Coverage dan
                                Sales Rep</span>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <button type="button" class="btn btn-sm btn-xl-md btn-warning font-weight-bolder mr-2"
                                id="btnBulkUpdate">
                                <i class="fas fa-exchange-alt mr-1"></i> Bulk Update
                            </button>
                            <button type="button" class="btn btn-sm btn-xl-md btn-primary font-weight-bolder"
                                id="btnTambah">
                                <i class="fas fa-plus mr-1"></i> Tambah Mapping
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-separate table-head-custom table-checkable w-100" id="tblMapping">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px">No</th>
                                    <th>Kode Outlet (ID Konversi)</th>
                                    <th>Nama Outlet</th>
                                    <th>Segment</th>
                                    <th class="text-center">Pilar</th>
                                    <th class="text-center">ID Coverage</th>
                                    <th class="text-center">NIK</th>
                                    <th>Nama Sales</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 100px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: OUTLET SELLOUT TANPA MASTER (ID KONVERSI) -->
                <div class="tab-pane fade" id="kt_tab_unmapped_sellout" role="tabpanel">
                    <div class="alert alert-custom alert-light-danger fade show mb-4 p-4" role="alert">
                        <div class="alert-icon"><i class="fas fa-info-circle text-danger"></i></div>
                        <div class="alert-text font-size-sm">
                            <strong>Master Distributor Belum Terdaftar:</strong> Outlet di bawah ini tercatat dalam data
                            Sellout, namun <strong>belum memiliki ID Konversi</strong> di Master
                            Customer. Gunakan tombol <strong>Export XLSX</strong> untuk mengisi secara massal atau klik
                            <strong>Petakan</strong> untuk menyambungkan ke Master.
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="font-weight-bolder text-dark mb-1">Outlet Sellout Tanpa Master (ID Konversi)</h4>
                            <span class="text-muted font-size-sm">Data transaksi dari KFTD, AAM, dan METD yang belum
                                memiliki ID Konversi CRM</span>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <a href="{{ route('outlet.unmapped.export') }}"
                                class="btn btn-sm btn-success font-weight-bolder mr-2" id="btnExportUnmapped">
                                <i class="fas fa-file-excel mr-1"></i> Export XLSX
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold"
                                id="btnRefreshUnmapped">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-separate table-head-custom table-checkable w-100"
                            id="tblUnmappedSellout">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px">No</th>
                                    <th class="text-center">Dist</th>
                                    <th>Kode Cabang</th>
                                    <th>Kode Outlet Dist</th>
                                    <th>Nama Outlet Dist</th>
                                    <th>Segment Dist</th>
                                    <th class="text-center" style="width: 100px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: OUTLET SELLOUT BELUM ADA COVERAGE PILAR -->
                <div class="tab-pane fade" id="kt_tab_uncovered_pilar" role="tabpanel">
                    <div class="alert alert-custom alert-light-warning fade show mb-4 p-4" role="alert">
                        <div class="alert-icon"><i class="fas fa-exclamation-triangle text-warning"></i></div>
                        <div class="alert-text font-size-sm">
                            <strong>Pilar Coverage Belum Ada:</strong> Outlet di bawah ini <strong>sudah memiliki ID
                                Konversi</strong> di Master Customer, namun terdapat transaksi penjualan pada <strong>Pilar
                                yang belum ditugaskan ke Sales PIC</strong>. Klik tombol <strong>Tetapkan Coverage</strong>
                            untuk menugaskan sales PIC peng-cover pilar tersebut.
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="font-weight-bolder text-dark mb-1">Outlet Sellout Belum Ter-Coverage Pilar</h4>
                            <span class="text-muted font-size-sm">Master outlet yang memerlukan penetapan Sales Rep untuk
                                pilar transaksi bersangkutan</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold"
                                id="btnRefreshUncovered">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-separate table-head-custom table-checkable w-100"
                            id="tblUncoveredPilar">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px">No</th>
                                    <th>Kode Master (ID Konversi)</th>
                                    <th>Nama Master Outlet</th>
                                    <th>Segment Master</th>
                                    <th class="text-center">Pilar Transaksi</th>
                                    <th class="text-center">Jml Outlet Dist</th>
                                    <th class="text-center" style="width: 160px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('page.outlet.modal')
@endsection

@push('scripts')
    <script>
        window.OutletPageConfig = {
            csrfToken: '{{ csrf_token() }}',
            routes: {
                data: '{{ route('outlet.data') }}',
                unmappedData: '{{ route('outlet.unmapped.data') }}',
                uncoveredData: '{{ route('outlet.uncovered.data') }}',
                unmappedExport: '{{ route('outlet.unmapped.export') }}',
                unmappedStats: '{{ route('outlet.unmapped.stats') }}',
                outletSelect: '{{ route('outlet.outlet') }}',
                coverageSelect: '{{ route('outlet.coverage') }}',
                outletByCoverage: '{{ url('outlet/outlet-by-coverage') }}',
                bulkUpdate: '{{ route('outlet.bulk.update') }}',
                show: '{{ route('outlet.show', ':id') }}',
                store: '{{ route('outlet.store') }}',
                update: '{{ route('outlet.update', ':id') }}',
                destroy: '{{ route('outlet.destroy', ':id') }}',
                unmappedMap: '{{ route('outlet.unmapped.map') }}',
                uncoveredAssign: '{{ route('outlet.uncovered.assign') }}',
            }
        };
    </script>
    <script src="{{ asset('js/outlet/index.js') }}?v={{ filemtime(public_path('js/outlet/index.js')) }}"></script>
    <script src="{{ asset('js/outlet/modal.js') }}?v={{ filemtime(public_path('js/outlet/modal.js')) }}"></script>
@endpush
