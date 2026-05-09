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
        $(document).ready(function() {
            var table = $('#table_pengguna').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: "{{ route('users.data') }}",
                    type: 'GET'
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'nik',
                    },
                    {
                        data: 'name',
                    },
                    {
                        data: 'email',
                    },
                    {
                        data: 'jabatan',
                    },
                    {
                        data: 'role',
                        className: 'text-center',
                        orderable: false
                    },
                    {
                        data: 'is_active',
                        className: 'text-center'
                    },
                    // {
                    //     data: 'action',
                    //     name: 'action',
                    //     className: 'text-center',
                    //     orderable: false,
                    //     searchable: false
                    // }
                ],
            });

            // Opsional: Jika kamu ingin menghubungkan tombol Export Metronic dengan DataTables
            // (Membutuhkan ekstensi Buttons dari DataTables)
            $('#export_print').on('click', function(e) {
                e.preventDefault();
                table.button(0).trigger();
            });
            $('#export_copy').on('click', function(e) {
                e.preventDefault();
                table.button(1).trigger();
            });
            $('#export_excel').on('click', function(e) {
                e.preventDefault();
                table.button(2).trigger();
            });
            $('#export_csv').on('click', function(e) {
                e.preventDefault();
                table.button(3).trigger();
            });
            $('#export_pdf').on('click', function(e) {
                e.preventDefault();
                table.button(4).trigger();
            });
        });
    </script>
@endpush
