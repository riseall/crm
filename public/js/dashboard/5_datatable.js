/**
 * dashboard/5_datatable.js
 * DataTable dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Tables = (function ($, H) {
    var tblAnggota = null;
    var currentRoutes = null;
    var isInitialized = false;

    function init(routes) {
        currentRoutes = routes;
        if (!$("#tblAnggota").length) return;

        var collapseEl = $("#tblAnggota_collapse");
        if (collapseEl.length && !collapseEl.hasClass("show")) {
            collapseEl.one("show.bs.collapse", function () {
                initTable();
            });
        } else {
            initTable();
        }
    }

    function initTable() {
        if (isInitialized) return;
        isInitialized = true;

        tblAnggota = $("#tblAnggota").DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            ajax: {
                url: currentRoutes.tableAnggota,
                data: function (d) {
                    return $.extend({}, d, H.getFilters());
                },
            },
            columns: [
                {
                    data: null,
                    render: function (d, t, r, m) {
                        return m.row + m.settings._iDisplayStart + 1;
                    },
                    orderable: false,
                    className: "text-center",
                },
                { data: "nik" },
                { data: "name" },
                {
                    data: "level_name",
                    render: function (v) {
                        return H.formatBadge(v);
                    },
                },
                { data: "pilar" },
                { data: "divisi", defaultContent: "-" },
                // { data: "cov_desc", defaultContent: "-" },

                {
                    data: "total_netto",
                    render: function (v) {
                        return "<strong>" + H.formatRupiah(v) + "</strong>";
                    },
                },
                {
                    data: "nik",
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function (data, type, row) {
                        if (row.is_unmapped === 0 && row.nik) {
                            let detailUrl =
                                window.DashboardPage.routes.detailAnggota.replace(
                                    ":nik",
                                    row.nik,
                                );

                            return `<a href="${detailUrl}" class="btn btn-sm btn-light-primary font-weight-bolder" title="Lihat Detail Outlet">
                        <i class="fas fa-eye"></i>
                    </a>`;
                        }

                        return '<span class="text-muted small">N/A</span>';
                    },
                },
            ],
            order: [[6, "desc"]],
        });
    }

    function reloadAll() {
        if (tblAnggota) tblAnggota.ajax.reload(null, false);
    }

    return {
        init: init,
        reloadAll: reloadAll,
    };
})(jQuery, Dashboard.Helpers);
