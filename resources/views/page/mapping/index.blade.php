@extends('layouts.app', ['title' => 'Mapping Coverage'])

@section('content')
    <div class="row">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL DATA" value="{{ $totalData }}" subtitle="Entitas" subtitleColor="text-primary"
                icon="far fa-folder-open" color="primary">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="DATA BARU" value="{{ $newData }}" subtitle="(7 Hari Terakhir)" subtitleColor="text-info"
                icon="far fa-file-alt" color="info">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="STATUS AKTIF" value="{{ $activeData }}" subtitle="Verified" subtitleColor="text-success"
                icon="far fa-check-circle" color="success">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="PERUBAHAN HARI INI" value="{{ $updatedToday }}" subtitle="Updates"
                subtitleColor="text-warning" icon="fas fa-history" color="warning">
            </x-stat-card>
        </div>
    </div>

    <x-datatable-card title="Master Coverage" subtitle="Daftar coverage aktif" table-id="table_coverage" showCreate
        createLabel="Tambah Mapping Coverage">

        <th>No</th>
        <th>ID Coverage</th>
        <th>Deskripsi</th>
        <th>Divisi</th>
        <th>Ket. Cabang</th>
        <th>Pilar</th>
        <th>Tgg. Jwb Pilar</th>
        <th>NIK</th>
        <th>Nama</th>
        <th>Level</th>
        <th>Valid From</th>
        <th>Valid To</th>
        <th>Status</th>
        <th>Aksi</th>
    </x-datatable-card>

    {{-- Modal Form --}}
    <x-modal id="modalCoverage" title="Tambah Coverage" size="modal-lg" submitBtnId="btnSimpanCoverage">
        @include('page.mapping.form')
    </x-modal>
@endsection

@push('scripts')
    <script>
        window.MappingPageConfig = {
            csrfToken: '{{ csrf_token() }}',
            routes: {
                data: '{{ route('mapping.data') }}',
                anggota: '{{ route('mapping.anggota') }}',
                show: '{{ route('mapping.show', ':id') }}',
                toggle: '{{ route('mapping.toggle', ':id') }}',
                store: '{{ route('mapping.store') }}',
                update: '{{ route('mapping.update', ':id') }}',
                destroy: '{{ route('mapping.destroy', ':id') }}',
                lookupCoverage: '{{ route('mapping.lookup.coverage') }}',
            }
        };
    </script>
    <script src="{{ asset('js/mapping/index.js') }}?v={{ filemtime(public_path('js/mapping/index.js')) }}"></script>
@endpush
