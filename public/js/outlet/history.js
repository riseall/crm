/**
 * outlet/history.js
 * DataTable untuk halaman history outlet.
 */
$(document).ready(function () {
    var page = window.OutletHistoryPage || {};
    var routes = page.routes || {};

    var $bulan = $("#filter_bulan");
    var $tahun = $("#filter_tahun");
    var $coverage = $("#filter_coverage_id");

    if (!$("#tblHistoryOutlet").length) {
        return;
    }

    $bulan.val(page.defaultMonth || $bulan.val());
    $tahun.val(page.defaultYear || $tahun.val());

    var table = $("#tblHistoryOutlet").DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        searchDelay: 400,
        ajax: {
            url: routes.data,
            data: function (d) {
                return $.extend({}, d, {
                    bulan: $bulan.val(),
                    tahun: $tahun.val(),
                    coverage_id: $coverage.val(),
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
            { data: "dist", name: "so.dist", searchable: false },
            { data: "id_outlet", name: "so.id_outlet", searchable: false },
            { data: "nama_outlet", name: "vcd.nama_outlet", searchable: false },
            { data: "cov_nik", name: "mc.cov_nik", searchable: false },
            {
                data: "user_name",
                name: "ma.name",
                searchable: false,
                render: function (data) {
                    return data || "-";
                },
            },
            {
                data: "level_name",
                name: "mc.cov_level",
                searchable: false,
                render: function (v) {
                    return (
                        '<span class="badge badge-light-primary">' +
                        (v || "-") +
                        "</span>"
                    );
                },
            },
            { data: "pilar", name: "vp.pilar", searchable: false },
            { data: "valid_from", name: "mc.valid_from", searchable: false },
            {
                data: "valid_to",
                name: "mc.valid_to",
                searchable: false,
                render: function (v) {
                    return v || "Sekarang";
                },
            },
            {
                data: "status_label",
                name: "status_label",
                searchable: false,
                render: function (v, t, r) {
                    return (
                        '<span class="badge ' +
                        (r.status_class || "badge-secondary") +
                        '">' +
                        (v || "Aktif") +
                        "</span>"
                    );
                },
                className: "text-center",
            },
            {
                data: "total_transaksi",
                name: "total_transaksi",
                searchable: false,
                className: "text-right",
                render: $.fn.dataTable.render.number(".", ",", 0),
            },
            {
                data: "total_netto",
                name: "total_netto",
                searchable: false,
                className: "text-right",
                render: function (data) {
                    return (
                        "<strong>Rp " +
                        parseFloat(data || 0).toLocaleString("id-ID") +
                        "</strong>"
                    );
                },
            },
        ],
        order: [[12, "desc"]],
    });

    $("#btnApplyHistoryFilter").on("click", function () {
        table.ajax.reload(null, false);
    });

    $("#btnAllOutlets").on("click", function () {
        window.location.href = routes.index;
    });
});
