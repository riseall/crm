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
            {
                data: 'action',
                className: 'text-center text-nowrap',
                orderable: false,
                searchable: false
            }
        ],
        order: [[1, 'asc']]
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

    // CRUD Event Handlers
    const modalUser = $('#modal_user');
    const formUser = $('#form_user');
    let editId = null;

    $('#btnTambah').on('click', function(e) {
        e.preventDefault();
        editId = null;
        formUser.trigger('reset');
        $('#nik').empty().trigger('change');
        $('#form_method').val('POST');
        $('#modal_userLabel').text('Tambah User');
        modalUser.modal('show');
    });

    $(document).on('click', '.edit-btn', function() {
        editId = $(this).data('id');
        formUser.trigger('reset');
        $('#form_method').val('PUT');
        $('#modal_userLabel').text('Edit User');
        
        let nik = $(this).data('nik');
        let name = $(this).data('name');
        let newOption = new Option(nik + ' - ' + name, nik, true, true);
        $('#nik').empty().append(newOption).trigger('change');
        
        $('#name').val(name);
        $('#email').val($(this).data('email'));
        $('#jabatan').val($(this).data('jabatan'));
        $('#role').val($(this).data('role'));
        $('#is_active').prop('checked', $(this).data('is_active') == 1);
        
        modalUser.modal('show');
    });

    $('#btnSimpan').on('click', function(e) {
        e.preventDefault();
        formUser.trigger('submit');
    });

    formUser.on('submit', function(e) {
        e.preventDefault();
        let url = editId ? config.routes.update.replace(':id', editId) : config.routes.store;
        
        $.ajax({
            url: url,
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                modalUser.modal('hide');
                table.ajax.reload(null, false);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', response.success, 'success');
                } else {
                    alert(response.success);
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan';
                if(xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(e => e.join(', ')).join('<br>');
                } else if(xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error',
                        html: msg,
                        icon: 'error'
                    });
                } else {
                    alert(msg.replace(/<br>/g, '\n'));
                }
            }
        });
    });

    $(document).on('click', '.delete-btn', function() {
        let id = $(this).data('id');
        let url = config.routes.destroy.replace(':id', id);
        
        let confirmDelete = () => {
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _method: 'DELETE',
                    _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
                },
                success: function(response) {
                    table.ajax.reload(null, false);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Terhapus!', response.success, 'success');
                    }
                },
                error: function(xhr) {
                    let msg = 'Gagal menghapus';
                    if(xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (typeof Swal !== 'undefined') Swal.fire('Error', msg, 'error');
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Hapus User?',
                text: "Data tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed || result.value) {
                    confirmDelete();
                }
            });
        } else {
            if (confirm('Hapus user ini?')) confirmDelete();
        }
    });

    $('#nik').select2({
        placeholder: "Cari NIK / Nama...",
        allowClear: true,
        dropdownParent: $('#modal_user'),
        ajax: {
            url: '/mapping/anggota',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.results
                };
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        let data = e.params.data;
        $('#name').val(data.name);
        $('#email').val(data.email || '-');
        $('#jabatan').val(data.jabatan || '-');
    }).on('select2:clear', function () {
        $('#name').val('');
        $('#email').val('');
        $('#jabatan').val('');
    });
});
