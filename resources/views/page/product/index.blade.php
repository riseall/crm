@extends('layouts.app', ['title' => 'Product'])

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

    <x-datatable-card title="Master PM (Produk ↔ Coverage)" subtitle="Daftar Produk dan Coverage" table-id="tblPm"
        table-class="table table-bordered table-hover">
        <x-slot name="toolbar">
            @can('pm-create')
                <button type="button" class="btn btn-primary" id="btnTambah">
                    <i class="fas fa-plus"></i> Tambah PM
                </button>
            @endcan
        </x-slot>

        <th>#</th>
        <th>Kode Produk (pt_ph)</th>
        <th>ID Coverage</th>
        <th>Deskripsi Coverage</th>
        <th>NIK</th>
        <th>Pilar</th>
        <th>Aksi</th>
    </x-datatable-card>

    {{-- Modal --}}
    <div class="modal fade" id="modalPm" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah PM</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pmId">
                    <div class="form-group">
                        <label>Produk (pt_ph) <span class="text-danger">*</span></label>
                        <select id="pt_ph" class="form-control select2-produk" style="width:100%"></select>
                    </div>
                    <div class="form-group">
                        <label>Coverage <span class="text-danger">*</span></label>
                        <select id="id_coverage_pm" class="form-control" style="width:100%">
                            <option value="">-- Pilih Coverage --</option>
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
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {

            var table = $('#tblPm').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('product.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'pt_ph'
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
                        data: 'action',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

            // Select2 produk AJAX
            $('.select2-produk').select2({
                dropdownParent: $('#modalPm'),
                ajax: {
                    url: '{{ route('product.produk') }}',
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
                placeholder: 'Cari kode atau nama produk...',
                minimumInputLength: 2,
            });

            $('#btnTambah').on('click', function() {
                $('#pmId').val('');
                $('#modalTitle').text('Tambah PM');
                $('.select2-produk').val(null).trigger('change');
                $('#id_coverage_pm').val('');
                $('#modalPm').modal('show');
            });

            $(document).on('click', '.btn-edit', function() {
                var id = $(this).data('id');
                $.get('{{ url('product') }}/' + id, function(data) {
                    $('#pmId').val(data.id_pm);
                    $('#modalTitle').text('Edit PM');
                    var option = new Option(data.pt_ph, data.pt_ph, true, true);
                    $('.select2-produk').append(option).trigger('change');
                    $('#id_coverage_pm').val(data.id_coverage);
                    $('#modalPm').modal('show');
                });
            });

            $('#btnSimpan').on('click', function() {
                var id = $('#pmId').val();
                var url = id ? '{{ url('product') }}/' + id : '{{ route('product.store') }}';
                var method = id ? 'PUT' : 'POST';

                $.ajax({
                    url: url,
                    type: method,
                    data: {
                        _token: '{{ csrf_token() }}',
                        pt_ph: $('#pt_ph').val(),
                        id_coverage: $('#id_coverage_pm').val(),
                    },
                    success: function(res) {
                        $('#modalPm').modal('hide');
                        Swal.fire('Berhasil', res.message, 'success');
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        Swal.fire('Gagal', xhr.responseJSON.message, 'error');
                    }
                });
            });

            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'Hapus PM?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ url('product') }}/' + id,
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
        });
    </script>
@endpush
