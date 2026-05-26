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
        $(document).ready(function() {
            var table = $('#table_coverage').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: "{{ route('mapping.data') }}",
                    type: 'GET'
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'id_coverage'
                    },
                    {
                        data: 'cov_desc'
                    },
                    {
                        data: 'cov_divisi'
                    },
                    {
                        data: 'cov_ket_cbg'
                    },
                    {
                        data: 'cov_pilar'
                    },
                    {
                        data: 'tanggung_jwb_pilar'
                    },
                    {
                        data: 'cov_nik'
                    },
                    {
                        data: 'user_name',
                    },
                    {
                        data: 'cov_level_desc'
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
                        className: 'text-center'
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center text-nowrap',
                        width: '125px'
                    },
                ],
                order: [
                    [1, 'asc']
                ],
            });

            var resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    table.columns.adjust();
                }, 150);
            });

            var lookupTimer;
            $('#id_coverage').on('input change', function() {
                clearTimeout(lookupTimer);

                if ($('#coverageId').val()) {
                    return;
                }

                lookupTimer = setTimeout(function() {
                    lookupCoverage();
                }, 400);
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

            $('.select2-anggota').on('select2:select', function(e) {
                var data = e.params && e.params.data ? e.params.data : {};
                fillPicMetadata(data);
            });

            // Auto-disable Combo jika pilar OTC
            $('#cov_pilar').on('change', function() {
                if ($(this).val() === 'OTC') {
                    setSelectValue($('#tanggung_jwb_pilar'), 'Single');
                    $('#tanggung_jwb_pilar').prop('disabled', true).selectpicker('refresh');
                } else {
                    $('#tanggung_jwb_pilar').prop('disabled', false).selectpicker('refresh');
                }
            });

            // Tambah
            $('#btnTambah').on('click', function() {
                resetForm();
                $('#modalCoverageLabel').text('Tambah Coverage');
                $('#id_coverage').prop('readonly', false);
                $('#modalCoverage').modal('show');
            });

            $('#modalCoverage').on('hidden.bs.modal', function() {
                resetForm();
            });

            // Edit
            $(document).on('click', '.btn-edit', function() {
                var id = $(this).data('id');
                $.get('{{ route('mapping.show', ':id') }}'.replace(':id', id), function(data) {
                    resetForm();
                    $('#modalCoverageLabel').text('Edit Coverage');
                    $('#coverageId').val(data.id);
                    $('#id_coverage').val(data.id_coverage).prop('readonly', true);
                    $('#cov_desc').val(data.cov_desc);
                    setSelectValue($('#cov_divisi'), data.cov_divisi);
                    $('#cov_ket_cbg').val(data.cov_ket_cbg);
                    setSelectValue($('#cov_pilar'), data.cov_pilar);
                    setSelectValue($('#cov_level'), getLevelDesc(data));
                    setSelectValue($('#tanggung_jwb_pilar'), data.tanggung_jwb_pilar);
                    $('#valid_from').val(data.valid_from);
                    $('#valid_to').val(data.valid_to);

                    // Set select2 NIK
                    var anggotaText = data.user && data.user.name ? data.cov_nik + ' - ' + data.user
                        .name :
                        data.cov_nik;
                    var option = new Option(anggotaText, data.cov_nik, true, true);
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
                    if (result.isConfirmed || result.value) {
                        $.ajax({
                            url: '{{ route('mapping.toggle', ':id') }}'.replace(':id', id),
                            type: 'PATCH',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(res) {
                                Swal.fire('Berhasil', res.message, 'success').then(
                                    function() {
                                        window.location.reload();
                                    });
                            },
                            error: function(xhr) {
                                var message = xhr.responseJSON && xhr.responseJSON
                                    .message ?
                                    xhr.responseJSON.message :
                                    'Status coverage gagal diubah.';
                                Swal.fire('Gagal', message, 'error');
                            }
                        });
                    }
                });
            });

            // Simpan (Store/Update)
            $('#btnSimpanCoverage').on('click', function() {
                clearFormErrors();

                var id = $('#coverageId').val();
                var url = id ? '{{ route('mapping.update', ':id') }}'.replace(':id', id) :
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
                        cov_level_desc: $('#cov_level').val(),
                        cov_nik: $('#cov_nik').val(),
                        tanggung_jwb_pilar: $('#tanggung_jwb_pilar').val(),
                        valid_from: $('#valid_from').val(),
                        valid_to: $('#valid_to').val(),
                    },
                    success: function(res) {
                        $('#modalCoverage').modal('hide');
                        Swal.fire('Berhasil', res.message, 'success').then(function() {
                            window.location.reload();
                        });
                    },
                    error: function(xhr) {
                        showFormErrors(xhr);
                    }
                });
            });

            // Hapus
            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                var url = $(this).data('url') || '{{ route('mapping.destroy', ':id') }}'.replace(':id',
                    id);

                Swal.fire({
                    title: 'Hapus Coverage?',
                    text: 'Data tidak bisa dikembalikan!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                }).then(function(result) {
                    if (result.isConfirmed || result.value) {
                        $.ajax({
                            url: url,
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
                clearFormErrors();
                $('#coverageId').val('');
                $('#id_coverage').val('');
                $('#cov_desc').val('');
                setSelectValue($('#cov_divisi'), '');
                $('#cov_ket_cbg').val('');
                setSelectValue($('#cov_pilar'), '');
                setSelectValue($('#cov_level'), '');
                setSelectValue($('#tanggung_jwb_pilar'), 'Single');
                $('#tanggung_jwb_pilar').prop('disabled', false).selectpicker('refresh');
                $('#valid_from').val('');
                $('#valid_to').val('');
                $('.select2-anggota').val(null).trigger('change');
                $('#idCoverageHelp')
                    .removeClass('text-success text-warning')
                    .addClass('text-muted')
                    .text('Kode coverage bisa dipakai ulang. Sistem hanya menjaga 1 baris aktif untuk setiap ID.');
            }

            function lookupCoverage() {
                var idCoverage = $.trim($('#id_coverage').val());

                if (!idCoverage) {
                    $('#idCoverageHelp')
                        .removeClass('text-success text-warning')
                        .addClass('text-muted')
                        .text(
                            'Kode coverage bisa dipakai ulang. Sistem hanya menjaga 1 baris aktif untuk setiap ID.'
                        );
                    return;
                }

                $.get('{{ route('mapping.lookup.coverage') }}', {
                    id_coverage: idCoverage
                }).done(function(res) {
                    if (!res.found || !res.coverage) {
                        $('#idCoverageHelp')
                            .removeClass('text-success')
                            .addClass('text-muted')
                            .text('Coverage belum pernah ada. Lengkapi data coverage baru.');
                        return;
                    }

                    fillCoverageMetadata(res.coverage);
                    $('#idCoverageHelp')
                        .removeClass('text-muted text-warning')
                        .addClass('text-success')
                        .text(
                            'Data coverage lama ditemukan dan otomatis diisi. Silakan lengkapi PIC dan tanggal valid baru.'
                        );
                });
            }

            function fillCoverageMetadata(data) {
                $('#cov_desc').val(data.cov_desc || '');
                setSelectValue($('#cov_divisi'), data.cov_divisi);
                $('#cov_ket_cbg').val(data.cov_ket_cbg || '');
                setSelectValue($('#cov_pilar'), data.cov_pilar || '');
                // setSelectValue($('#cov_level'), data.level_desc || getLevelDesc(data));

                if ($('#cov_pilar').val() === 'OTC') {
                    setSelectValue($('#tanggung_jwb_pilar'), 'Single');
                    $('#tanggung_jwb_pilar').prop('disabled', true).selectpicker('refresh');
                } else {
                    setSelectValue($('#tanggung_jwb_pilar'), data.responsibility || data.tanggung_jwb_pilar ||
                        'Single');
                    $('#tanggung_jwb_pilar').prop('disabled', false).selectpicker('refresh');
                }
            }

            function fillPicMetadata(data) {
                // if (data.level_desc) {
                //     setSelectValue($('#cov_level'), data.level_desc);
                // }

                if (data.tanggal_masuk && !$('#valid_from').val()) {
                    $('#valid_from').val(data.tanggal_masuk);
                }

                $('#valid_to').val(data.tanggal_keluar || '');

                if (data.is_active === 0) {
                    $('#formErrorAlert')
                        .removeClass('d-none')
                        .text('PIC yang dipilih berstatus nonaktif di HRMS.');
                } else {
                    $('#formErrorAlert').addClass('d-none').text('');
                }
            }

            function setSelectValue($select, value) {
                if (!value) {
                    $select.val('').trigger('change');
                    if ($select.hasClass('selectpicker')) {
                        $select.selectpicker('refresh');
                    }
                    return;
                }

                if (!$select.find('option').filter(function() {
                        return this.value === value;
                    }).length) {
                    $select.append(new Option(value, value));
                }

                $select.val(value).trigger('change');

                if ($select.hasClass('selectpicker')) {
                    $select.selectpicker('refresh');
                }
            }

            function getLevelDesc(data) {
                if (data.cov_level_desc) {
                    return data.cov_level_desc;
                }

                var levelMap = {
                    1: 'GM',
                    2: 'RM',
                    3: 'SAM',
                    4: 'AM',
                    5: 'MR/SR',
                };

                return levelMap[data.cov_level] || '';
            }

            function clearFormErrors() {
                $('#formErrorAlert').addClass('d-none').text('');
                $('#modalCoverage .is-invalid').removeClass('is-invalid');
                $('#modalCoverage .invalid-feedback').text('');
                $('.select2-anggota').next('.select2-container').find('.select2-selection')
                    .removeClass('border border-danger');
            }

            function showFormErrors(xhr) {
                var response = xhr.responseJSON || {};
                var errors = response.errors || {};
                var hasFieldErrors = Object.keys(errors).length > 0;

                if (hasFieldErrors) {
                    $.each(errors, function(field, messages) {
                        var message = Array.isArray(messages) ? messages.join('<br>') : messages;
                        var inputId = field === 'cov_level_desc' ? 'cov_level' : field;
                        var $input = $('#' + inputId);

                        $input.addClass('is-invalid');
                        $('#error_' + field).html(message);

                        if ($input.hasClass('select2-anggota')) {
                            $input.next('.select2-container').find('.select2-selection')
                                .addClass('border border-danger');
                        }
                    });
                }

                if (response.message && !hasFieldErrors && xhr.status === 422) {
                    $('#cov_nik').addClass('is-invalid');
                    $('#error_cov_nik').text(response.message);
                    $('#cov_nik').next('.select2-container').find('.select2-selection')
                        .addClass('border border-danger');
                    return;
                }

                if (response.message && !hasFieldErrors) {
                    $('#formErrorAlert').removeClass('d-none').text(response.message);
                    return;
                }

                if (!hasFieldErrors) {
                    $('#formErrorAlert').removeClass('d-none')
                        .text('Terjadi kesalahan saat menyimpan data.');
                }
            }
        });
    </script>
@endpush
