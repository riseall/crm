$(document).ready(function() {
    'use strict';

    const config = window.TopMarketingPageConfig || {
        routes: {}
    };

    $('.top-marketing-monthpicker').datepicker({
        format: 'yyyy-mm',
        minViewMode: 1,
        orientation: 'bottom left',
        todayHighlight: true,
        autoclose: true
    }).on('changeDate', function() {
        $(this).trigger('change');
    });

    function splitPeriod(value) {
        var parts = (value || '').split('-');
        return {
            tahun: parts[0] || new Date().getFullYear(),
            bulan: parts[1] || (new Date().getMonth() + 1)
        };
    }

    function syncUploadPeriod() {
        var period = splitPeriod($('#upload_periode').val());
        $('#upload_tahun').val(period.tahun);
        $('#upload_bulan').val(parseInt(period.bulan, 10));
    }

    var table = $('#table_top_marketing').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: config.routes.data || '/top-marketing/data',
            data: function(d) {
                var period = splitPeriod($('#filter_periode').val());
                d.tahun = period.tahun;
                d.bulan = parseInt(period.bulan, 10);
            }
        },
        columns: [{
                data: 'DT_RowIndex',
                orderable: false,
                searchable: false,
                className: 'text-center'
            },
            {
                data: 'periode',
                name: 'tahun',
                className: 'text-center'
            },
            {
                data: 'divisi',
                name: 'divisi'
            },
            {
                data: 'region',
                name: 'region'
            },
            {
                data: 'kode_ph',
                name: 'kode_ph'
            },
            {
                data: 'nama_prod',
                name: 'nama_prod'
            },
            {
                data: 'pilar',
                name: 'pilar',
                className: 'text-center'
            },
            {
                data: 'pm',
                name: 'pm',
                defaultContent: '-'
            },
            {
                data: 'mr',
                name: 'mr',
                defaultContent: '-'
            },
            {
                data: 'am',
                name: 'am',
                defaultContent: '-'
            },
            {
                data: 'sam',
                name: 'sam',
                defaultContent: '-'
            },
            {
                data: 'rm',
                name: 'rm',
                defaultContent: '-'
            },
            {
                data: 'gm',
                name: 'gm',
                defaultContent: '-'
            },
            {
                data: 'segment',
                name: 'segment',
                defaultContent: '-'
            },
            {
                data: 'cbg_new',
                name: 'cbg_new',
                defaultContent: '-'
            },
            {
                data: 'unit',
                name: 'unit',
                className: 'text-right'
            },
            {
                data: 'val_net',
                name: 'val_net',
                className: 'text-right'
            },
        ],
        order: [
            [16, 'desc']
        ]
    });

    $('#filter_periode').on('change changeDate input', function() {
        table.ajax.reload();
    });

    $('#btnRefresh').on('click', function() {
        table.ajax.reload();
    });

    $('#upload_periode').on('change changeDate input', syncUploadPeriod);

    $('#modalUploadTopMarketing').on('shown.bs.modal', function() {
        $('#upload_periode').val($('#filter_periode').val()).trigger('change');
        $('#upload_file_error').text('');
    });

    $('#formUploadTopMarketing').on('submit', function(e) {
        e.preventDefault();
        syncUploadPeriod();

        var form = this;
        var formData = new FormData(form);
        var button = $('#btnUploadTopMarketing');

        $('#upload_file_error').text('');
        button.prop('disabled', true).text('Uploading...');

        var uploadUrl = config.routes.upload || '/top-marketing/upload';

        $.ajax({
            url: uploadUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#modalUploadTopMarketing').modal('hide');
                form.reset();
                table.ajax.reload();
                Swal.fire('Berhasil', response.message, 'success').then(function() {
                    window.location.reload();
                });
            },
            error: function(xhr) {
                var response = xhr.responseJSON || {};
                var message = response.message || 'Upload top marketing gagal.';

                if (response.errors && response.errors.file && response.errors.file.length) {
                    message = response.errors.file[0];
                    $('#upload_file_error').text(message);
                }

                Swal.fire('Gagal', message, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('Upload');
            }
        });
    });
});
