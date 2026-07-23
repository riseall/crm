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

    if (!$("#tblHistoryOutlet").length) {
        return;
    }

    if ($.fn.select2) {
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

    var table = $("#tblHistoryOutlet").DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        searchDelay: 700,
        ajax: {
            url: routes.data,
            data: function (d) {
                return $.extend({}, d, {
                    start_date: $startDate.val(),
                    end_date: $endDate.val(),
                    coverage_id: $coverage.val(),
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
            { data: "id_outlet", name: "so.id_outlet", searchable: false },
            { data: "outlet_name", name: "so.outlet_name", searchable: false },
            {
                data: "coverage_nik",
                name: "so.coverage_nik",
                searchable: false,
                className: "text-center",
                render: function (data) {
                    return isUnmappedValue(data)
                        ? '<span class="text-muted font-weight-bold">Unmapped</span>'
                        : data;
                },
            },
            {
                data: "user_name",
                name: "so.user_name",
                searchable: false,
                className: "text-center",
                render: function (data) {
                    return isUnmappedValue(data)
                        ? '<span class="text-muted font-weight-bold">Unmapped</span>'
                        : data;
                },
            },
            {
                data: "coverage_level",
                name: "so.coverage_level",
                searchable: false,
                className: "text-center",
                render: function (v) {
                    return isUnmappedValue(v)
                        ? '<span class="text-muted font-weight-bold">Unmapped</span>'
                        : '<span class="badge badge-light-primary font-weight-bold">' + v + '</span>';
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
                className: "text-right font-weight-bolder",
                render: function (data) {
                    return (
                        "Rp " +
                        parseFloat(data || 0).toLocaleString("id-ID")
                    );
                },
            },
        ],
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
        if ($.fn.select2) {
            $coverage.val(null).trigger("change");
            $outlet.val(null).trigger("change");
        }
        table.search("").columns().search("").draw();
        table.ajax.reload(null, false);
    });
});
