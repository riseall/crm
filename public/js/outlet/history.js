/**
 * outlet/history.js
 * DataTable untuk halaman history outlet.
 */
$(document).ready(function () {
    var page = window.OutletHistoryPage || {};
    var routes = page.routes || {};

    var $startDate = $("#filter_start_date");
    var $endDate = $("#filter_end_date");
    var $coverage = $("#filter_coverage_id");
    var $outlet = $("#filter_outlet_id");
    var selectedCoverageNik = "";

    var urlParams = new URLSearchParams(window.location.search);
    var initStartDate = urlParams.get("start_date");
    var initEndDate = urlParams.get("end_date");
    var initCoverageId = urlParams.get("coverage_id");
    var initUnmapped = urlParams.get("unmapped");
    var initLevel = urlParams.get("level");
    var initPilar = urlParams.getAll("pilar[]");
    if (!initPilar.length && urlParams.get("pilar")) {
        initPilar = [urlParams.get("pilar")];
    }

    if (initStartDate && $startDate.length) {
        $startDate.val(initStartDate);
    }
    if (initEndDate && $endDate.length) {
        $endDate.val(initEndDate);
    }
    if (initStartDate && initEndDate && $("#filter_period").length && window.moment) {
        var startMom = moment(initStartDate, "YYYY-MM-DD");
        var endMom = moment(initEndDate, "YYYY-MM-DD");
        if (startMom.isValid() && endMom.isValid()) {
            $("#filter_period").val(startMom.format("DD MMM YYYY") + " - " + endMom.format("DD MMM YYYY"));
        }
    }

    if (!$("#tblHistoryOutlet").length) {
        return;
    }

    if ($.fn.select2) {
        if (initCoverageId && initCoverageId !== "UNMAPPED" && $coverage.length) {
            var opt = new Option(initCoverageId, initCoverageId, true, true);
            $coverage.append(opt);
        }
        $coverage
            .select2({
                placeholder: "Cari PIC...",
                allowClear: true,
                ajax: {
                    url: routes.coverage,
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results,
                        };
                    },
                },
                minimumInputLength: 2,
                width: "100%",
            })
            .on("select2:select", function (e) {
                selectedCoverageNik =
                    (e.params.data && e.params.data.cov_nik) || "";
            })
            .on("select2:clear select2:unselect", function () {
                selectedCoverageNik = "";
            });

        $outlet.select2({
            placeholder: "Cari Outlet...",
            allowClear: true,
            ajax: {
                url: routes.outlet,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results,
                    };
                },
            },
            minimumInputLength: 2,
            width: "100%",
        });
    }

    function isUnmappedValue(val) {
        var str = String(val || "").trim().toLowerCase();
        return !str || str === "-" || str === "n/a" || str === "unmapped" || str === "null";
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

    var table = $("#tblHistoryOutlet").DataTable({
        processing: true,
        serverSide: true,
        // autoWidth: false,
        responsive: true,
        scrollX: true,
        searchDelay: 700,
        ajax: {
            url: routes.data,
            data: function (d) {
                return $.extend({}, d, {
                    start_date: $startDate.val(),
                    end_date: $endDate.val(),
                    coverage_id: $coverage.val() || (initCoverageId === "UNMAPPED" ? "UNMAPPED" : ""),
                    coverage_nik: selectedCoverageNik,
                    outlet_id: $outlet.val(),
                });
            },
        },
        columns: [
            {
                data: "DT_RowIndex",
                orderable: false,
                searchable: false,
                className: "text-center",
            },
            {
                data: "bulan",
                name: "bulan",
                searchable: false,
                className: "text-center",
            },
            { data: "dist", name: "so.dist", searchable: false },
            { data: "id_outlet", name: "so.id_outlet", defaultContent: "-", searchable: false },
            { data: "id_cbg", name: "so.id_cbg", defaultContent: "-", searchable: false },
            { data: "outlet_name", name: "so.outlet_name", searchable: false },
            {
                data: "coverage_nik",
                name: "so.coverage_nik",
                searchable: false,
                className: "text-center",
                render: function (data, type, row) {
                    var isUnmapped = !row.coverage_id || row.coverage_id === "UNMAPPED";
                    if (isUnmapped) {
                        return '<span class="text-muted font-weight-bold">Unmapped</span>';
                    }
                    if (isUnmappedValue(data) || data === "NN") {
                        return row.coverage_id || "-";
                    }
                    return data;
                },
            },
            {
                data: "user_name",
                name: "so.user_name",
                searchable: false,
                className: "text-center",
                render: function (data, type, row) {
                    var isUnmapped = !row.coverage_id || row.coverage_id === "UNMAPPED";
                    if (isUnmapped) {
                        return '<span class="text-muted font-weight-bold">Unmapped</span>';
                    }
                    if (isUnmappedValue(data) || data === "NN") {
                        var levelName = row.coverage_level_name || (row.coverage_level == 5 ? 'MR' : (row.coverage_level == 4 ? 'AM' : (row.coverage_level == 3 ? 'SAM' : (row.coverage_level == 2 ? 'RM' : 'MR'))));
                        var displayName = formatNNName(row.coverage_desc, levelName);
                        return '<span class="text-danger font-italic font-weight-bold">' + displayName + '</span>';
                    }
                    return data;
                },
            },
            {
                data: "product_pilar",
                name: "so.product_pilar",
                searchable: false,
                className: "text-center",
            },
            {
                data: "total_netto",
                name: "total_netto",
                searchable: false,
                className: "text-center font-weight-bolder",
                render: function (data) {
                    return (
                        "Rp " +
                        parseFloat(data || 0).toLocaleString("id-ID")
                    );
                },
            },
        ],
        rowCallback: function (row, data) {
            var isUnmapped = !data.coverage_id || data.coverage_id === "UNMAPPED";
            $(row).toggleClass("table-warning", isUnmapped);
        },
        drawCallback: function (settings) {
            var json = (settings && settings.json) ? settings.json : (table ? table.ajax.json() : null);
            var grandTotal = (json && json.grand_total_netto !== undefined) ? json.grand_total_netto : 0;
            var totalFormatted = "Rp " + parseFloat(grandTotal || 0).toLocaleString("id-ID");
            $("#totalNettoDisplay").text(totalFormatted);
        },
        order: [[9, "desc"]],
    });

    $("#btnApplyHistoryFilter").on("click", function () {
        table.ajax.reload(null, false);
    });

    window.addEventListener("filter:reset", (event) => {
        if (
            event.detail &&
            event.detail.id &&
            event.detail.id !== "btnResetHistoryFilter"
        )
            return;

        // Reset select2 coverage & outlet ke nilai kosong, lalu reload tabel.
        selectedCoverageNik = "";
        initCoverageId = "";
        initUnmapped = "";
        initLevel = "";
        initPilar = [];
        if ($.fn.select2) {
            $coverage.val(null).trigger("change");
            $outlet.val(null).trigger("change");
        }
        $("#infoFilterSummary").empty();
        table.search("").columns().search("").draw();
        table.ajax.reload(null, false);
    });
});
