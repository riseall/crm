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

    function formatNNName(covDesc, level) {
        if (!covDesc) return "NN";
        covDesc = String(covDesc).trim();
        if (/^NN/i.test(covDesc)) return covDesc;

        // Pola: PILAR-CBG<NOMOR> (contoh: ETHICAL-DPS2 -> NN2 MR ETHICAL DPS, OGB-DPS1 -> NN1 MR OGB DPS)
        var match = covDesc.match(/^([A-Za-z]+)[-_]([A-Za-z]+)(\d*)$/);
        if (match) {
            var pilar = match[1].toUpperCase();
            var cbg = match[2].toUpperCase();
            var num = match[3] || "";
            var lvl = level || "MR";
            return "NN" + num + " " + lvl + " " + pilar + " " + cbg;
        }

        return "NN - " + covDesc;
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
                {
                    data: "nik",
                    render: function (v, t, r) {
                        var isUnmapped = r.is_unmapped === 1 || !r.coverage_id || r.coverage_id === "UNMAPPED";
                        if (isUnmapped) {
                            return '<span class="badge badge-warning font-weight-bold">UNMAPPED</span>';
                        }
                        if (!v || v === "NN" || v === "-") {
                            return r.coverage_id || "-";
                        }
                        return v;
                    },
                },
                {
                    data: "name",
                    render: function (v, t, r) {
                        var isUnmapped = r.is_unmapped === 1 || !r.coverage_id || r.coverage_id === "UNMAPPED";
                        if (isUnmapped) {
                            return '<span class="text-danger font-italic font-weight-bold">' + (r.cov_desc || 'Belum Termapping') + '</span>';
                        }
                        if (!v || v === "NN" || v === "N/A") {
                            var displayName = formatNNName(r.cov_desc, r.level_name);
                            return '<span class="text-danger font-italic font-weight-bold">' + displayName + '</span>';
                        }
                        return v;
                    },
                },
                {
                    data: "level_name",
                    render: function (v) {
                        return H.formatBadge(v);
                    },
                },
                { data: "pilar" },
                { data: "divisi", defaultContent: "-" },
                {
                    data: "target_top",
                    className: "text-right text-nowrap",
                    render: function (v) {
                        return H.formatRupiah(v);
                    },
                },
                {
                    data: "total_netto",
                    className: "text-right text-nowrap",
                    render: function (v) {
                        return "<strong>" + H.formatRupiah(v) + "</strong>";
                    },
                },
                {
                    data: "achievement",
                    className: "text-center text-nowrap",
                    render: function (v, t, r) {
                        var target = parseFloat(r.target_top) || 0;
                        if (target <= 0) {
                            return '<span class="badge badge-light text-muted">N/A</span>';
                        }
                        var ach = parseFloat(v) || 0;
                        var badgeClass =
                            ach >= 100
                                ? "badge-light-success text-success"
                                : ach >= 70
                                ? "badge-light-warning text-warning"
                                : "badge-light-danger text-danger";
                        var formatted =
                            ach.toLocaleString("id-ID", {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1,
                            }) + "%";
                        return (
                            '<span class="badge font-weight-bolder ' +
                            badgeClass +
                            '">' +
                            formatted +
                            "</span>"
                        );
                    },
                },
                {
                    data: "nik",
                    orderable: false,
                    searchable: false,
                    className: "text-center text-nowrap",
                    render: function (data, type, row) {
                        let btns = [];
                        if (row.is_unmapped === 0 && row.nik) {
                            let detailUrl =
                                window.DashboardPage.routes.detailAnggota.replace(
                                    ":nik",
                                    row.nik,
                                );

                            btns.push(
                                `<a href="${detailUrl}" target="_blank" class="btn btn-icon btn-sm btn-outline-primary font-weight-bolder mr-1" title="Lihat Detail Outlet"><i class="fas fa-store-alt"></i></a>`
                            );
                        }

                        if (row.nik) {
                            let visitsUrl =
                                (window.DashboardPage &&
                                    window.DashboardPage.routes &&
                                    window.DashboardPage.routes.visitsDetail)
                                    ? window.DashboardPage.routes.visitsDetail.replace(
                                          ":nik",
                                          encodeURIComponent(row.nik)
                                      )
                                    : "/visits?nik=" + encodeURIComponent(row.nik);

                            btns.push(
                                `<a href="${visitsUrl}" target="_blank" class="btn btn-icon btn-sm btn-outline-info font-weight-bolder" title="Lihat detail kunjungan ${row.name || ''}"><i class="fas fa-map-marked-alt"></i></a>`
                            );
                        }

                        if (row.is_unmapped === 1 || !row.nik) {
                            let historyUrl =
                                (window.DashboardPage &&
                                    window.DashboardPage.routes &&
                                    window.DashboardPage.routes.outletHistory)
                                    ? window.DashboardPage.routes.outletHistory
                                    : "/outlet/history";

                            let params = [];
                            let filters = (H && typeof H.getFilters === "function") ? H.getFilters() : {};
                            if (filters.start_date) params.push("start_date=" + encodeURIComponent(filters.start_date));
                            if (filters.end_date) params.push("end_date=" + encodeURIComponent(filters.end_date));
                            let covId = row.coverage_id || "UNMAPPED";
                            params.push("coverage_id=" + encodeURIComponent(covId));
                            let fullHistoryUrl = historyUrl + (params.length ? "?" + params.join("&") : "");

                            btns.push(
                                `<a href="${fullHistoryUrl}" target="_blank" class="btn btn-icon btn-sm btn-outline-warning font-weight-bolder" title="Lihat Riwayat Outlet & Transaksi SO Unmapped"><i class="fas fa-exclamation-triangle"></i></a>`
                            );
                        }

                        return btns.length
                            ? btns.join("")
                            : '<span class="text-muted small">N/A</span>';
                    },
                },
            ],
            rowCallback: function (row, data) {
                var isUnmapped = data.is_unmapped === 1 || !data.coverage_id || data.coverage_id === "UNMAPPED";
                $(row).toggleClass("table-warning", isUnmapped);
            },
            order: [[7, "desc"]],
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
