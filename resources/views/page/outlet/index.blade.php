@extends('layouts.app', ['title' => 'Outlet'])

@section('content')
    <div class="row">
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="total outlet" value="{{ $totalData }}" subtitle="Entitas" subtitleColor="text-primary"
                icon="far fa-folder-open" color="primary">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="outlet tercoverage" value="{{ $totalTercoverage }}" subtitle="Entitas"
                subtitleColor="text-info" icon="far fa-file-alt" color="info">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="outlet belum tercoverage" value="{{ $totalBelumCoverage }}" subtitle="Entitas"
                subtitleColor="text-danger" icon="far fa-file-excel" color="danger">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="butuh pengganti coverage" value="{{ $totalButuhPengganti }}" subtitle="Entitas"
                subtitleColor="text-warning" icon="fas fa-history" color="warning">
            </x-stat-card>
        </div>
    </div>

    {{-- <div class="d-flex justify-content-end mb-4">
        <button type="button" class="btn btn-warning font-weight-bolder mr-2" id="btnBulkUpdate">
            <i class="fas fa-exchange-alt"></i> Bulk Update
        </button>
    </div> --}}

    <x-datatable-card title="Master Mapping Outlet - Coverage" subtitle="Daftar mapping outlet ke coverage"
        table-id="tblMapping" showCreate createLabel="Tambah Mapping">
        <th>No</th>
        <th>Dist</th>
        <th>Kode Outlet</th>
        <th>Nama Outlet</th>
        <th>Kode Cabang</th>
        {{-- <th>Nama Cabang</th>
        <th>Kota</th> --}}
        <th>Segment</th>
        <th>Pilar</th>
        <th>Id Coverage</th>
        <th>NIK</th>
        <th>Nama</th>
        <th>Status</th>
        <th>Aksi</th>
    </x-datatable-card>

    @include('page.outlet.modal')
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            window.tableMapping = $('#tblMapping').DataTable({
                processing: true,
                serverSide: true,
                searchDelay: 700,
                autoWidth: false,
                ajax: '{{ route('outlet.data') }}',
                initComplete: function() {
                    var api = this.api();
                    var $input = $('#tblMapping_filter input');
                    var timer = null;

                    $input
                        .off('.DT')
                        .attr('placeholder', 'Min. 2 karakter')
                        .on('keyup search', function(e) {
                            var value = this.value.trim();

                            if (e.type === 'keyup' && e.key === 'Enter') {
                                clearTimeout(timer);
                                if (value.length === 0 || value.length >= 2) {
                                    api.search(value).draw();
                                }
                                return;
                            }

                            clearTimeout(timer);
                            timer = setTimeout(function() {
                                if (value.length === 0 || value.length >= 2) {
                                    api.search(value).draw();
                                }
                            }, 700);
                        });
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'dist',
                        name: 'mst_cust_dist.dist',
                        defaultContent: '-'
                    },
                    {
                        data: 'id_outlet',
                        name: 'mst_cust_dist.id_outlet'
                    },
                    {
                        data: 'nama_outlet',
                        name: 'mst_cust_dist.nama_outlet',
                        defaultContent: '-'
                    },
                    {
                        data: 'kode_cabang',
                        name: 'mst_cust_dist.id_cbg_dist',
                        defaultContent: '-',
                        className: 'text-center'
                    },
                    // {
                    //     data: 'nama_cabang',
                    //     name: 'mst_cust_dist.nama_cabang',
                    //     defaultContent: '-'
                    // },
                    // {
                    //     data: 'kota',
                    //     name: 'mst_cust_dist.kota',
                    //     defaultContent: '-'
                    // },
                    {
                        data: 'segment',
                        name: 'mst_cust_dist.segment',
                        defaultContent: '-'
                    },
                    {
                        data: 'pilar',
                        name: 'crm_mst_mappings.cov_pilar',
                        defaultContent: '-',
                        className: 'text-center'
                    },
                    {
                        data: 'id_coverage',
                        name: 'crm_mst_outlets.id_coverage',
                        className: 'text-center'
                    },
                    {
                        data: 'cov_nik',
                        name: 'crm_mst_mappings.cov_nik',
                        defaultContent: '-',
                        className: 'text-center'
                    },
                    {
                        data: 'user_name',
                        name: 'users.name',
                        defaultContent: '-'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        className: 'text-center'
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center text-nowrap',
                        width: '100px'
                    },
                ]
            });
        });
    </script>
@endpush
