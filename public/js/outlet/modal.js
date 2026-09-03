$(document).ready(function() {
    'use strict';

    const config = window.OutletPageConfig || {
        csrfToken: '',
        routes: {}
    };

    var $mappingModal = $('#modalMapping');
    var $bulkModal = $('#modalBulkUpdate');
    var $outlet = $('.select2-outlet');
    var $coverage = $('#id_coverage');
    var $oldCoverage = $('#old_coverage_id');
    var $newCoverage = $('#new_coverage_id');

    $outlet.select2({
        dropdownParent: $mappingModal,
        ajax: {
            url: config.routes.outletSelect || '/outlet/outlet-select',
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
        var showUrl = (config.routes.show || '/outlet/:id').replace(':id', id);

        $.get(showUrl, function(data) {
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
        var url = id ? (config.routes.update || '/outlet/:id').replace(':id', id) : (config.routes.store || '/outlet');
        var method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: {
                _token: config.csrfToken,
                id_outlet: $('#id_outlet').val(),
                id_coverage: $coverage.val(),
            },
            success: function(res) {
                $mappingModal.modal('hide');
                Swal.fire('Berhasil', res.message, 'success');
                reloadMappingTable();
            },
            error: function(xhr) {
                Swal.fire('Gagal', getErrorMessage(xhr, 'Data gagal disimpan.'), 'error');
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        var url = $(this).data('url') || (config.routes.destroy || '/outlet/:id').replace(':id', id);

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
                        _token: config.csrfToken
                    },
                    success: function(res) {
                        Swal.fire('Berhasil', res.message, 'success');
                        reloadMappingTable();
                    },
                    error: function(xhr) {
                        Swal.fire('Gagal', getErrorMessage(xhr, 'Data gagal dihapus.'), 'error');
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

        var byCoverageUrl = (config.routes.outletByCoverage || '/outlet/outlet-by-coverage') + '/' + encodeURIComponent(coverageId);
        $.get(byCoverageUrl, function(data) {
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
                    url: config.routes.bulkUpdate || '/outlet/bulk/update',
                    type: 'POST',
                    data: {
                        _token: config.csrfToken,
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
                        Swal.fire('Gagal', getErrorMessage(xhr, 'Data gagal dipindahkan.'), 'error');
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
        $('#outletList').html('<small class="text-muted">Pilih coverage lama terlebih dahulu.</small>');
    }

    function initCoverageSelect($select, dropdownParent, placeholder, includeInactive) {
        $select.select2({
            dropdownParent: dropdownParent,
            placeholder: placeholder,
            allowClear: true,
            ajax: {
                url: config.routes.coverageSelect || '/outlet/coverage-select',
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
            url: config.routes.outletSelect || '/outlet/outlet-select',
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
            _token: config.csrfToken,
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
            url: config.routes.unmappedMap || '/outlet/unmapped/map',
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
            url: config.routes.uncoveredAssign || '/outlet/uncovered/assign',
            type: 'POST',
            data: {
                _token: config.csrfToken,
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
