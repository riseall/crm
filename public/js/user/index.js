$(document).ready(function() {
    'use strict';

    const config = window.UserPageConfig || {
        routes: {}
    };

    var table = $('#table_pengguna').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
            url: config.routes.data || '/users/data',
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
        ],
    });

    // Ekstensi Buttons dari DataTables
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
