@extends('layouts.app', ['title' => 'Product'])

@section('content')
    <div class="row">

        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL PRODUK PHAPROS" value="{{ $totalData }}" subtitle="Entitas"
                subtitleColor="text-primary" icon="far fa-folder-open" color="primary">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL PRODUK DISTRIBUTOR" value="{{ $totalProdDist }}" subtitle="Entitas"
                subtitleColor="text-success" icon="far fa-folder-open" color="success">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="DATA COVERAGE BARU" value="{{ $newData }}" subtitle="(7 Hari Terakhir)"
                subtitleColor="text-info" icon="far fa-file-alt" color="info">
            </x-stat-card>
        </div>
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="PERUBAHAN HARI INI" value="{{ $updatedToday }}" subtitle="Updates"
                subtitleColor="text-warning" icon="fas fa-history" color="warning">
            </x-stat-card>
        </div>
    </div>

    <x-datatable-card title="Produk PM" subtitle="Daftar Produk Phapros dan Coverage PM" table-id="tblPm" showCreate
        createLabel="Tambah Mapping Produk">
        <th>No</th>
        <th>Kode Phapros</th>
        <th>Nama Produk</th>
        <th>Id Coverage</th>
        <th>Action</th>
    </x-datatable-card>

    <x-datatable-card title="List Produk Distributor" subtitle="Daftar Produk Distributor dan Coverage PM"
        table-id="tblPmDist">
        <th>No</th>
        <th>Kode Produk Dist</th>
        <th>Kode Produk Phapros</th>
        <th>Nama Produk</th>
        <th>Dist</th>
        <th>Pilar</th>
        {{-- <th>Id Coverage</th> --}}
        <th>NIK PM</th>
        <th>Nama PM</th>
    </x-datatable-card>

    {{-- Modal --}}
    @include('page.product.modal')
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {

            window.tablePm = $('#tblPm').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('product.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'c_ph',
                        name: 'crm_mst_products.pt_ph'
                    },
                    {
                        data: 'p_desc',
                        name: 'parts.pt_desc'
                    },
                    {
                        data: 'id_coverage',
                        name: 'crm_mst_products.id_coverage',
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

            window.tablePmDist = $('#tblPmDist').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('product.dataDist') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'c_dist',
                        name: 'v_pt_mstr_dist.pt_dist'
                    },
                    {
                        data: 'c_ph',
                        name: 'v_pt_mstr_dist.pt_ph'
                    },
                    {
                        data: 'p_desc',
                        name: 'v_pt_mstr_dist.pt_desc'
                    },
                    {
                        data: 'dist',
                        name: 'v_pt_mstr_dist.dist',
                        className: 'text-center'
                    },
                    {
                        data: 'pilar',
                        name: 'v_pt_mstr_dist.pilar_sub',
                        className: 'text-center'
                    },
                    // {
                    //     data: 'cov',
                    //     className: 'text-center'
                    // },
                    {
                        data: 'cov_pm',
                        name: 'crm_mst_mappings.cov_nik',
                        className: 'text-center'
                    },
                    {
                        data: 'nama_pm',
                        name: 'users.name'
                    },
                ]
            });

        });
    </script>
@endpush
