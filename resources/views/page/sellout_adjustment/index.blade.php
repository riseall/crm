@extends('layouts.app', ['title' => 'Adjust Data Sellout'])

@section('content')
    <!-- Stat Cards Summary -->
    <div class="row" id="statsContainer">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL TRANSAKSI" value="{{ number_format($totalData, 0, ',', '.') }}" subtitle="Bulan Berjalan"
                subtitleColor="text-primary" icon="far fa-folder-open" color="primary" id="cardTotalData">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL NETTO" value="Rp {{ number_format($totalNetto, 0, ',', '.') }}"
                subtitle="Bulan Berjalan" subtitleColor="text-success" icon="fas fa-coins" color="success"
                id="cardTotalNetto">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="DIVISI REGULER" value="{{ number_format($countReguler, 0, ',', '.') }}" subtitle="Transaksi"
                subtitleColor="text-info" icon="fas fa-layer-group" color="info" id="cardCountReguler">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="DIVISI CMA" value="{{ number_format($countCma, 0, ',', '.') }}" subtitle="Transaksi"
                subtitleColor="text-warning" icon="fas fa-check-circle" color="warning" id="cardCountCma">
            </x-stat-card>
        </div>
    </div>

    <!-- Filter Bar Component -->
    <x-filter-bar :pilars="$pilarOptions" :distributors="$distOptions" title="Adjust Data Sellout"
        subtitle="Filter data sellout berdasarkan periode dan produk" icon="fa-sliders-h"
        :show-header="false" :show-bulan="false" :show-tahun="false" :show-period="true" :show-distributor="false" :show-pilar="false"
        :show-reset="true" id-period="filter_period" id-start-date="filter_start_date" id-end-date="filter_end_date"
        id-distributor="filter_dist" id-pilar="filter_pilar" id-apply="btnApplyFilter" id-reset="btnResetFilter"
        selected-start-date="{{ $startOfMonth }}" selected-end-date="{{ $endOfMonth }}">

        <div class="col-12 col-sm-auto mb-2 p-0" style="min-width: 220px; max-width: 320px;">
            <label class="font-size-xs font-weight-bolder text-dark-75 mb-1 d-block">Produk</label>
            <select id="filter_product_code" class="form-control form-control-sm select2-filter-product"
                style="width: 100%;"></select>
        </div>
    </x-filter-bar>

    <!-- DataTable Card -->
    <x-datatable-card title="Data Sellout & Penyesuaian Divisi"
        subtitle="Kelola dan ubah divisi data transaksi sellout secara single maupun bulk"
        table-id="tblSelloutAdjustment">
        <x-slot name="beforeTable">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 p-3 bg-light rounded">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <span id="selectionBadge"
                        class="badge badge-light-primary text-primary font-weight-bolder py-2 px-3 mr-3"
                        style="display: none;">
                        <i class="fas fa-check-square mr-1"></i> <span id="selectionCount">0</span> baris dipilih
                    </span>
                    <button type="button" class="btn btn-sm btn-primary font-weight-bolder mr-2" id="btnOpenBulkEdit"
                        disabled>
                        <i class="fas fa-edit"></i> Adjust Terpilih
                    </button>
                </div>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-light-primary font-weight-bolder" id="btnRefreshTable">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </x-slot>

        <th width="30" class="text-center">
            <div class="custom-control custom-checkbox text-center">
                <input type="checkbox" class="custom-control-input" id="checkAllRows">
                <label class="custom-control-label" for="checkAllRows"></label>
            </div>
        </th>
        <th>No</th>
        <th>Tgl Faktur</th>
        <th>No Faktur</th>
        <th>Dist</th>
        <th>Cabang</th>
        <th>Outlet</th>
        <th>Kode Produk</th>
        <th>Nama Produk</th>
        <th>Pilar</th>
        <th>Divisi</th>
        <th>Sub Divisi</th>
        <th>Qty</th>
        <th>Netto</th>
        <th width="60" class="text-center">Aksi</th>
    </x-datatable-card>

    <!-- Modals -->
    @include('page.sellout_adjustment.modal')
@endsection

@push('scripts')
    <script
        src="{{ asset('js/dashboard/date-range-filter.js') }}?v={{ filemtime(public_path('js/dashboard/date-range-filter.js')) }}">
    </script>
    <script>
        $(document).ready(function() {
            var selectedRowIds = new Set();
            var divisiSubDivisiMap = @json($divisiSubDivisiMap);

            // Helper sinkronisasi dropdown Divisi -> Sub Divisi
            function syncSubDivisiSelect($divSelect, $subSelect, selectedVal) {
                var divVal = $divSelect.val();
                var options = divisiSubDivisiMap[divVal] || ['CMA-MMR', 'CMA-OTC', 'CMA-PBF', 'REGULER'];

                $subSelect.empty();
                $subSelect.append('<option value="">-- Pilih Sub Divisi --</option>');
                options.forEach(function(opt) {
                    var isSelected = (selectedVal && selectedVal === opt) ? 'selected' : '';
                    $subSelect.append('<option value="' + opt + '" ' + isSelected + '>' + opt +
                        '</option>');
                });

                if (!selectedVal) {
                    if (options.length === 1) {
                        $subSelect.val(options[0]);
                    } else if (divVal === 'CMA') {
                        $subSelect.val('CMA-MMR');
                    }
                } else {
                    $subSelect.val(selectedVal);
                }

                $subSelect.selectpicker('refresh');
            }

            // Inisialisasi Select2 Filter Produk
            $('#filter_product_code').select2({
                ajax: {
                    url: '{{ route('sellout-adjustment.products') }}',
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
                    cache: true
                },
                placeholder: '-- Semua Produk (Cari Kode/Nama) --',
                minimumInputLength: 2,
                allowClear: true
            });

            // Inisialisasi DataTable
            window.tableSellout = $('#tblSelloutAdjustment').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                scrollX: true,
                ajax: {
                    url: '{{ route('sellout-adjustment.data') }}',
                    data: function(d) {
                        d.start_date = $('#filter_start_date').val();
                        d.end_date = $('#filter_end_date').val();
                        d.dist = $('#filter_dist').val();
                        d.divisi = $('#filter_divisi').val();
                        d.sub_divisi = $('#filter_sub_divisi').val();
                        d.pilar = $('#filter_pilar').val();
                        d.product_code = $('#filter_product_code').val();
                    }
                },
                columns: [{
                        data: 'checkbox',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        width: '30px'
                    },
                    {
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        width: '40px'
                    },
                    {
                        data: 'tgl_faktur',
                        name: 'so.tgl_faktur',
                        className: 'text-nowrap'
                    },
                    {
                        data: 'no_faktur',
                        name: 'so.no_faktur',
                        className: 'font-weight-bold text-nowrap'
                    },
                    {
                        data: 'dist',
                        name: 'so.dist',
                        className: 'text-center'
                    },
                    {
                        data: 'id_cbg',
                        name: 'so.id_cbg',
                        className: 'text-center'
                    },
                    {
                        data: 'nama_outlet',
                        name: 'so.nama_outlet',
                        render: function(data, type, row) {
                            var nama = data ? data : (row.id_outlet || '-');
                            return '<div class="text-dark font-weight-bold font-size-sm">' +
                                nama + '</div>' +
                                '<span class="text-muted font-size-xs">' + (row.id_outlet || '') +
                                '</span>';
                        }
                    },
                    {
                        data: 'code_prod_ph',
                        name: 'so.code_prod_ph',
                        className: 'text-center font-weight-bold font-size-sm',
                        render: function(data, type, row) {
                            return data || row.code_product || '-';
                        }
                    },
                    {
                        data: 'desc',
                        name: 'so.desc',
                        render: function(data, type, row) {
                            return data || '-';
                        }
                    },
                    {
                        data: 'pilar_sub',
                        name: 'so.pilar_sub',
                        className: 'text-center font-weight-bold'
                    },
                    {
                        data: 'divisi',
                        name: 'so.divisi',
                        className: 'text-center'
                    },
                    {
                        data: 'sub_divisi',
                        name: 'so.sub_divisi',
                        className: 'text-center',
                        render: function(data) {
                            if (!data) return '-';
                            var badgeClass = 'badge-light-dark';
                            if (data === 'CMA-MMR') badgeClass = 'badge-light-primary';
                            else if (data === 'CMA-OTC') badgeClass = 'badge-light-success';
                            else if (data === 'CMA-PBF') badgeClass = 'badge-light-info';
                            else if (data === 'REGULER') badgeClass = 'badge-light-warning';
                            return '<span class="badge ' + badgeClass +
                                ' font-weight-bold px-2 py-1">' + data + '</span>';
                        }
                    },
                    {
                        data: 'qty',
                        name: 'so.qty',
                        className: 'text-right font-weight-bold'
                    },
                    {
                        data: 'netto',
                        name: 'so.netto',
                        className: 'text-right font-weight-bolder text-primary text-nowrap'
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        width: '60px'
                    }
                ],
                order: [
                    [2, 'desc']
                ],
                drawCallback: function() {
                    // Re-check selected rows when page changes
                    $('.row-checkbox').each(function() {
                        var id = $(this).val();
                        if (selectedRowIds.has(id)) {
                            $(this).prop('checked', true);
                        }
                    });
                    updateSelectionUI();
                }
            });

            // Update Selection UI
            function updateSelectionUI() {
                var count = selectedRowIds.size;
                $('#selectionCount').text(count);
                $('#bulk_selected_count').text(count);

                if (count > 0) {
                    $('#selectionBadge').show();
                    $('#btnOpenBulkEdit').prop('disabled', false);
                } else {
                    $('#selectionBadge').hide();
                    $('#btnOpenBulkEdit').prop('disabled', true);
                }

                // Check "checkAllRows" state
                var totalOnPage = $('.row-checkbox').length;
                var checkedOnPage = $('.row-checkbox:checked').length;
                $('#checkAllRows').prop('checked', totalOnPage > 0 && totalOnPage === checkedOnPage);
            }

            // Checkbox All on Page
            $('#checkAllRows').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.row-checkbox').each(function() {
                    var id = $(this).val();
                    $(this).prop('checked', isChecked);
                    if (isChecked) {
                        selectedRowIds.add(id);
                    } else {
                        selectedRowIds.delete(id);
                    }
                });
                updateSelectionUI();
            });

            // Individual Row Checkbox
            $('#tblSelloutAdjustment').on('change', '.row-checkbox', function() {
                var id = $(this).val();
                if ($(this).is(':checked')) {
                    selectedRowIds.add(id);
                } else {
                    selectedRowIds.delete(id);
                }
                updateSelectionUI();
            });

            // Refresh Dynamic Stats
            function reloadStats() {
                $.ajax({
                    url: '{{ route('sellout-adjustment.stats') }}',
                    type: 'GET',
                    data: {
                        start_date: $('#filter_start_date').val(),
                        end_date: $('#filter_end_date').val(),
                        dist: $('#filter_dist').val(),
                        divisi: $('#filter_divisi').val(),
                        sub_divisi: $('#filter_sub_divisi').val(),
                        pilar: $('#filter_pilar').val(),
                        product_code: $('#filter_product_code').val()
                    },
                    success: function(res) {
                        $('#cardTotalData .font-size-h2').text(Number(res.total_count).toLocaleString(
                            'id-ID'));
                        $('#cardTotalNetto .font-size-h2').text(res.total_netto);
                        $('#cardCountReguler .font-size-h2').text(Number(res.count_reguler)
                            .toLocaleString('id-ID'));
                        $('#cardCountCma .font-size-h2').text(Number(res.count_cma).toLocaleString(
                            'id-ID'));
                    }
                });
            }

            // Apply Filter
            $('#btnApplyFilter').on('click', function() {
                selectedRowIds.clear();
                updateSelectionUI();
                window.tableSellout.ajax.reload();
                reloadStats();
            });

            // Reset Filter Event dari x-filter-bar
            window.addEventListener('filter:reset', function(e) {
                if (e.detail && e.detail.id === 'btnResetFilter') {
                    $('#filter_product_code').val(null).trigger('change');
                    selectedRowIds.clear();
                    updateSelectionUI();
                    window.tableSellout.ajax.reload();
                    reloadStats();
                }
            });

            // Refresh Button
            $('#btnRefreshTable').on('click', function() {
                window.tableSellout.ajax.reload(null, false);
                reloadStats();
            });

            // ----------------------------------------------------
            // Modal Events: Sinkronisasi Divisi -> Sub Divisi
            // ----------------------------------------------------
            $('#single_edit_divisi').on('change', function() {
                syncSubDivisiSelect($('#single_edit_divisi'), $('#single_edit_sub_divisi'), null);
            });

            $('#bulk_edit_divisi').on('change', function() {
                syncSubDivisiSelect($('#bulk_edit_divisi'), $('#bulk_edit_sub_divisi'), null);
            });

            // ----------------------------------------------------
            // 1. SINGLE EDIT MODAL & ACTION
            // ----------------------------------------------------
            $('#tblSelloutAdjustment').on('click', '.btn-edit-single', function() {
                var btn = $(this);
                var id = btn.data('id');
                var faktur = btn.data('faktur');
                var outlet = btn.data('outlet');
                var code = btn.data('code');
                var desc = btn.data('desc');
                var pilar = btn.data('pilar');
                var divisi = btn.data('divisi');
                var subDivisi = btn.data('sub-divisi');
                var netto = btn.data('netto');

                $('#single_edit_id').val(id);
                $('#single_info_faktur').text(faktur || '-');
                $('#single_info_pilar').text(pilar || '-');
                $('#single_info_outlet').text(outlet || '-');
                $('#single_info_product').text((code ? code + ' - ' : '') + (desc || '-'));
                $('#single_info_netto').text('Rp ' + netto);

                var targetDivisi = divisi ? (divisi.toUpperCase().includes('CMA') ? 'CMA' : (divisi
                    .toUpperCase().includes('REGULER') ? 'REGULER' : divisi)) : 'CMA';
                $('#single_edit_divisi').val(targetDivisi).selectpicker('refresh');
                syncSubDivisiSelect($('#single_edit_divisi'), $('#single_edit_sub_divisi'), subDivisi);

                $('#error_single_divisi').text('').hide();
                $('#error_single_sub_divisi').text('').hide();

                $('#modalSingleEdit').modal('show');
            });

            $('#btnSaveSingleEdit').on('click', function() {
                var id = $('#single_edit_id').val();
                var divisi = $('#single_edit_divisi').val();
                var subDivisi = $('#single_edit_sub_divisi').val();

                if (!divisi) {
                    $('#error_single_divisi').text('Silakan pilih divisi target.').show();
                    return;
                }
                if (!subDivisi) {
                    $('#error_single_sub_divisi').text('Silakan pilih sub divisi.').show();
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

                $.ajax({
                    url: '{{ url('sellout-adjustment') }}/' + id,
                    type: 'PUT',
                    data: {
                        _token: '{{ csrf_token() }}',
                        divisi: divisi,
                        sub_divisi: subDivisi
                    },
                    success: function(res) {
                        $('#modalSingleEdit').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        window.tableSellout.ajax.reload(null, false);
                        reloadStats();
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr
                            .responseJSON.message : 'Terjadi kesalahan sistem.';
                        Swal.fire('Gagal!', msg, 'error');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Simpan Perubahan');
                    }
                });
            });

            // ----------------------------------------------------
            // 2. BULK EDIT (CHECKBOX SELECTED)
            // ----------------------------------------------------
            $('#btnOpenBulkEdit').on('click', function() {
                if (selectedRowIds.size === 0) {
                    Swal.fire('Peringatan', 'Pilih minimal 1 baris transaksi terlebih dahulu.', 'warning');
                    return;
                }
                $('#bulk_edit_divisi').val('CMA').selectpicker('refresh');
                syncSubDivisiSelect($('#bulk_edit_divisi'), $('#bulk_edit_sub_divisi'), 'CMA-MMR');
                $('#error_bulk_divisi').text('').hide();
                $('#error_bulk_sub_divisi').text('').hide();
                $('#modalBulkEdit').modal('show');
            });

            $('#btnSaveBulkEdit').on('click', function() {
                var ids = Array.from(selectedRowIds);
                var divisi = $('#bulk_edit_divisi').val();
                var subDivisi = $('#bulk_edit_sub_divisi').val();

                if (!divisi) {
                    $('#error_bulk_divisi').text('Silakan pilih divisi target.').show();
                    return;
                }
                if (!subDivisi) {
                    $('#error_bulk_sub_divisi').text('Silakan pilih sub divisi target.').show();
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');

                $.ajax({
                    url: '{{ route('sellout-adjustment.update-bulk') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids,
                        divisi: divisi,
                        sub_divisi: subDivisi
                    },
                    success: function(res) {
                        $('#modalBulkEdit').modal('hide');
                        selectedRowIds.clear();
                        updateSelectionUI();
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                            timer: 2500,
                            showConfirmButton: false
                        });
                        window.tableSellout.ajax.reload(null, false);
                        reloadStats();
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr
                            .responseJSON.message : 'Terjadi kesalahan sistem.';
                        Swal.fire('Gagal!', msg, 'error');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Terapkan Perubahan');
                    }
                });
            });
        });
    </script>
@endpush
