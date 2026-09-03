<style>
    .outlet-item {
        transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        border: 1px solid #ebedf2;
    }
    .outlet-item:hover {
        background-color: #f4f6fa !important;
        border-color: #3699ff !important;
    }
    .outlet-item.is-checked {
        background-color: #e8f4ff !important;
        border-color: #8ec5fc !important;
    }
    #outletScrollList::-webkit-scrollbar {
        width: 6px;
    }
    #outletScrollList::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    #outletScrollList::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    #outletScrollList::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    .custom-mode-card {
        background-color: #ffffff;
        border: 2px solid #ebedf2;
        border-radius: 8px;
        transition: all 0.2s ease-in-out;
        cursor: pointer;
    }
    .custom-mode-card:hover {
        border-color: #3699ff;
        background-color: #fbfdff;
    }
    .custom-mode-card.active-primary {
        border-color: #3699ff !important;
        background-color: #f0f7ff !important;
        box-shadow: 0 0 10px rgba(54, 153, 255, 0.15);
    }
    .custom-mode-card.active-success {
        border-color: #1bc5bd !important;
        background-color: #f0fbf9 !important;
        box-shadow: 0 0 10px rgba(27, 197, 189, 0.15);
    }
</style>

<x-modal id="modalMapping" title="Tambah Mapping" submitBtnId="btnSimpan">
    <input type="hidden" id="mappingId">
    <div class="form-group">
        <label>Outlet <span class="text-danger">*</span></label>
        <select id="id_outlet" class="form-control select2-outlet" style="width:100%"></select>
    </div>
    <div class="form-group">
        <label>Coverage <span class="text-danger">*</span></label>
        <select id="id_coverage" class="form-control select2-coverage-modal" style="width:100%"></select>
    </div>
</x-modal>

<x-modal id="modalBulkUpdate" title="Bulk Update Coverage" size="modal-lg" submitBtnId="btnBulkSimpan"
    submitBtnText="Pindahkan">
    <div class="form-group">
        <label>Coverage Lama <span class="text-danger">*</span></label>
        <select id="old_coverage_id" class="form-control select2-coverage-modal" style="width:100%"></select>
    </div>
    <div class="form-group">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="mb-0 font-weight-bold">Pilih Outlet yang Dipindah <span class="text-danger">*</span></label>
            <span class="badge badge-light-primary text-primary font-weight-bolder" id="outletCountBadge" style="display: none;">0 dipilih</span>
        </div>
        <div id="outletList" class="border p-3 rounded bg-light">
            <small class="text-muted">Pilih coverage lama terlebih dahulu.</small>
        </div>
    </div>
    <div class="form-group">
        <label>Coverage Baru <span class="text-danger">*</span></label>
        <select id="new_coverage_id" class="form-control select2-coverage-modal" style="width:100%"></select>
    </div>
</x-modal>

<x-modal id="modalMapUnmapped" title="Petakan Outlet Sellout ke Master Customer" size="modal-lg" submitBtnId="btnSubmitMapUnmapped"
    submitBtnText="Simpan Pemetaan">
    <!-- Info Ringkasan Outlet Dist -->
    <div class="card card-custom bg-light border mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between pb-2 mb-2 border-bottom">
                <div class="d-flex align-items-center mb-1 mb-md-0">
                    <span class="badge badge-primary font-weight-bolder px-2 py-1 mr-2" id="unmapped_info_dist">-</span>
                    <span class="text-dark font-weight-bold" id="unmapped_info_cbg">-</span>
                </div>
                <div>
                    <span class="badge badge-light-dark font-weight-bold" id="unmapped_info_segment">-</span>
                </div>
            </div>
            <div class="row font-size-sm">
                <div class="col-md-7 mb-2 mb-md-0">
                    <span class="text-muted font-size-xs d-block">Nama Outlet di Distributor:</span>
                    <strong id="unmapped_info_nama" class="text-dark font-size-base">-</strong>
                </div>
                <div class="col-md-5">
                    <span class="text-muted font-size-xs d-block">Kode Outlet Dist:</span>
                    <strong id="unmapped_info_outlet" class="text-dark">-</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Fields -->
    <input type="hidden" id="map_dist">
    <input type="hidden" id="map_id_cbg">
    <input type="hidden" id="map_id_outlet">
    <input type="hidden" name="map_mode" id="map_mode_val" value="existing">

    <!-- Pilih Mode Pemetaan (Cards) -->
    <div class="form-group mb-4">
        <label class="font-weight-bolder text-dark font-size-sm text-uppercase d-block mb-2">Metode Pemetaan <span class="text-danger">*</span></label>
        <div class="row">
            <div class="col-sm-6 mb-2 mb-sm-0">
                <div class="custom-mode-card active-primary p-3" id="cardModeExisting" data-mode="existing">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40 symbol-light-primary mr-3 flex-shrink-0">
                            <span class="symbol-label font-size-h5">
                                <i class="fas fa-link text-primary"></i>
                            </span>
                        </div>
                        <div>
                            <div class="font-weight-bolder text-dark">Master Eksisting</div>
                            <div class="text-muted font-size-xs">Hubungkan ke ID Konversi yang sudah ada</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="custom-mode-card p-3" id="cardModeNew" data-mode="new">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40 symbol-light-success mr-3 flex-shrink-0">
                            <span class="symbol-label font-size-h5">
                                <i class="fas fa-plus-circle text-success"></i>
                            </span>
                        </div>
                        <div>
                            <div class="font-weight-bolder text-dark">Master Baru</div>
                            <div class="text-muted font-size-xs">Buat data Master Customer baru</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 1: Hubungkan ke Master Eksisting -->
    <div id="sectionExistingMaster" class="p-3 border rounded bg-white mb-3">
        <div class="form-group mb-0">
            <label class="font-weight-bold text-dark mb-1">Pilih Master Customer (ID Konversi) <span class="text-danger">*</span></label>
            <select id="map_existing_id_konversi" class="form-control select2-unmapped-master" style="width: 100%"></select>
            <span class="form-text text-muted font-size-xs mt-1">
                <i class="fas fa-info-circle mr-1 text-primary"></i> Ketik min. 2 karakter untuk mencari ID Konversi atau Nama Master Outlet di database CRM
            </span>
        </div>
    </div>

    <!-- SECTION 2: Buat Master Outlet Baru -->
    <div id="sectionNewMaster" class="p-3 border rounded bg-white mb-3" style="display: none;">
        <div class="row">
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">ID Konversi Baru <span class="text-danger">*</span></label>
                <input type="text" id="map_new_id_konversi" class="form-control" placeholder="Contoh: KFTD_100123 / KODE">
                <span class="form-text text-muted font-size-xs">ID unik master customer di CRM</span>
            </div>
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">Nama Master Outlet <span class="text-danger">*</span></label>
                <input type="text" id="map_new_nama_outlet" class="form-control" placeholder="Nama Master Outlet">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">Segment</label>
                <select id="map_new_segment" class="form-control">
                    <option value="">-- Pilih Segment --</option>
                    <option value="APOTEK">APOTEK</option>
                    <option value="RUMAH SAKIT">RUMAH SAKIT</option>
                    <option value="KLINIK">KLINIK</option>
                    <option value="TOKO OBAT">TOKO OBAT</option>
                    <option value="PBF">PBF</option>
                    <option value="DOKTER">DOKTER</option>
                    <option value="INSTITUSI">INSTITUSI</option>
                    <option value="LAIN-LAIN">LAIN-LAIN</option>
                </select>
            </div>
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">Kota / Kabupaten</label>
                <input type="text" id="map_new_kota" class="form-control" placeholder="Kota atau Kabupaten">
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="font-weight-bold text-dark">Alamat Lengkap (Opsional)</label>
            <textarea id="map_new_alamat" class="form-control" rows="2" placeholder="Alamat lengkap outlet..."></textarea>
        </div>
    </div>

    <!-- SECTION 3: Coverage Assignment (Opsional/Direkomendasikan) -->
    <div class="p-3 border rounded bg-light mb-1">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-user-check text-primary mr-2"></i>
            <h6 class="font-weight-bolder text-dark mb-0 font-size-sm">
                Penugasan Coverage & Sales Rep <span class="text-muted font-weight-normal font-size-xs">(Opsional)</span>
            </h6>
        </div>
        <div class="row">
            <div class="col-md-4 form-group mb-2 mb-md-0">
                <label class="font-weight-bold text-dark font-size-xs text-uppercase">Pilar</label>
                <select id="map_pilar" class="form-control">
                    <option value="ETHICAL">ETHICAL</option>
                    <option value="OGB">OGB</option>
                    <option value="CH">CH</option>
                </select>
            </div>
            <div class="col-md-8 form-group mb-0">
                <label class="font-weight-bold text-dark font-size-xs text-uppercase">Coverage (Sales Rep)</label>
                <select id="map_id_coverage" class="form-control select2-unmapped-coverage" style="width: 100%"></select>
                <span class="form-text text-muted font-size-xs mt-1">Pilih sales rep yang meng-cover outlet ini</span>
            </div>
        </div>
    </div>
</x-modal>

<x-modal id="modalAssignPilarCoverage" title="Tetapkan Coverage Sales Rep (Pilar)" submitBtnId="btnSubmitAssignPilar"
    submitBtnText="Simpan Coverage">
    <div class="card card-custom bg-light border mb-4">
        <div class="card-body p-3">
            <div class="row font-size-sm">
                <div class="col-md-7 mb-2 mb-md-0">
                    <span class="text-muted font-size-xs d-block">ID Konversi & Nama Master:</span>
                    <strong id="assign_info_id_konversi" class="text-primary font-weight-bolder font-size-base">-</strong>
                    <div id="assign_info_nama_outlet" class="text-dark font-weight-bold">-</div>
                </div>
                <div class="col-md-5">
                    <span class="text-muted font-size-xs d-block">Pilar Transaksi:</span>
                    <span class="badge badge-primary font-weight-bolder font-size-sm" id="assign_info_pilar">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Fields -->
    <input type="hidden" id="assign_id_konversi">
    <input type="hidden" id="assign_pilar">

    <div class="form-group mb-0">
        <label class="font-weight-bold text-dark">Pilih Coverage / Sales Rep <span class="text-danger">*</span></label>
        <select id="assign_id_coverage" class="form-control select2-assign-coverage" style="width: 100%"></select>
        <span class="form-text text-muted font-size-xs mt-1">Pilih sales rep yang akan meng-cover outlet ini pada pilar tersebut</span>
    </div>
</x-modal>

@push('scripts')
    <script>
        $(document).ready(function() {
            var $mappingModal = $('#modalMapping');
            var $bulkModal = $('#modalBulkUpdate');
            var $outlet = $('.select2-outlet');
            var $coverage = $('#id_coverage');
            var $oldCoverage = $('#old_coverage_id');
            var $newCoverage = $('#new_coverage_id');

            $outlet.select2({
                dropdownParent: $mappingModal,
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

            initCoverageSelect($coverage, $mappingModal, 'Cari Coverage, NIK, atau Nama...', false);
            initCoverageSelect($oldCoverage, $bulkModal, 'Cari Coverage Lama...', true);
            initCoverageSelect($newCoverage, $bulkModal, 'Cari Coverage Baru...', false);

            $(document).on('click', '#btnTambah', function(e) {
                e.preventDefault();
                resetMappingModal();
                $('#modalMappingLabel').text('Tambah Mapping');
                $mappingModal.modal('show');
            });

            $(document).on('click', '.btn-edit', function() {
                var id = $(this).data('id');

                $.get('{{ route('outlet.show', ':id') }}'.replace(':id', id), function(data) {
                    resetMappingModal();
                    $('#mappingId').val(data.id);
                    $('#modalMappingLabel').text('Edit Mapping');

                    var outletText = data.outlet_text ||
                        (data.cust_dist && data.cust_dist.nama_outlet ?
                            data.id_outlet + ' - ' + data.cust_dist.nama_outlet :
                            data.id_outlet);
                    var option = new Option(outletText, data.id_outlet, true, true);

                    $outlet.append(option).trigger('change');
                    setSelect2Value($coverage, data.id_coverage, coverageText(data.coverage, data.id_coverage));
                    $mappingModal.modal('show');
                });
            });

            $(document).on('click', '#btnSimpan', function() {
                var id = $('#mappingId').val();
                var url = id ? '{{ route('outlet.update', ':id') }}'.replace(':id', id) : '{{ route('outlet.store') }}';
                var method = id ? 'PUT' : 'POST';

                $.ajax({
                    url: url,
                    type: method,
                    data: {
                        _token: '{{ csrf_token() }}',
                        id_outlet: $('#id_outlet').val(),
                        id_coverage: $coverage.val(),
                    },
                    success: function(res) {
                        $mappingModal.modal('hide');
                        Swal.fire('Berhasil', res.message, 'success');
                        reloadMappingTable();
                    },
                    error: function(xhr) {
                        Swal.fire('Gagal', getErrorMessage(xhr, 'Data gagal disimpan.'),
                            'error');
                    }
                });
            });

            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                var url = $(this).data('url') || '{{ route('outlet.destroy', ':id') }}'.replace(':id', id);

                Swal.fire({
                    title: 'Hapus Mapping?',
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
                                reloadMappingTable();
                            },
                            error: function(xhr) {
                                Swal.fire('Gagal', getErrorMessage(xhr,
                                        'Data gagal dihapus.'),
                                    'error');
                            }
                        });
                    }
                });
            });

            $(document).on('click', '#btnBulkUpdate', function(e) {
                e.preventDefault();
                resetBulkModal();
                $bulkModal.modal('show');
            });

            $oldCoverage.on('change', function() {
                var coverageId = $(this).val();
                $('#outletCountBadge').hide().text('0 dipilih');

                if (!coverageId) {
                    $('#outletList').html(
                        '<small class="text-muted">Pilih coverage lama terlebih dahulu.</small>');
                    return;
                }

                $('#outletList').html(
                    '<div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat daftar outlet...</div>'
                );

                $.get('{{ url('outlet/outlet-by-coverage') }}/' + encodeURIComponent(coverageId), function(data) {
                    if (!data || !data.length) {
                        $('#outletList').html(
                            '<div class="text-muted text-center py-2"><i class="fas fa-info-circle mr-1"></i> Tidak ada outlet untuk coverage ini.</div>'
                        );
                        return;
                    }

                    var html = '<div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">';
                    html += '  <div class="custom-control custom-checkbox">';
                    html += '    <input type="checkbox" class="custom-control-input" id="checkAll">';
                    html += '    <label class="custom-control-label font-weight-bold cursor-pointer" for="checkAll">Pilih Semua (' + data.length + ')</label>';
                    html += '  </div>';
                    html += '  <div style="max-width: 220px;" class="w-100">';
                    html += '    <input type="text" id="searchOutletList" class="form-control form-control-sm" placeholder="Cari kode/nama outlet...">';
                    html += '  </div>';
                    html += '</div>';

                    html += '<div id="outletScrollList" class="pr-1" style="max-height: 250px; overflow-y: auto;">';
                    $.each(data, function(i, item) {
                        var badge = item.needs_replacement ?
                            '<span class="badge badge-warning font-size-xs ml-1">Butuh Pengganti</span>' :
                            '';
                        var pilarBadge = item.pilar ?
                            '<span class="badge badge-light-dark font-weight-bold font-size-xs ml-1">' + item.pilar + '</span>' :
                            '';
                        var outletName = item.nama_outlet && item.nama_outlet !== '-' ?
                            item.nama_outlet :
                            '';
                        var searchStr = (item.id_outlet + ' ' + (item.nama_outlet || '') + ' ' + (item.pilar || '') + ' ' + (item.kota || '')).toLowerCase();

                        html += '<div class="outlet-item d-flex align-items-center justify-content-between p-2 mb-1 bg-white rounded cursor-pointer" data-search="' + searchStr.replace(/"/g, '&quot;') + '">';
                        html += '  <div class="custom-control custom-checkbox d-flex align-items-center flex-grow-1 mr-2 text-truncate">';
                        html += '    <input type="checkbox" class="custom-control-input chk-outlet" id="chk_' + item.id + '" value="' + item.id + '">';
                        html += '    <label class="custom-control-label d-block text-truncate cursor-pointer mb-0 w-100 font-size-sm" for="chk_' + item.id + '">';
                        html += '      <span class="font-weight-bolder text-dark">' + item.id_outlet + '</span>';
                        if (outletName) {
                            html += '      <span class="text-muted mx-1">-</span><span class="text-dark">' + outletName + '</span>';
                        }
                        html += '    </label>';
                        html += '  </div>';
                        html += '  <div class="d-flex align-items-center flex-shrink-0">';
                        html += pilarBadge + badge;
                        html += '  </div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    $('#outletList').html(html);
                    updateOutletCountFast();
                }).fail(function(xhr) {
                    $('#outletList').html(
                        '<div class="text-danger text-center py-2"><i class="fas fa-exclamation-triangle mr-1"></i> ' +
                        getErrorMessage(xhr, 'Gagal memuat outlet.') + '</div>'
                    );
                });
            });

            function updateOutletCountFast() {
                var $all = $('.chk-outlet');
                var total = $all.length;
                if (total === 0) {
                    $('#outletCountBadge').hide();
                    return;
                }

                var checked = $('.chk-outlet:checked').length;
                $('#outletCountBadge').show().text(checked + ' dari ' + total + ' dipilih');
                $('#checkAll').prop('checked', checked === total && total > 0);
            }

            $(document).on('change', '#checkAll', function() {
                var isChecked = this.checked;
                var $filterInput = $('#searchOutletList');
                var query = $filterInput.length ? $filterInput.val().trim() : '';

                if (query !== '') {
                    var $visible = $('.outlet-item:not([style*="display: none"])');
                    $visible.find('.chk-outlet').prop('checked', isChecked);
                    $visible.toggleClass('is-checked', isChecked);
                } else {
                    $('.chk-outlet').prop('checked', isChecked);
                    $('.outlet-item').toggleClass('is-checked', isChecked);
                }

                updateOutletCountFast();
            });

            $(document).on('change', '.chk-outlet', function() {
                $(this).closest('.outlet-item').toggleClass('is-checked', this.checked);
                updateOutletCountFast();
            });

            var searchTimer = null;
            $(document).on('keyup search', '#searchOutletList', function() {
                var q = this.value.toLowerCase().trim();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    var items = document.querySelectorAll('.outlet-item');
                    for (var i = 0; i < items.length; i++) {
                        var searchStr = items[i].getAttribute('data-search') || '';
                        items[i].style.display = (!q || searchStr.indexOf(q) !== -1) ? '' : 'none';
                    }
                }, 80);
            });

            $(document).on('click', '#btnBulkSimpan', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var oldCov = $oldCoverage.val();
                var newCov = $newCoverage.val();
                var outletIds = $('.chk-outlet:checked').map(function() {
                    return $(this).val();
                }).get();

                if (!oldCov || !newCov) {
                    Swal.fire('Perhatian', 'Coverage lama dan baru wajib dipilih.', 'warning');
                    return;
                }

                if (oldCov === newCov) {
                    Swal.fire('Perhatian', 'Coverage baru harus berbeda dengan coverage lama.', 'warning');
                    return;
                }

                if (outletIds.length === 0) {
                    Swal.fire('Perhatian', 'Pilih minimal 1 outlet yang ingin dipindahkan.', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'Pindahkan ' + outletIds.length + ' outlet?',
                    text: 'Outlet yang dipilih akan dialihkan ke coverage baru.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, pindahkan!',
                    cancelButtonText: 'Batal',
                }).then(function(result) {
                    if (result.isConfirmed || result.value) {
                        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');

                        $.ajax({
                            url: '{{ route('outlet.bulk.update') }}',
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                old_coverage_id: oldCov,
                                new_coverage_id: newCov,
                                mapping_ids: outletIds,
                            },
                            success: function(res) {
                                $bulkModal.modal('hide');
                                Swal.fire('Berhasil', res.message, 'success');
                                reloadMappingTable();
                            },
                            error: function(xhr) {
                                Swal.fire('Gagal', getErrorMessage(xhr,
                                        'Data gagal dipindahkan.'),
                                    'error');
                            },
                            complete: function() {
                                $btn.prop('disabled', false).text('Pindahkan');
                            }
                        });
                    }
                });
            });

            function resetMappingModal() {
                $('#mappingId').val('');
                $outlet.val(null).trigger('change');
                $outlet.find('option').remove();
                setSelect2Value($coverage, '');
                $('#modalMappingLabel').text('Tambah Mapping');
            }

            function resetBulkModal() {
                setSelect2Value($oldCoverage, '');
                setSelect2Value($newCoverage, '');
                $('#outletCountBadge').hide().text('0 dipilih');
                $('#outletList').html(
                    '<small class="text-muted">Pilih coverage lama terlebih dahulu.</small>');
            }

            function initCoverageSelect($select, dropdownParent, placeholder, includeInactive) {
                $select.select2({
                    dropdownParent: dropdownParent,
                    placeholder: placeholder,
                    allowClear: true,
                    ajax: {
                        url: '{{ route('outlet.coverage') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            var query = {
                                q: params.term,
                                all_levels: 1,
                                exclude_prefix: '3',
                            };
                            if (includeInactive) {
                                query.include_inactive = 1;
                            }
                            return query;
                        },
                        processResults: function(data) {
                            return {
                                results: data.results
                            };
                        },
                    },
                    minimumInputLength: 2,
                    width: '100%',
                });
            }

            function setSelect2Value($select, value, text) {
                $select.find('option').remove();

                if (!value) {
                    $select.val(null).trigger('change');
                    return;
                }

                $select.append(new Option(text || value, value, true, true)).trigger('change');
            }

            function coverageText(coverage, fallback) {
                if (!coverage) {
                    return fallback || '';
                }

                var parts = [coverage.id_coverage, coverage.cov_nik];

                if (coverage.user && coverage.user.name) {
                    parts.push(coverage.user.name);
                }

                return parts.filter(Boolean).join(' - ');
            }

            function reloadMappingTable() {
                if (window.tableMapping) {
                    window.tableMapping.ajax.reload(null, false);
                }
            }

            function getErrorMessage(xhr, fallback) {
                return xhr.responseJSON && xhr.responseJSON.message ?
                    xhr.responseJSON.message :
                    fallback;
            }

            // -------------------------------------------------------------
            // Logika Modal Petakan Outlet Sellout Belum Termapping
            // -------------------------------------------------------------
            var $unmappedModal = $('#modalMapUnmapped');
            var $mapExistingMaster = $('#map_existing_id_konversi');
            var $mapCoverage = $('#map_id_coverage');

            $mapExistingMaster.select2({
                dropdownParent: $unmappedModal,
                ajax: {
                    url: '{{ route('outlet.outlet') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term };
                    },
                    processResults: function(data) {
                        return { results: data.results };
                    },
                },
                placeholder: 'Cari ID Konversi atau Nama Master...',
                minimumInputLength: 2,
                width: '100%'
            });

            initCoverageSelect($mapCoverage, $unmappedModal, 'Cari Coverage, NIK, atau Nama...', false);

            // Handler Pilihan Metode Pemetaan (Card Toggle)
            function setMapMode(mode) {
                $('#map_mode_val').val(mode);
                if (mode === 'new') {
                    $('#cardModeExisting').removeClass('active-primary');
                    $('#cardModeNew').addClass('active-success');
                    $('#sectionExistingMaster').hide();
                    $('#sectionNewMaster').slideDown(150);
                } else {
                    $('#cardModeNew').removeClass('active-success');
                    $('#cardModeExisting').addClass('active-primary');
                    $('#sectionNewMaster').hide();
                    $('#sectionExistingMaster').slideDown(150);
                }
            }

            $(document).on('click', '#cardModeExisting', function() {
                setMapMode('existing');
            });

            $(document).on('click', '#cardModeNew', function() {
                setMapMode('new');
            });

            $(document).on('click', '.btn-map-unmapped', function() {
                var rawData = $(this).attr('data-outlet');
                var outlet = {};
                try {
                    outlet = JSON.parse(rawData);
                } catch(e) {
                    console.error('JSON parse error:', e);
                }

                $('#unmapped_info_dist').text(outlet.dist || '-');
                $('#unmapped_info_cbg').text(outlet.id_cbg || '-');
                $('#unmapped_info_outlet').text(outlet.id_outlet || '-');
                $('#unmapped_info_nama').text(outlet.nama_outlet || '-');
                $('#unmapped_info_segment').text(outlet.segment_dist || '-');

                $('#map_dist').val(outlet.dist || '');
                $('#map_id_cbg').val(outlet.id_cbg || '');
                $('#map_id_outlet').val(outlet.id_outlet || '');

                // Reset mode to existing
                setMapMode('existing');
                $mapExistingMaster.val(null).trigger('change');
                $mapExistingMaster.find('option').remove();

                // Pre-fill new master fields
                var suggestedId = (outlet.dist && outlet.id_outlet) ? outlet.dist + '_' + outlet.id_outlet : (outlet.id_outlet || '');
                $('#map_new_id_konversi').val(suggestedId);
                $('#map_new_nama_outlet').val(outlet.nama_outlet || '');
                $('#map_new_kota').val('');
                $('#map_new_alamat').val('');

                // Map segment guess
                var segDist = (outlet.segment_dist || '').toUpperCase();
                var matchedSeg = '';
                if (segDist.indexOf('APOT') !== -1) matchedSeg = 'APOTEK';
                else if (segDist.indexOf('RS') !== -1 || segDist.indexOf('RUMAH SAKIT') !== -1) matchedSeg = 'RUMAH SAKIT';
                else if (segDist.indexOf('KLINIK') !== -1) matchedSeg = 'KLINIK';
                else if (segDist.indexOf('PBF') !== -1) matchedSeg = 'PBF';
                else if (segDist.indexOf('TOKO OBAT') !== -1) matchedSeg = 'TOKO OBAT';
                $('#map_new_segment').val(matchedSeg);

                setSelect2Value($mapCoverage, '');
                $('#map_pilar').val('ETHICAL');

                $unmappedModal.modal('show');
            });

            $(document).on('click', '#btnSubmitMapUnmapped', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var mode = $('#map_mode_val').val() || 'existing';
                var dist = $('#map_dist').val();
                var idCbg = $('#map_id_cbg').val();
                var idOutlet = $('#map_id_outlet').val();

                var payload = {
                    _token: '{{ csrf_token() }}',
                    dist: dist,
                    id_cbg: idCbg,
                    id_outlet: idOutlet,
                    mode: mode,
                    id_coverage: $mapCoverage.val() || '',
                    pilar: $('#map_pilar').val() || 'ETHICAL'
                };

                if (mode === 'existing') {
                    var existingId = $mapExistingMaster.val();
                    if (!existingId) {
                        Swal.fire('Perhatian', 'Silakan pilih Master Outlet (ID Konversi) yang sudah ada.', 'warning');
                        return;
                    }
                    payload.id_konversi = existingId;
                } else {
                    var newId = $('#map_new_id_konversi').val().trim();
                    var newNama = $('#map_new_nama_outlet').val().trim();
                    if (!newId) {
                        Swal.fire('Perhatian', 'ID Konversi Baru wajib diisi.', 'warning');
                        return;
                    }
                    if (!newNama) {
                        Swal.fire('Perhatian', 'Nama Outlet Master wajib diisi.', 'warning');
                        return;
                    }
                    payload.id_konversi = newId;
                    payload.nama_outlet = newNama;
                    payload.segment = $('#map_new_segment').val();
                    payload.kota = $('#map_new_kota').val();
                    payload.alamat = $('#map_new_alamat').val();
                }

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                $.ajax({
                    url: '{{ route('outlet.unmapped.map') }}',
                    type: 'POST',
                    data: payload,
                    success: function(res) {
                        $unmappedModal.modal('hide');
                        Swal.fire('Berhasil', res.message, 'success');
                        if (window.tableUnmapped) {
                            window.tableUnmapped.ajax.reload(null, false);
                        }
                        reloadMappingTable();
                        if (typeof window.updateUnmappedCounters === 'function') {
                            window.updateUnmappedCounters();
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Gagal', getErrorMessage(xhr, 'Gagal memproses pemetaan outlet.'), 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Simpan Pemetaan');
                    }
                });
            });

            // -------------------------------------------------------------
            // Logika Modal Tetapkan Coverage Pilar (Tab 3)
            // -------------------------------------------------------------
            var $assignPilarModal = $('#modalAssignPilarCoverage');
            var $assignCoverageSelect = $('#assign_id_coverage');

            initCoverageSelect($assignCoverageSelect, $assignPilarModal, 'Cari Coverage, NIK, atau Nama...', false);

            $(document).on('click', '.btn-assign-pilar', function() {
                var rawData = $(this).attr('data-outlet');
                var outlet = {};
                try {
                    outlet = JSON.parse(rawData);
                } catch(e) {
                    console.error('JSON parse error:', e);
                }

                $('#assign_info_id_konversi').text(outlet.id_konversi || '-');
                $('#assign_info_nama_outlet').text(outlet.nama_outlet || '-');
                $('#assign_info_pilar').text(outlet.pilar || '-');

                $('#assign_id_konversi').val(outlet.id_konversi || '');
                $('#assign_pilar').val(outlet.pilar || 'ETHICAL');

                setSelect2Value($assignCoverageSelect, '');
                $assignPilarModal.modal('show');
            });

            $(document).on('click', '#btnSubmitAssignPilar', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var idKonversi = $('#assign_id_konversi').val();
                var pilar = $('#assign_pilar').val();
                var idCoverage = $assignCoverageSelect.val();

                if (!idCoverage) {
                    Swal.fire('Perhatian', 'Silakan pilih Coverage / Sales Rep terlebih dahulu.', 'warning');
                    return;
                }

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                $.ajax({
                    url: '{{ route('outlet.uncovered.assign') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        id_konversi: idKonversi,
                        pilar: pilar,
                        id_coverage: idCoverage
                    },
                    success: function(res) {
                        $assignPilarModal.modal('hide');
                        Swal.fire('Berhasil', res.message, 'success');
                        if (window.tableUncovered) {
                            window.tableUncovered.ajax.reload(null, false);
                        }
                        reloadMappingTable();
                        if (typeof window.updateUnmappedCounters === 'function') {
                            window.updateUnmappedCounters();
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Gagal', getErrorMessage(xhr, 'Gagal menetapkan coverage pilar.'), 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Simpan Coverage');
                    }
                });
            });
        });
    </script>
@endpush
