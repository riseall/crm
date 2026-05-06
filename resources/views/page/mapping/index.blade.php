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

    <x-datatable-card title="Master Coverage" subtitle="Daftar coverage aktif" table-id="table_coverage"
        table-class="table table-bordered table-hover">
        <th>No</th>
        <th>ID Coverage</th>
        <th>Deskripsi</th>
        <th>NIK</th>
        <th>Pilar</th>
        <th>Level</th>
        <th>Tgg. Jwb Pilar</th>
        <th>Valid From</th>
        <th>Valid To</th>
        <th>Status</th>
        <th>Aksi</th>
    </x-datatable-card>

    {{-- Modal Form --}}
    <div class="modal fade" id="modalCoverage" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Coverage</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="coverageId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ID Coverage <span class="text-danger">*</span></label>
                                <input type="text" id="id_coverage" class="form-control" placeholder="Contoh: 1.2.3.4.5">
                                <small class="text-muted" id="idCoverageHelp"></small>
                            </div>
                            <div class="form-group">
                                <label>NIK / Anggota <span class="text-danger">*</span></label>
                                <select id="cov_nik" class="form-control select2-anggota" style="width:100%"></select>
                            </div>
                            <div class="form-group">
                                <label>Pilar <span class="text-danger">*</span></label>
                                <select id="cov_pilar" class="form-control">
                                    <option value="">-- Pilih Pilar --</option>
                                    <option value="ETHICAL">ETHICAL</option>
                                    <option value="OGB">OGB</option>
                                    <option value="OTC">OTC</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Tanggung Jawab Pilar <span class="text-danger">*</span></label>
                                <select id="tanggung_jwb_pilar" class="form-control">
                                    <option value="Single">Single</option>
                                    <option value="Combo">Combo (Ethical + OGB)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Level <span class="text-danger">*</span></label>
                                <select id="cov_level" class="form-control">
                                    <option value="">-- Pilih Level --</option>
                                    <option value="MR">MR - Medical Representative</option>
                                    <option value="AM">AM - Area Manager</option>
                                    <option value="SAM">SAM - Senior Area Manager</option>
                                    <option value="RM">RM - Regional Manager</option>
                                    <option value="GM">GM - General Manager</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <input type="text" id="cov_desc" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Divisi</label>
                                <input type="text" id="cov_divisi" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Ket. CBG</label>
                                <input type="text" id="cov_ket_cbg" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Valid From <span class="text-danger">*</span></label>
                                <input type="date" id="valid_from" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Valid To</label>
                                <input type="date" id="valid_to" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSimpan">Simpan</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var table = $('#table_coverage').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('mapping.data') }}",
                    type: 'GET'
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'id_coverage'
                    },
                    {
                        data: 'cov_desc'
                    },
                    {
                        data: 'cov_nik'
                    },
                    {
                        data: 'cov_pilar'
                    },
                    {
                        data: 'cov_level'
                    },
                    {
                        data: 'tanggung_jwb_pilar'
                    },
                    {
                        data: 'valid_from'
                    },
                    {
                        data: 'valid_to',
                        defaultContent: '-'
                    },
                    {
                        data: 'status',
                        orderable: false
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

            // Select2 untuk dropdown NIK anggota (AJAX)
            $('.select2-anggota').select2({
                dropdownParent: $('#modalCoverage'),
                ajax: {
                    url: '{{ route('mapping.anggota') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.results
                        };
                    },
                },
                placeholder: 'Cari NIK atau Nama...',
                minimumInputLength: 2,
            });

            // Auto-disable Combo jika pilar OTC
            $('#cov_pilar').on('change', function() {
                if ($(this).val() === 'OTC') {
                    $('#tanggung_jwb_pilar').val('Single').prop('disabled', true);
                } else {
                    $('#tanggung_jwb_pilar').prop('disabled', false);
                }
            });

            // Tambah
            $('#btnTambah').on('click', function() {
                resetForm();
                $('#modalTitle').text('Tambah Coverage');
                $('#id_coverage').prop('readonly', false);
                $('#modalCoverage').modal('show');
            });

            // Edit
            $(document).on('click', '.btn-edit', function() {
                var id = $(this).data('id');
                $.get('{{ url('mapping') }}/' + id, function(data) {
                    resetForm();
                    $('#modalTitle').text('Edit Coverage');
                    $('#coverageId').val(data.id_coverage);
                    $('#id_coverage').val(data.id_coverage).prop('readonly', true);
                    $('#cov_desc').val(data.cov_desc);
                    $('#cov_divisi').val(data.cov_divisi);
                    $('#cov_ket_cbg').val(data.cov_ket_cbg);
                    $('#cov_pilar').val(data.cov_pilar).trigger('change');
                    $('#cov_level').val(data.cov_level);
                    $('#tanggung_jwb_pilar').val(data.tanggung_jwb_pilar);
                    $('#valid_from').val(data.valid_from);
                    $('#valid_to').val(data.valid_to);

                    // Set select2 NIK
                    var option = new Option(data.cov_nik, data.cov_nik, true, true);
                    $('.select2-anggota').append(option).trigger('change');

                    $('#modalCoverage').modal('show');
                });
            });

            // Toggle aktif/nonaktif
            $(document).on('click', '.btn-toggle', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'Ubah Status Coverage?',
                    text: 'Jika dinonaktifkan, outlet terkait akan di-flag butuh pengganti.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, ubah!',
                    cancelButtonText: 'Batal',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ url('mapping') }}/' + id + '/toggle',
                            type: 'PATCH',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(res) {
                                Swal.fire('Berhasil', res.message, 'success');
                                table.ajax.reload();
                            },
                            error: function(xhr) {
                                Swal.fire('Gagal', xhr.responseJSON.message, 'error');
                            }
                        });
                    }
                });
            });

            // Simpan (Store/Update)
            $('#btnSimpan').on('click', function() {
                var id = $('#coverageId').val();
                var url = id ? '{{ url('mapping') }}/' + id :
                    '{{ route('mapping.store') }}';
                var method = id ? 'PUT' : 'POST';

                $.ajax({
                    url: url,
                    type: method,
                    data: {
                        _token: '{{ csrf_token() }}',
                        id_coverage: $('#id_coverage').val(),
                        cov_desc: $('#cov_desc').val(),
                        cov_divisi: $('#cov_divisi').val(),
                        cov_ket_cbg: $('#cov_ket_cbg').val(),
                        cov_pilar: $('#cov_pilar').val(),
                        cov_level: $('#cov_level').val(),
                        cov_nik: $('#cov_nik').val(),
                        tanggung_jwb_pilar: $('#tanggung_jwb_pilar').val(),
                        valid_from: $('#valid_from').val(),
                        valid_to: $('#valid_to').val(),
                    },
                    success: function(res) {
                        $('#modalCoverage').modal('hide');
                        Swal.fire('Berhasil', res.message, 'success');
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON.message || xhr.responseJSON.errors;
                        Swal.fire('Gagal', typeof msg === 'object' ? Object.values(msg).join(
                            '\n') : msg, 'error');
                    }
                });
            });

            // Hapus
            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'Hapus Coverage?',
                    text: 'Data tidak bisa dikembalikan!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ url('mapping') }}/' + id,
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(res) {
                                Swal.fire('Berhasil', res.message, 'success');
                                table.ajax.reload();
                            },
                            error: function(xhr) {
                                Swal.fire('Gagal', xhr.responseJSON.message, 'error');
                            }
                        });
                    }
                });
            });

            function resetForm() {
                $('#coverageId').val('');
                $('#id_coverage').val('');
                $('#cov_desc').val('');
                $('#cov_divisi').val('');
                $('#cov_ket_cbg').val('');
                $('#cov_pilar').val('').trigger('change');
                $('#cov_level').val('');
                $('#tanggung_jwb_pilar').val('Single').prop('disabled', false);
                $('#valid_from').val('');
                $('#valid_to').val('');
                $('.select2-anggota').val(null).trigger('change');
            }
        });
    </script>
@endpush
