/**
 * dashboard/4_drilldown.js
 * Drilldown hierarki dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Drilldown = (function ($, H) {
    var route = '';
    var drillStack = [];
    var defaultLevel = 'GM';
    var drillTable = null;
    var nextLevel = null;

    function init(drillRoute, options) {
        route = drillRoute;
        options = options || {};
        defaultLevel = options.defaultLevel || 'GM';
        bindEvents();
    }

    function reset(level) {
        var startLevel = level || defaultLevel || 'GM';
        drillStack = [{ level: startLevel, parentId: '', label: startLevel }];
        loadDrillDown();
    }

    function renderBreadcrumb() {
        var html = '';
        drillStack.forEach(function (item, i) {
            var isLast = i === drillStack.length - 1;
            if (i > 0) html += '<span class="separator">/</span>';
            html += isLast
                ? '<span class="crumb active">' + item.label + '</span>'
                : '<span class="crumb" data-stack-idx="' + i + '">' + item.label + '</span>';
        });
        $('#drillPath').html(html);
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function loadDrillDown() {
        var cur = drillStack[drillStack.length - 1];

        renderBreadcrumb();

        if (drillTable) {
            drillTable.destroy();
            drillTable = null;
        }

        $('#drilldownTable').html(
            '<div class="table-responsive">' +
                '<table id="tblDrilldown" class="table table-hover table-sm align-middle mb-0 w-100">' +
                '<thead class="thead-light"><tr>' +
                '<th>#</th><th>ID Coverage</th><th>Deskripsi</th>' +
                '<th>NIK</th><th>Nama</th><th>Pilar</th>' +
                '<th class="text-right">Outlet</th>' +
                '<th class="text-right">Transaksi</th>' +
                '<th class="text-right">Netto</th>' +
                '<th>Lihat Bawahan</th>' +
                '</tr></thead><tbody></tbody></table></div>'
        );

        drillTable = $('#tblDrilldown').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            ajax: {
                url: route,
                data: function (d) {
                    return $.extend({}, d, H.getFilters(), {
                        level: cur.level,
                        coverage_id: cur.parentId,
                    });
                },
                dataSrc: function (res) {
                    nextLevel = res.next_level || null;
                    return res.data || [];
                },
            },
            columns: [
                {
                    data: null,
                    render: function (d, t, r, m) {
                        return m.row + m.settings._iDisplayStart + 1;
                    },
                    orderable: false,
                },
                {
                    data: 'coverage_id',
                    render: function (value, type, row) {
                        var coverageId = value || 'UNMAPPED';
                        var levelName = row.coverage_level_name || row.coverage_level || '-';
                        var isUnmapped = coverageId === 'UNMAPPED' || row.is_unmapped == 1;
                        var badgeClass = isUnmapped ? 'badge-danger' : 'badge-primary';

                        return '<span class="badge ' + badgeClass + '">' + escapeHtml(levelName) + '</span> ' +
                            '<code>' + escapeHtml(coverageId) + '</code>';
                    },
                },
                { data: 'coverage_desc', defaultContent: '-' },
                { data: 'coverage_nik', defaultContent: '-' },
                { data: 'user_name', defaultContent: '-' },
                {
                    data: 'coverage_pilar',
                    render: function (value) {
                        return '<span class="badge badge-light">' + escapeHtml(value || 'N/A') + '</span>';
                    },
                },
                {
                    data: 'total_outlet',
                    className: 'text-right',
                    render: function (value) {
                        return H.formatInt(value);
                    },
                },
                {
                    data: 'total_transaksi',
                    className: 'text-right',
                    render: function (value) {
                        return H.formatInt(value);
                    },
                },
                {
                    data: 'total_netto',
                    className: 'text-right',
                    render: function (value) {
                        return '<strong>' + H.formatRupiah(value) + '</strong>';
                    },
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (value, type, row) {
                        var coverageId = row.coverage_id || 'UNMAPPED';
                        var coverageDesc = row.coverage_desc || '-';
                        var drillLabel = coverageDesc !== '-' ? coverageDesc : coverageId;
                        var isUnmapped = coverageId === 'UNMAPPED' || row.is_unmapped == 1;

                        if (!nextLevel || isUnmapped) {
                            return '<span class="text-muted small">N/A</span>';
                        }

                        return '<button class="btn btn-xs btn-light-primary btn-drill-next" ' +
                            'data-next-level="' + escapeHtml(nextLevel) + '" ' +
                            'data-parent-id="' + escapeHtml(coverageId) + '" ' +
                            'data-label="' + escapeHtml(drillLabel) + '">' +
                            escapeHtml(nextLevel) + ' <i class="fas fa-chevron-right fa-xs"></i>' +
                            '</button>';
                    },
                },
            ],
            rowCallback: function (row, data) {
                $(row).toggleClass('table-warning', data.is_unmapped == 1);
            },
            order: [[8, 'desc']],
        });
    }

    function bindEvents() {
        $(document).on('click', '.btn-drill-level', function () {
            var level = $(this).data('level');
            drillStack = [{ level: level, parentId: '', label: level }];
            $('.btn-drill-level').removeClass('active');
            $(this).addClass('active');
            loadDrillDown();
        });

        $(document).on('click', '.btn-drill-next', function () {
            drillStack.push({
                level: $(this).data('next-level'),
                parentId: $(this).data('parent-id'),
                label: $(this).data('label'),
            });
            loadDrillDown();
            $('html,body').animate({ scrollTop: $('#drilldownTable').offset().top - 100 }, 300);
        });

        $(document).on('click', '#drillPath .crumb[data-stack-idx]', function () {
            var idx = parseInt($(this).data('stack-idx'), 10);
            drillStack = drillStack.slice(0, idx + 1);
            loadDrillDown();
        });
    }

    return {
        init: init,
        reset: reset
    };
})(jQuery, Dashboard.Helpers);
