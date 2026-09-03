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
        window.ProductPageConfig = {
            csrfToken: '{{ csrf_token() }}',
            routes: {
                data: '{{ route('product.data') }}',
                dataDist: '{{ route('product.dataDist') }}',
                produk: '{{ route('product.produk') }}',
                show: '{{ route('product.show', ':id') }}',
                store: '{{ route('product.store') }}',
                update: '{{ route('product.update', ':id') }}',
                destroy: '{{ route('product.destroy', ':id') }}',
            }
        };
    </script>
    <script src="{{ asset('js/product/index.js') }}?v={{ filemtime(public_path('js/product/index.js')) }}"></script>
@endpush

