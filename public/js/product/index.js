$(document).ready(function() {
    'use strict';

    const config = window.ProductPageConfig || {
        csrfToken: '',
        routes: {}
    };

    // 1. DataTables
    window.tablePm = $('#tblPm').DataTable({
        processing: true,
        serverSide: true,
        ajax: config.routes.data || '/product/data',
        columns: [{
                data: 'DT_RowIndex',
                orderable: false,
                searchable: false,
                className: 'text-center'
            },
            {
                data: 'c_ph',
                name: 'crm_mst_products.pt_ph'
            },
            {
                data: 'p_desc',
                name: 'parts.pt_desc'
            },
            {
                data: 'id_coverage',
                name: 'crm_mst_products.id_coverage',
                className: 'text-center'
            },
            {
                data: 'action',
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap',
                width: '100px'
            },
        ]
    });

    window.tablePmDist = $('#tblPmDist').DataTable({
        processing: true,
        serverSide: true,
        ajax: config.routes.dataDist || '/product/data-dist',
        columns: [{
                data: 'DT_RowIndex',
                orderable: false,
                searchable: false,
                className: 'text-center'
            },
            {
                data: 'c_dist',
                name: 'v_pt_mstr_dist.pt_dist'
            },
            {
                data: 'c_ph',
                name: 'v_pt_mstr_dist.pt_ph'
            },
            {
                data: 'p_desc',
                name: 'v_pt_mstr_dist.pt_desc'
            },
            {
                data: 'dist',
                name: 'v_pt_mstr_dist.dist',
                className: 'text-center'
            },
            {
                data: 'pilar',
                name: 'v_pt_mstr_dist.pilar_sub',
                className: 'text-center'
            },
            {
                data: 'cov_pm',
                name: 'crm_mst_mappings.cov_nik',
                className: 'text-center'
            },
            {
                data: 'nama_pm',
                name: 'users.name'
            },
        ]
    });

    // 2. Modal and CRUD
    var $modal = $('#modalPm');
    var $product = $('.select2-produk');
    var $coverage = $('#id_coverage_pm');

    if ($.fn.selectpicker) {
        $coverage.selectpicker('render');
    }

    $product.select2({
        dropdownParent: $modal,
        ajax: {
            url: config.routes.produk || '/product/produk',
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

    $('#btnTambah').on('click', function(e) {
        e.preventDefault();
        resetProductModal();
        $('#modalPmLabel').text('Tambah PM');
        $modal.modal('show');
    });

    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        var showUrl = (config.routes.show || '/product/:id').replace(':id', id);

        $.get(showUrl, function(data) {
            resetProductModal();
            $('#pmId').val(data.id);
            $('#modalPmLabel').text('Edit PM');

            var productText = data.mpart && data.mpart.pt_desc ?
                data.pt_ph + ' - ' + data.mpart.pt_desc :
                data.pt_ph;
            var option = new Option(productText, data.pt_ph, true, true);

            $product.append(option).trigger('change');
            setCoverageValue(data.id_coverage);
            $modal.modal('show');
        });
    });

    $('#btnSimpan').on('click', function() {
        var id = $('#pmId').val();
        var url = id ? (config.routes.update || '/product/:id').replace(':id', id) : (config.routes.store || '/product');
        var method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: {
                _token: config.csrfToken,
                pt_ph: $('#pt_ph').val(),
                id_coverage: $coverage.val(),
            },
            success: function(res) {
                $modal.modal('hide');
                Swal.fire('Berhasil', res.message, 'success');
                reloadProductTables();
            },
            error: function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ?
                    xhr.responseJSON.message :
                    'Data gagal disimpan.';
                Swal.fire('Gagal', message, 'error');
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        var url = $(this).data('url') || (config.routes.destroy || '/product/:id').replace(':id', id);

        Swal.fire({
            title: 'Hapus Produk',
            text: 'Apakah Anda yakin ingin menghapus produk ini?',
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
                        reloadProductTables();
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.message ?
                            xhr.responseJSON.message :
                            'Data gagal dihapus.';
                        Swal.fire('Gagal', message, 'error');
                    }
                });
            }
        });
    });

    function resetProductModal() {
        $('#pmId').val('');
        $product.val(null).trigger('change');
        $product.find('option').remove();
        setCoverageValue('');
    }

    function setCoverageValue(value) {
        var selectedValue = value || '';

        if ($.fn.selectpicker) {
            $coverage.selectpicker('val', selectedValue);
            $coverage.selectpicker('refresh');
        } else {
            $coverage.val(selectedValue);
            $coverage.trigger('change');
        }
    }

    function reloadProductTables() {
        if (window.tablePm) {
            window.tablePm.ajax.reload(null, false);
        }

        if (window.tablePmDist) {
            window.tablePmDist.ajax.reload(null, false);
        }
    }
});
