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
        {{-- <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="STATUS AKTIF" value="{{ $activeData }}" subtitle="Verified" subtitleColor="text-success"
                icon="far fa-check-circle" color="success">
            </x-stat-card>
        </div> --}}
        <div class="col-xl col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="PERUBAHAN HARI INI" value="{{ $updatedToday }}" subtitle="Updates"
                subtitleColor="text-warning" icon="fas fa-history" color="warning">
            </x-stat-card>
        </div>
    </div>

    <x-datatable-card title="Master Mapping Outlet ↔ Coverage" subtitle="Daftar mapping outlet ke coverage"
        table-id="tblMapping" table-class="table table-bordered table-hover">
        <x-slot name="toolbar">
            @can('mapping-create')
                <button type="button" class="btn btn-warning mr-2" id="btnBulkUpdate">
                    <i class="fas fa-exchange-alt"></i> Bulk Update (Mutasi)
                </button>
                <button type="button" class="btn btn-primary" id="btnTambah">
                    <i class="fas fa-plus"></i> Tambah Mapping
                </button>
            @endcan
        </x-slot>

        <th>No</th>
        <th>ID Outlet</th>
        <th>ID Coverage</th>
        <th>Deskripsi Coverage</th>
        <th>NIK</th>
        <th>Pilar</th>
        <th>Level</th>
        <th>Status</th>
        <th>Aksi</th>
    </x-datatable-card>

    {{-- Modal Tambah/Edit --}}
    <div class="modal fade" id="modalMapping" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Mapping</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="mappingId">
                    <div class="form-group">
                        <label>Outlet <span class="text-danger">*</span></label>
                        <select id="id_outlet" class="form-control select2-outlet" style="width:100%"></select>
                    </div>
                    <div class="form-group">
                        <label>Coverage <span class="text-danger">*</span></label>
                        <select id="id_coverage" class="form-control select2-coverage" style="width:100%">
                            @foreach (\App\Models\Mapping::active()->get() as $cov)
                                <option value="{{ $cov->id_coverage }}">
                                    {{ $cov->id_coverage }} - {{ $cov->cov_desc }} ({{ $cov->cov_pilar }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSimpan">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Bulk Update (Mutasi) --}}
    <div class="modal fade" id="modalBulkUpdate" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Update Coverage (Mutasi)</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Coverage Lama <span class="text-danger">*</span></label>
                        <select id="old_coverage_id" class="form-control" style="width:100%">
                            <option value="">-- Pilih Coverage Lama --</option>
                            @foreach (\App\Models\Mapping::all() as $cov)
                                <option value="{{ $cov->id_coverage }}">
                                    {{ $cov->id_coverage }} - {{ $cov->cov_desc }} ({{ $cov->cov_nik }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pilih Outlet yang Dipindah <span class="text-danger">*</span></label>
                        <div id="outletList" class="border p-3 rounded">
                            <small class="text-muted">Pilih coverage lama terlebih dahulu.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Coverage Baru <span class="text-danger">*</span></label>
                        <select id="new_coverage_id" class="form-control" style="width:100%">
                            <option value="">-- Pilih Coverage Baru --</option>
                            @foreach (\App\Models\Mapping::active()->get() as $cov)
                                <option value="{{ $cov->id_coverage }}">
                                    {{ $cov->id_coverage }} - {{ $cov->cov_desc }} ({{ $cov->cov_nik }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-warning" id="btnBulkSimpan">Pindahkan</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {

            var table = $('#tblMapping').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('outlet.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'id_outlet'
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
                        data: 'needs_replacement_badge',
                        orderable: false
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

            // Select2 outlet AJAX
            $('.select2-outlet').select2({
                dropdownParent: $('#modalMapping'),
                ajax: {
                    url: '{{ route('outlet.outlet') }}',
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
                placeholder: 'Cari ID Outlet atau Nama...',
                minimumInputLength: 2,
            });

            // Tambah
            $('#btnTambah').on('click', function() {
                $('#mappingId').val('');
                $('#modalTitle').text('Tambah Mapping');
                $('.select2-outlet').val(null).trigger('change');
                $('#id_coverage').val('');
                $('#modalMapping').modal('show');
            });

            // Edit
            $(document).on('click', '.btn-edit', function() {
                var id = $(this).data('id');
                $.get('{{ url('outlet') }}/' + id, function(data) {
                    $('#mappingId').val(data.id_mapping);
                    $('#modalTitle').text('Edit Mapping');
                    var option = new Option(data.id_outlet, data.id_outlet, true, true);
                    $('.select2-outlet').append(option).trigger('change');
                    $('#id_coverage').val(data.id_coverage);
                    $('#modalMapping').modal('show');
                });
            });

            // Simpan
            $('#btnSimpan').on('click', function() {
                var id = $('#mappingId').val();
                var url = id ? '{{ url('outlet') }}/' + id :
                    '{{ route('outlet.store') }}';
                var method = id ? 'PUT' : 'POST';

                $.ajax({
                    url: url,
                    type: method,
                    data: {
                        _token: '{{ csrf_token() }}',
                        id_outlet: $('#id_outlet').val(),
                        id_coverage: $('#id_coverage').val(),
                    },
                    success: function(res) {
                        $('#modalMapping').modal('hide');
                        Swal.fire('Berhasil', res.message, 'success');
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        Swal.fire('Gagal', xhr.responseJSON.message, 'error');
                    }
                });
            });

            // Hapus
            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'Hapus Mapping?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ url('outlet') }}/' + id,
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

            // Bulk Update: load outlet saat pilih coverage lama
            $('#old_coverage_id').on('change', function() {
                var covId = $(this).val();
                if (!covId) {
                    $('#outletList').html(
                        '<small class="text-muted">Pilih coverage lama terlebih dahulu.</small>');
                    return;
                }

                $.get('{{ url('outlet/outlet-by-coverage') }}/' + covId, function(data) {
                    if (data.length === 0) {
                        $('#outletList').html(
                            '<small class="text-muted">Tidak ada outlet untuk coverage ini.</small>'
                        );
                        return;
                    }
                    var html =
                        '<div class="mb-2"><label><input type="checkbox" id="checkAll"> <strong>Pilih Semua</strong></label></div>';
                    $.each(data, function(i, item) {
                        var badge = item.needs_replacement ?
                            ' <span class="badge badge-warning">Butuh Pengganti</span>' :
                            '';
                        html +=
                            '<div><label><input type="checkbox" class="chk-outlet" value="' +
                            item.id_outlet + '"> ' + item.id_outlet + badge +
                            '</label></div>';
                    });
                    $('#outletList').html(html);

                    $('#checkAll').on('change', function() {
                        $('.chk-outlet').prop('checked', $(this).is(':checked'));
                    });
                });
            });

            $('#btnBulkUpdate').on('click', function() {
                $('#old_coverage_id').val('').trigger('change');
                $('#new_coverage_id').val('');
                $('#modalBulkUpdate').modal('show');
            });

            $('#btnBulkSimpan').on('click', function() {
                var outletIds = $('.chk-outlet:checked').map(function() {
                    return $(this).val();
                }).get();
                if (outletIds.length === 0) {
                    Swal.fire('Perhatian', 'Pilih minimal 1 outlet.', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'Pindahkan ' + outletIds.length + ' outlet?',
                    text: 'Outlet akan dipindahkan ke coverage baru.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, pindahkan!',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('outlet.bulk.update') }}',
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                old_coverage_id: $('#old_coverage_id').val(),
                                new_coverage_id: $('#new_coverage_id').val(),
                                outlet_ids: outletIds,
                            },
                            success: function(res) {
                                $('#modalBulkUpdate').modal('hide');
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
        });
    </script>
@endpush
