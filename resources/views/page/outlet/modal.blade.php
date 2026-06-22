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
        <label>Pilih Outlet yang Dipindah <span class="text-danger">*</span></label>
        <div id="outletList" class="border p-3 rounded">
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

            initCoverageSelect($coverage, $mappingModal, 'Cari Coverage, NIK, atau Nama...');
            initCoverageSelect($oldCoverage, $bulkModal, 'Cari Coverage Lama...');
            initCoverageSelect($newCoverage, $bulkModal, 'Cari Coverage Baru...');

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

                if (!coverageId) {
                    $('#outletList').html(
                        '<small class="text-muted">Pilih coverage lama terlebih dahulu.</small>');
                    return;
                }

                $.get('{{ url('outlet/outlet-by-coverage') }}/' + coverageId, function(data) {
                    if (!data.length) {
                        $('#outletList').html(
                            '<small class="text-muted">Tidak ada outlet untuk coverage ini.</small>'
                            );
                        return;
                    }

                    var html =
                        '<div class="mb-2"><label><input type="checkbox" id="checkAll"> <strong>Pilih Semua</strong></label></div>';
                    $.each(data, function(i, item) {
                        var badge = item.needs_replacement ?
                            ' <span class="badge badge-warning">Butuh Pengganti</span>' :
                            '';
                        html +=
                            '<div><label><input type="checkbox" class="chk-outlet" value="' +
                            item.id + '"> ' + item.id_outlet + badge + '</label></div>';
                    });

                    $('#outletList').html(html);
                });
            });

            $(document).on('change', '#checkAll', function() {
                $('.chk-outlet').prop('checked', $(this).is(':checked'));
            });

            $(document).on('click', '#btnBulkSimpan', function(e) {
                e.preventDefault();
                var outletIds = $('.chk-outlet:checked').map(function() {
                    return $(this).val();
                }).get();

                if (!$oldCoverage.val() || !$newCoverage.val()) {
                    Swal.fire('Perhatian', 'Coverage lama dan baru wajib dipilih.', 'warning');
                    return;
                }

                if (outletIds.length === 0) {
                    Swal.fire('Perhatian', 'Pilih minimal 1 outlet.', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'Pindahkan ' + outletIds.length + ' outlet?',
                    text: 'Outlet akan dipindahkan ke coverage baru.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, pindahkan!',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('outlet.bulk.update') }}',
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                old_coverage_id: $oldCoverage.val(),
                                new_coverage_id: $newCoverage.val(),
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
                $('#outletList').html(
                    '<small class="text-muted">Pilih coverage lama terlebih dahulu.</small>');
            }

            function initCoverageSelect($select, dropdownParent, placeholder) {
                $select.select2({
                    dropdownParent: dropdownParent,
                    placeholder: placeholder,
                    allowClear: true,
                    ajax: {
                        url: '{{ route('outlet.coverage') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term,
                                all_levels: 1,
                                exclude_prefix: '3',
                            };
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
