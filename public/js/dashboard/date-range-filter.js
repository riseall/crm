(function ($) {
    "use strict";

    function formatDisplay(start, end) {
        return start.format("DD MMM YYYY") + " - " + end.format("DD MMM YYYY");
    }

    function initializeDateRange(element) {
        var input = $(element);
        var startInput = $(input.data("start-input"));
        var endInput = $(input.data("end-input"));
        var required = String(input.data("required")) === "true";
        var startValue = startInput.val();
        var endValue = endInput.val();
        var start = startValue ? moment(startValue, "YYYY-MM-DD") : moment().startOf("month");
        var end = endValue ? moment(endValue, "YYYY-MM-DD") : moment().endOf("month");

        input.daterangepicker(
            {
                autoUpdateInput: false,
                startDate: start,
                endDate: end,
                opens: "left",
                locale: {
                    format: "DD MMM YYYY",
                    separator: " - ",
                    applyLabel: "Terapkan",
                    cancelLabel: required ? "Batal" : "Hapus",
                    fromLabel: "Dari",
                    toLabel: "Sampai",
                    customRangeLabel: "Pilih tanggal",
                    weekLabel: "M",
                    daysOfWeek: ["Mg", "Sn", "Sl", "Rb", "Km", "Jm", "Sb"],
                    monthNames: [
                        "Januari",
                        "Februari",
                        "Maret",
                        "April",
                        "Mei",
                        "Juni",
                        "Juli",
                        "Agustus",
                        "September",
                        "Oktober",
                        "November",
                        "Desember",
                    ],
                    firstDay: 1,
                },
                ranges: {
                    "Bulan ini": [moment().startOf("month"), moment().endOf("month")],
                    "Bulan lalu": [
                        moment().subtract(1, "month").startOf("month"),
                        moment().subtract(1, "month").endOf("month"),
                    ],
                    "3 bulan terakhir": [
                        moment().subtract(2, "month").startOf("month"),
                        moment().endOf("month"),
                    ],
                    "Tahun ini": [moment().startOf("year"), moment().endOf("year")],
                },
            },
            function (selectedStart, selectedEnd) {
                startInput.val(selectedStart.format("YYYY-MM-DD"));
                endInput.val(selectedEnd.format("YYYY-MM-DD"));
                input.val(formatDisplay(selectedStart, selectedEnd));
            },
        );

        if (startValue && endValue) {
            input.val(formatDisplay(start, end));
        }

        input.on("cancel.daterangepicker", function () {
            if (!required) {
                input.val("");
                startInput.val("");
                endInput.val("");
            }
        });
    }

    $(function () {
        $(".dashboard-date-range").each(function () {
            initializeDateRange(this);
        });
    });
})(jQuery);
