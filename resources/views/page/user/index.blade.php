@extends('layouts.app', ['title' => 'Users'])

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

    <x-datatable-card title="Data Pengguna" subtitle="List semua pengguna terdaftar" table-id="table_pengguna"
        create-url="#" create-label="Tambah User">
        <th>No</th>
        <th>NIK</th>
        <th>Name</th>
        <th>Email</th>
        <th>Jabatan</th>
        <th>Role</th>
        <th>Status</th>
        {{-- <th>Aksi</th> --}}
    </x-datatable-card>
@endsection

@push('scripts')
    <script>
        window.UserPageConfig = {
            routes: {
                data: '{{ route('users.data') }}'
            }
        };
    </script>
    <script src="{{ asset('js/user/index.js') }}?v={{ filemtime(public_path('js/user/index.js')) }}"></script>
@endpush

