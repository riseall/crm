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
        create-url="javascript:;" create-label="Tambah User" :show-create="true">
        <th>No</th>
        <th>NIK</th>
        <th>Name</th>
        <th>Email</th>
        <th>Jabatan</th>
        <th>Role</th>
        <th>Status</th>
        <th>Aksi</th>
    </x-datatable-card>

    <!-- Modal User -->
    <form id="form_user">
        @csrf
        <input type="hidden" name="_method" id="form_method" value="POST">
        <x-modal id="modal_user" title="Tambah User" size="modal-lg" submitBtnId="btnSimpan" submitBtnText="Simpan">
            <div class="form-group row">
                <div class="col-lg-6">
                    <label>NIK / Anggota <span class="text-danger">*</span></label>
                    <select class="form-control" name="nik" id="nik" style="width: 100%" required></select>
                </div>
                <div class="col-lg-6">
                    <label>Nama <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" name="name" id="name" required readonly />
                </div>
            </div>
            <div class="form-group row">
                <div class="col-lg-6">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control bg-light" name="email" id="email" required readonly />
                </div>
                <div class="col-lg-6">
                    <label>Jabatan</label>
                    <input type="text" class="form-control bg-light" name="jabatan" id="jabatan" readonly />
                </div>
            </div>
            <div class="form-group row">
                <div class="col-lg-6">
                    <label>Role <span class="text-danger">*</span></label>
                    <select class="form-control selectpicker" name="role" id="role" required>
                        <option value="">Pilih Role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-6">
                    <label>Status</label>
                    <div class="checkbox-inline mt-2">
                        <label class="checkbox checkbox-success">
                            <input type="checkbox" name="is_active" id="is_active" value="1" checked />
                            <span></span> Active
                        </label>
                    </div>
                </div>
            </div>
        </x-modal>
    </form>
@endsection

@push('scripts')
    <script>
        window.UserPageConfig = {
            routes: {
                data: '{{ route('users.data') }}',
                store: '{{ route('users.store') }}',
                update: '{{ route('users.update', ':id') }}',
                destroy: '{{ route('users.destroy', ':id') }}',
                anggota: '{{ route('mapping.anggota') }}'
            }
        };
    </script>
    <script src="{{ asset('js/user/index.js') }}?v={{ filemtime(public_path('js/user/index.js')) }}"></script>
@endpush
