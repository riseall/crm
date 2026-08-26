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
        });
    </script>
@endpush
