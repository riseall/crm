/**
 * dashboard/5_datatable.js
 * DataTable Detail Achievement (Fokus Level MR & Produk PM).
 */
var Dashboard = Dashboard || {};

Dashboard.Tables = (function ($, H) {
    var dtInstance = null;
    var currentRoutes = null;
    var currentMode = "rm";
    var tableEl = null;
    var isInitialized = false;

    function init(routes) {
        currentRoutes = routes || {};
        tableEl = $("#tblAchievement");
        if (!tableEl.length) {
            tableEl = $("#tblAnggota");
        }
        if (!tableEl.length) return;

        var collapseEl = tableEl.closest(".collapse");
        if (collapseEl.length && !collapseEl.hasClass("show")) {
            collapseEl.one("show.bs.collapse", function () {
                setup();
            });
        } else {
            setup();
        }
    }

    function setup() {
        if (isInitialized) return;
        isInitialized = true;

        var activeRadio = $("#tableModeToggle input[name='table_mode']:checked");
        if (activeRadio.length) {
            currentMode = activeRadio.val();
        }

        bindEvents();
        initDataTable();
    }

    function bindEvents() {
        $("#tableModeToggle input[name='table_mode']").on("change", function () {
            var mode = $(this).val();
            setMode(mode, true);
        });
    }

    function setMode(mode, syncChart) {
        if (!mode || mode === currentMode && dtInstance) return;
        currentMode = mode;

        var radio = $("#tableModeToggle input[value='" + mode + "']");
        if (radio.length) {
            radio.prop("checked", true);
            radio.closest(".btn-group").find("label").removeClass("active");
            radio.closest("label").addClass("active");
        }

        if (syncChart && Dashboard.Charts && typeof Dashboard.Charts.setRaceMode === "function") {
            Dashboard.Charts.setRaceMode(mode, false);
        }

        initDataTable();
    }

    function renderAchievementBadge(val, top) {
        var target = parseFloat(top) || 0;
        if (target <= 0) {
            return '<span class="badge badge-light text-muted">0,0%</span>';
        }
        var ach = parseFloat(val) || 0;
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
        return '<span class="badge font-weight-bolder ' + badgeClass + '">' + formatted + '</span>';
    }

    function renderActionButtons(row) {
        var btns = [];
        var detailBase = window.DashboardPage && window.DashboardPage.routes ? window.DashboardPage.routes.detailAnggota : null;
        var visitsBase = window.DashboardPage && window.DashboardPage.routes ? window.DashboardPage.routes.visitsDetail : null;
        var historyBase = window.DashboardPage && window.DashboardPage.routes ? window.DashboardPage.routes.outletHistory : null;

        if (row.is_unmapped === 0 && row.nik) {
            var detailUrl = detailBase ? detailBase.replace(":nik", encodeURIComponent(row.nik)) : "/dashboard/anggota/" + encodeURIComponent(row.nik);
            btns.push(
                '<a href="' + detailUrl + '" class="btn btn-icon btn-sm btn-outline-primary font-weight-bolder mr-1" title="Detail Selling Out">' +
                    '<i class="fas fa-store-alt"></i>' +
                '</a>'
            );
        }

        if (row.nik) {
            var visitsUrl = visitsBase ? visitsBase.replace(":nik", encodeURIComponent(row.nik)) : "/visits?nik=" + encodeURIComponent(row.nik);
            btns.push(
                '<a href="' + visitsUrl + '" class="btn btn-icon btn-sm btn-outline-info font-weight-bolder mr-1" title="Lihat detail kunjungan ' + (row.mr || "") + '">' +
                    '<i class="fas fa-map-marked-alt"></i>' +
                '</a>'
            );
        }

        if (row.is_unmapped === 1 || !row.nik) {
            var historyUrl = historyBase || "/outlet/history";
            var params = [];
            var filters = (H && typeof H.getFilters === "function") ? H.getFilters() : {};
            if (filters.start_date) params.push("start_date=" + encodeURIComponent(filters.start_date));
            if (filters.end_date) params.push("end_date=" + encodeURIComponent(filters.end_date));
            var covId = row.coverage_id || "UNMAPPED";
            params.push("coverage_id=" + encodeURIComponent(covId));
            var fullHistoryUrl = historyUrl + (params.length ? "?" + params.join("&") : "");

            btns.push(
                '<a href="' + fullHistoryUrl + '" class="btn btn-icon btn-sm btn-outline-warning font-weight-bolder" title="Lihat Riwayat Outlet & Transaksi SO Unmapped">' +
                    '<i class="fas fa-exclamation-triangle"></i>' +
                '</a>'
            );
        }

        return btns.length ? btns.join("") : '<span class="text-muted small">-</span>';
    }

    function initDataTable() {
        if (!tableEl || !tableEl.length) return;

        if ($.fn.DataTable.isDataTable(tableEl)) {
            dtInstance.destroy();
            tableEl.empty();
        }

        var isPm = (currentMode === "pm");
        var columns = [];
        var orderColIndex = 4;

        if (isPm) {
            tableEl.html([
                '<thead class="text-center">',
                    '<tr>',
                        '<th>PM</th>',
                        '<th>Produk</th>',
                        '<th class="text-right">Realisasi</th>',
                        '<th class="text-right">TOP</th>',
                        '<th class="text-center">Achievement</th>',
                        '<th class="text-center">Aksi</th>',
                    '</tr>',
                '</thead>',
                '<tbody></tbody>',
                '<tfoot>',
                    '<tr>',
                        '<th colspan="2" class="text-right font-weight-bold">TOTAL</th>',
                        '<th class="text-right font-weight-bold" id="totalRealisasi">Rp 0</th>',
                        '<th class="text-right font-weight-bold" id="totalTop">Rp 0</th>',
                        '<th class="text-center font-weight-bold" id="totalAch">0,0%</th>',
                        '<th></th>',
                    '</tr>',
                '</tfoot>',
            ].join(""));

            columns = [
                { data: "pm", defaultContent: "-" },
                { data: "produk", defaultContent: "-" },
                {
                    data: "realisasi",
                    className: "text-right text-nowrap",
                    render: function (v) {
                        return "<strong>" + H.formatRupiah(v) + "</strong>";
                    },
                },
                {
                    data: "top",
                    className: "text-right text-nowrap",
                    render: function (v) {
                        return H.formatRupiah(v);
                    },
                },
                {
                    data: "achievement",
                    className: "text-center text-nowrap",
                    render: function (v, t, r) {
                        return renderAchievementBadge(v, r.top);
                    },
                },
                {
                    data: "sku",
                    orderable: false,
                    searchable: false,
                    className: "text-center text-nowrap",
                    render: function () {
                        return '<span class="text-muted small">-</span>';
                    },
                },
            ];
            orderColIndex = 2;
        } else {
            tableEl.html([
                '<thead class="text-center">',
                    '<tr>',
                        '<th>RM</th>',
                        '<th>SAM</th>',
                        '<th>AM</th>',
                        '<th>MR</th>',
                        '<th class="text-right">Realisasi</th>',
                        '<th class="text-right">TOP</th>',
                        '<th class="text-center">Achievement</th>',
                        '<th class="text-center">Aksi</th>',
                    '</tr>',
                '</thead>',
                '<tbody></tbody>',
                '<tfoot>',
                    '<tr>',
                        '<th colspan="4" class="text-right font-weight-bold">TOTAL</th>',
                        '<th class="text-right font-weight-bold" id="totalRealisasi">Rp 0</th>',
                        '<th class="text-right font-weight-bold" id="totalTop">Rp 0</th>',
                        '<th class="text-center font-weight-bold" id="totalAch">0,0%</th>',
                        '<th></th>',
                    '</tr>',
                '</tfoot>',
            ].join(""));

            columns = [
                { data: "rm", defaultContent: "-" },
                { data: "sam", defaultContent: "-" },
                { data: "am", defaultContent: "-" },
                {
                    data: "mr",
                    render: function (v, t, r) {
                        if (r.is_unmapped === 1) {
                            return '<span class="badge badge-warning mr-1">UNMAPPED</span><span class="text-danger font-italic font-weight-bold">' + (v || "Data Belum Termapping") + '</span>';
                        }
                        if (!r.nik) {
                            return '<span class="text-danger font-italic font-weight-bold">' + (v || "NN") + '</span>';
                        }
                        return v || "-";
                    },
                },
                {
                    data: "realisasi",
                    className: "text-right text-nowrap",
                    render: function (v) {
                        return "<strong>" + H.formatRupiah(v) + "</strong>";
                    },
                },
                {
                    data: "top",
                    className: "text-right text-nowrap",
                    render: function (v) {
                        return H.formatRupiah(v);
                    },
                },
                {
                    data: "achievement",
                    className: "text-center text-nowrap",
                    render: function (v, t, r) {
                        return renderAchievementBadge(v, r.top);
                    },
                },
                {
                    data: "nik",
                    orderable: false,
                    searchable: false,
                    className: "text-center text-nowrap",
                    render: function (d, t, r) {
                        return renderActionButtons(r);
                    },
                },
            ];
            orderColIndex = 4;
        }

        var ajaxUrl = (currentRoutes && currentRoutes.tableAchievement)
            ? currentRoutes.tableAchievement
            : ((window.DashboardPage && window.DashboardPage.routes) ? window.DashboardPage.routes.tableAchievement : "/dashboard/table/achievement");

        dtInstance = tableEl.DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            ajax: {
                url: ajaxUrl,
                data: function (d) {
                    return $.extend({}, d, H.getFilters(), { mode: currentMode });
                },
            },
            columns: columns,
            order: [[orderColIndex, "desc"]],
            rowCallback: function (row, data) {
                $(row).toggleClass("table-warning", data.is_unmapped === 1);
            },
            footerCallback: function () {
                var json = this.api().ajax.json();
                if (json && json.totals) {
                    var totReal = json.totals.realisasi || 0;
                    var totTop = json.totals.top || 0;
                    var totAch = json.totals.achievement || 0;

                    $("#totalRealisasi").text(H.formatRupiah(totReal));
                    $("#totalTop").text(H.formatRupiah(totTop));
                    var formattedAch = totAch.toLocaleString("id-ID", {
                        minimumFractionDigits: 1,
                        maximumFractionDigits: 1,
                    }) + "%";
                    $("#totalAch").text(formattedAch);
                }
            },
        });
    }

    function search(query) {
        query = query || "";
        if (dtInstance) {
            dtInstance.search(query).draw();
            var filterInput = tableEl.closest(".dataTables_wrapper").find(".dataTables_filter input");
            if (filterInput.length) {
                filterInput.val(query);
            }
        }
    }

    function getSearch() {
        return dtInstance ? dtInstance.search() : "";
    }

    function reloadAll() {
        if (dtInstance) {
            dtInstance.ajax.reload(null, false);
        }
    }

    return {
        init: init,
        reloadAll: reloadAll,
        setMode: setMode,
        search: search,
        getSearch: getSearch,
    };
})(jQuery, Dashboard.Helpers);
