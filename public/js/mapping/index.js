$(document).ready(function() {
    'use strict';

    const config = window.MappingPageConfig || {
        csrfToken: '',
        routes: {}
    };

    var table = $('#table_coverage').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: config.routes.data || '/mapping/data',
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
            url: config.routes.anggota || '/mapping/anggota',
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

    $('.mapping-monthpicker').datepicker({
        format: 'yyyy-mm',
        minViewMode: 1,
        orientation: 'top left',
        todayHighlight: true,
        autoclose: true,
        clearBtn: true
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
        var showUrl = (config.routes.show || '/mapping/:id').replace(':id', id);

        $.get(showUrl, function(data) {
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
            $('#valid_from').val(dateToMonth(data.valid_from));
            $('#valid_to').val(dateToMonth(data.valid_to));

            // Set select2 NIK
            var anggotaText = data.user && data.user.name ? data.cov_nik + ' - ' + data.user.name : data.cov_nik;
            var option = new Option(anggotaText, data.cov_nik, true, true);
            $('.select2-anggota').append(option).trigger('change');

            $('#modalCoverage').modal('show');
        });
    });

    // Toggle aktif/nonaktif
    $(document).on('click', '.btn-toggle', function() {
        var id = $(this).data('id');
        var toggleUrl = (config.routes.toggle || '/mapping/toggle/:id').replace(':id', id);

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
                    url: toggleUrl,
                    type: 'PATCH',
                    data: {
                        _token: config.csrfToken
                    },
                    success: function(res) {
                        Swal.fire('Berhasil', res.message, 'success').then(function() {
                            window.location.reload();
                        });
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.message ?
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
        var url = id ? (config.routes.update || '/mapping/:id').replace(':id', id) : (config.routes.store || '/mapping');
        var method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: {
                _token: config.csrfToken,
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
        var url = $(this).data('url') || (config.routes.destroy || '/mapping/:id').replace(':id', id);

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
                        _token: config.csrfToken
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
                .text('Kode coverage bisa dipakai ulang. Sistem hanya menjaga 1 baris aktif untuk setiap ID.');
            return;
        }

        var lookupUrl = config.routes.lookupCoverage || '/mapping/lookup/coverage';
        $.get(lookupUrl, {
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
                .text('Data coverage lama ditemukan dan otomatis diisi. Silakan lengkapi PIC dan tanggal valid baru.');
        });
    }

    function fillCoverageMetadata(data) {
        $('#cov_desc').val(data.cov_desc || '');
        setSelectValue($('#cov_divisi'), data.cov_divisi);
        $('#cov_ket_cbg').val(data.cov_ket_cbg || '');
        setSelectValue($('#cov_pilar'), data.cov_pilar || '');

        if ($('#cov_pilar').val() === 'OTC') {
            setSelectValue($('#tanggung_jwb_pilar'), 'Single');
            $('#tanggung_jwb_pilar').prop('disabled', true).selectpicker('refresh');
        } else {
            setSelectValue($('#tanggung_jwb_pilar'), data.responsibility || data.tanggung_jwb_pilar || 'Single');
            $('#tanggung_jwb_pilar').prop('disabled', false).selectpicker('refresh');
        }
    }

    function fillPicMetadata(data) {
        $('#valid_to').val(dateToMonth(data.tanggal_keluar));

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

    function dateToMonth(value) {
        if (!value) {
            return '';
        }

        return String(value).substring(0, 7);
    }

    function clearFormErrors() {
        $('#formErrorAlert').addClass('d-none').text('');
        $('#modalCoverage .is-invalid').removeClass('is-invalid');
        $('#modalCoverage .invalid-feedback').removeClass('d-block').text('');
        $('#modalCoverage .bootstrap-select').removeClass('is-invalid');
        $('#modalCoverage .bootstrap-select .dropdown-toggle').removeClass('border border-danger');
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

                if ($input.hasClass('mapping-monthpicker')) {
                    $('#error_' + field).addClass('d-block');
                }

                if ($input.hasClass('selectpicker')) {
                    $input.closest('.bootstrap-select').addClass('is-invalid');
                    $input.closest('.bootstrap-select').find('.dropdown-toggle')
                        .addClass('border border-danger');
                    $('#error_' + field).addClass('d-block');
                }

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
