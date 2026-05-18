/**
 * dashboard/4_drilldown.js
 * Drilldown hierarki dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Drilldown = (function ($, H) {
    var route = '';
    var drillStack = [];
    var defaultLevel = 'GM';

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

    function loadDrillDown() {
        var cur = drillStack[drillStack.length - 1];
        var params = $.extend({}, H.getFilters(), {
            level: cur.level,
            coverage_id: cur.parentId,
        });

        $('#drilldownTable').html(H.loadingSpinner());
        renderBreadcrumb();

        $.get(route, params).done(function (res) {
            if (!res.data || !res.data.length) {
                $('#drilldownTable').html('<div class="alert alert-info mb-0">Tidak ada data untuk level ini.</div>');
                return;
            }

            var html = '<div class="table-responsive">' +
                '<table class="table table-hover table-sm align-middle mb-0">' +
                '<thead class="thead-light"><tr>' +
                '<th>#</th><th>ID Coverage</th><th>Deskripsi</th>' +
                '<th>NIK</th><th>Nama</th><th>Pilar</th>' +
                '<th class="text-right">Outlet</th>' +
                '<th class="text-right">Transaksi</th>' +
                '<th class="text-right">Netto</th>';
            if (res.next_level) html += '<th>Lihat Bawahan</th>';
            html += '</tr></thead><tbody>';

            res.data.forEach(function (row, i) {
                var levelName = row.cov_level_name || row.cov_level;
                var isUnmapped = row.id_coverage === 'UNMAPPED' || row.is_unmapped == 1;
                var rowClass = isUnmapped ? 'table-warning' : '';
                var badgeClass = isUnmapped ? 'badge-danger' : 'badge-primary';

                html += '<tr class="' + rowClass + '">' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td><span class="badge ' + badgeClass + '">' + levelName + '</span> <code>' + row.id_coverage + '</code></td>' +
                    '<td>' + (row.cov_desc || '-') + '</td>' +
                    '<td>' + row.cov_nik + '</td>' +
                    '<td>' + (row.name || '-') + '</td>' +
                    '<td><span class="badge badge-light">' + row.cov_pilar + '</span></td>' +
                    '<td class="text-right">' + H.formatInt(row.total_outlet) + '</td>' +
                    '<td class="text-right">' + H.formatInt(row.total_transaksi) + '</td>' +
                    '<td class="text-right"><strong>' + H.formatRupiah(row.total_netto) + '</strong></td>';

                if (res.next_level) {
                    html += '<td>';
                    if (!isUnmapped) {
                        html += '<button class="btn btn-xs btn-light-primary btn-drill-next" ' +
                            'data-next-level="' + res.next_level + '" ' +
                            'data-parent-id="' + row.id_coverage + '" ' +
                            'data-label="' + (row.cov_desc || row.id_coverage) + '">' +
                            res.next_level + ' <i class="fas fa-chevron-right fa-xs"></i>' +
                            '</button>';
                    } else {
                        html += '<span class="text-muted small">N/A</span>';
                    }
                    html += '</td>';
                }

                html += '</tr>';
            });

            html += '</tbody></table></div>';
            $('#drilldownTable').html(html);
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
