@extends('layouts.app', ['title' => 'Outlet'])

@section('content')
    <div class="row">
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL DATA" value="{{ $totalData }}" subtitle="Entitas" subtitleColor="text-primary"
                icon="far fa-folder-open" color="primary">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="DATA BARU" value="{{ $newData }}" subtitle="(7 Hari Terakhir)" subtitleColor="text-info"
                icon="far fa-file-alt" color="info">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="PERUBAHAN HARI INI" value="{{ $updatedToday }}" subtitle="Updates"
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
                autoWidth: false,
                ajax: '{{ route('outlet.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'dist',
                        name: 'v_cust_dist.dist',
                        defaultContent: '-'
                    },
                    {
                        data: 'id_outlet',
                        name: 'v_cust_dist.id_outlet'
                    },
                    {
                        data: 'nama_outlet',
                        name: 'v_cust_dist.nama_outlet',
                        defaultContent: '-'
                    },
                    {
                        data: 'kode_cabang',
                        name: 'v_cust_dist.id_cbg_dist',
                        defaultContent: '-',
                        className: 'text-center'
                    },
                    // {
                    //     data: 'nama_cabang',
                    //     name: 'v_cust_dist.nama_cabang',
                    //     defaultContent: '-'
                    // },
                    // {
                    //     data: 'kota',
                    //     name: 'v_cust_dist.kota',
                    //     defaultContent: '-'
                    // },
                    {
                        data: 'segment',
                        name: 'v_cust_dist.segment',
                        defaultContent: '-'
                    },
                    {
                        data: 'pilar',
                        name: 'mst_mappings.cov_pilar',
                        defaultContent: '-',
                        className: 'text-center'
                    },
                    {
                        data: 'id_coverage',
                        name: 'mst_outlets.id_coverage',
                        className: 'text-center'
                    },
                    {
                        data: 'cov_nik',
                        name: 'mst_mappings.cov_nik',
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
                        name: 'mst_outlets.needs_replacement',
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
