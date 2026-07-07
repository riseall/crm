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
                placeholder: "Cari NIK atau Nama...",
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
            placeholder: "Cari kode atau nama outlet...",
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
                    return data || "Unmapped";
                },
            },
            {
                data: "user_name",
                name: "so.user_name",
                searchable: false,
                render: function (data) {
                    return data || "-";
                },
            },
            {
                data: "coverage_level",
                name: "so.coverage_level",
                searchable: false,
                className: "text-center",
                render: function (v) {
                    return (
                        '<span class="badge badge-light-primary">' +
                        (v || "-") +
                        "</span>"
                    );
                },
            },
            {
                data: "product_pilar",
                name: "so.product_pilar",
                searchable: false,
                className: "text-center",
            },
            // {
            //     data: "status_label",
            //     name: "status_label",
            //     searchable: false,
            //     render: function (v, t, r) {
            //         return (
            //             '<span class="badge ' +
            //             (r.status_class || "badge-secondary") +
            //             '">' +
            //             (v || "Aktif") +
            //             "</span>"
            //         );
            //     },
            //     className: "text-center",
            // },
            {
                data: "total_transaksi",
                name: "total_transaksi",
                searchable: false,
                render: $.fn.dataTable.render.number(".", ",", 0),
            },
            {
                data: "total_netto",
                name: "total_netto",
                searchable: false,
                render: function (data) {
                    return (
                        "<strong>Rp " +
                        parseFloat(data || 0).toLocaleString("id-ID") +
                        "</strong>"
                    );
                },
            },
        ],
        order: [[10, "desc"]],
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
