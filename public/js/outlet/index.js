$(document).ready(function() {
    'use strict';

    const config = window.OutletPageConfig || {
        routes: {}
    };

    // Inisialisasi DataTable Mapping (Tab 1)
    window.tableMapping = $('#tblMapping').DataTable({
        processing: true,
        serverSide: true,
        searchDelay: 700,
        autoWidth: false,
        ajax: config.routes.data || '/outlet/data',
        initComplete: function() {
            var api = this.api();
            var $input = $('#tblMapping_filter input');
            var timer = null;

            $input
                .off('.DT')
                .attr('placeholder', 'Min. 2 karakter')
                .on('keyup search', function(e) {
                    var value = this.value.trim();

                    if (e.type === 'keyup' && e.key === 'Enter') {
                        clearTimeout(timer);
                        if (value.length === 0 || value.length >= 2) {
                            api.search(value).draw();
                        }
                        return;
                    }

                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        if (value.length === 0 || value.length >= 2) {
                            api.search(value).draw();
                        }
                    }, 700);
                });
        },
        columns: [{
                data: 'DT_RowIndex',
                orderable: false,
                searchable: false,
                className: 'text-center'
            },
            {
                data: 'id_outlet',
                name: 'cov.id_konversi'
            },
            {
                data: 'nama_outlet',
                name: 'master.nama_outlet',
                defaultContent: '-'
            },
            {
                data: 'segment',
                name: 'master.segment',
                defaultContent: '-'
            },
            {
                data: 'pilar',
                name: 'cov.pilar',
                defaultContent: '-',
                className: 'text-center'
            },
            {
                data: 'id_coverage',
                name: 'cov.id_coverage',
                className: 'text-center'
            },
            {
                data: 'cov_nik',
                name: 'mapping.cov_nik',
                defaultContent: '-',
                className: 'text-center'
            },
            {
                data: 'user_name',
                name: 'users.name',
                defaultContent: '-'
            },
            {
                data: 'status',
                name: 'status',
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

    // Lazy initialization untuk DataTable Unmapped (Tab 2)
    window.tableUnmappedInitialized = false;

    function initUnmappedTable() {
        if (window.tableUnmappedInitialized) return;

        window.tableUnmapped = $('#tblUnmappedSellout').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 700,
            autoWidth: false,
            ajax: config.routes.unmappedData || '/outlet/unmapped/data',
            initComplete: function() {
                var api = this.api();
                var $input = $('#tblUnmappedSellout_filter input');
                var timer = null;

                $input
                    .off('.DT')
                    .attr('placeholder', 'Cari cabang/outlet...')
                    .on('keyup search', function(e) {
                        var value = this.value.trim();

                        if (e.type === 'keyup' && e.key === 'Enter') {
                            clearTimeout(timer);
                            if (value.length === 0 || value.length >= 2) {
                                api.search(value).draw();
                            }
                            return;
                        }

                        clearTimeout(timer);
                        timer = setTimeout(function() {
                            if (value.length === 0 || value.length >= 2) {
                                api.search(value).draw();
                            }
                        }, 700);
                    });
            },
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                },
                {
                    data: 'dist',
                    name: 'so.dist',
                    className: 'text-center font-weight-bolder'
                },
                {
                    data: 'id_cbg',
                    name: 'so.id_cbg'
                },
                {
                    data: 'id_outlet',
                    name: 'so.id_outlet',
                    className: 'font-weight-bold'
                },
                {
                    data: 'nama_outlet',
                    name: 'so.nama_outlet',
                    defaultContent: '-'
                },
                {
                    data: 'segment_dist',
                    name: 'so.segment_dist',
                    defaultContent: '-'
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

        window.tableUnmappedInitialized = true;
    }

    // Lazy initialization untuk DataTable Uncovered Pilar (Tab 3)
    window.tableUncoveredInitialized = false;

    function initUncoveredTable() {
        if (window.tableUncoveredInitialized) return;

        window.tableUncovered = $('#tblUncoveredPilar').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 700,
            autoWidth: false,
            ajax: config.routes.uncoveredData || '/outlet/uncovered/data',
            initComplete: function() {
                var api = this.api();
                var $input = $('#tblUncoveredPilar_filter input');
                var timer = null;

                $input
                    .off('.DT')
                    .attr('placeholder', 'Cari ID konversi/nama...')
                    .on('keyup search', function(e) {
                        var value = this.value.trim();

                        if (e.type === 'keyup' && e.key === 'Enter') {
                            clearTimeout(timer);
                            if (value.length === 0 || value.length >= 2) {
                                api.search(value).draw();
                            }
                            return;
                        }

                        clearTimeout(timer);
                        timer = setTimeout(function() {
                            if (value.length === 0 || value.length >= 2) {
                                api.search(value).draw();
                            }
                        }, 700);
                    });
            },
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                },
                {
                    data: 'id_konversi',
                    name: 'so.id_konversi',
                    className: 'font-weight-bold'
                },
                {
                    data: 'nama_outlet',
                    name: 'master.nama_outlet',
                    defaultContent: '-'
                },
                {
                    data: 'segment',
                    name: 'master.segment',
                    defaultContent: '-'
                },
                {
                    data: 'pilar',
                    name: 'pilar',
                    className: 'text-center',
                    searchable: false
                },
                {
                    data: 'total_dist_outlets',
                    name: 'total_dist_outlets',
                    className: 'text-center',
                    searchable: false
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false,
                    className: 'text-center text-nowrap',
                    width: '160px'
                },
            ]
        });

        window.tableUncoveredInitialized = true;
    }

    // Tab change event handler
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr("href");
        if (target === '#kt_tab_unmapped_sellout') {
            if (!window.tableUnmappedInitialized) {
                initUnmappedTable();
            } else if (window.tableUnmapped) {
                window.tableUnmapped.columns.adjust().draw();
            }
        } else if (target === '#kt_tab_uncovered_pilar') {
            if (!window.tableUncoveredInitialized) {
                initUncoveredTable();
            } else if (window.tableUncovered) {
                window.tableUncovered.columns.adjust().draw();
            }
        } else if (target === '#kt_tab_mapping') {
            if (window.tableMapping) {
                window.tableMapping.columns.adjust().draw();
            }
        }
    });

    $('#btnExportUnmapped').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var originalHtml = $btn.html();
        var exportUrl = config.routes.unmappedExport || '/outlet/unmapped/export';
        $btn.addClass('disabled').html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengunduh...');

        fetch(exportUrl)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Gagal mengunduh file export.');
                }
                var filename = 'Outlet_Sellout_Belum_Termapping.xlsx';
                var disposition = response.headers.get('content-disposition');
                if (disposition && disposition.indexOf('filename=') !== -1) {
                    var matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                    if (matches != null && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }
                return response.blob().then(function(blob) {
                    return {
                        blob: blob,
                        filename: filename
                    };
                });
            })
            .then(function(data) {
                var url = window.URL.createObjectURL(data.blob);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
            })
            .catch(function(err) {
                Swal.fire('Error', err.message || 'Gagal mendownload file Excel.', 'error');
            })
            .finally(function() {
                $btn.removeClass('disabled').html(originalHtml);
                if (typeof KTApp !== 'undefined' && typeof KTApp.hidePageLoading === 'function') {
                    KTApp.hidePageLoading();
                }
                $('body').removeClass('page-loading');
            });
    });

    $('#btnRefreshUnmapped').on('click', function() {
        if (window.tableUnmapped) {
            window.tableUnmapped.ajax.reload(null, false);
        }
        updateUnmappedCounters();
    });

    $('#btnRefreshUncovered').on('click', function() {
        if (window.tableUncovered) {
            window.tableUncovered.ajax.reload(null, false);
        }
        updateUnmappedCounters();
    });

    window.updateUnmappedCounters = function() {
        var statsUrl = config.routes.unmappedStats || '/outlet/unmapped/stats';
        $.get(statsUrl, function(res) {
            $('#badgeUnmappedCount').text(res.total_unmapped_outlet || 0);
            $('#badgeUncoveredCount').text(res.total_uncovered_pilar || 0);
        });
    };
});
