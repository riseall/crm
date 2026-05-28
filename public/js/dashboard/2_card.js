/**
 * dashboard/2_card.js
 * Summary cards dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Cards = (function ($, H) {
    var route = '';

    function init(summaryRoute) {
        route = summaryRoute;
    }

    function load() {
        return $.get(route, H.getFilters(), function (res) {
            $('#card_netto').text(H.formatRupiah(res.total_netto));
            $('#card_outlet').text(H.formatInt(res.total_outlet));
            $('#card_anggota').text(H.formatInt(res.total_anggota));
            $('#card_transaksi').text(H.formatInt(res.total_transaksi));

            var growth = parseFloat(res.diff_netto) || 0;
            var growthEl = $('#card_growth');
            growthEl
                .text((growth >= 0 ? '+' : '') + growth.toFixed(1) + '% vs bulan lalu')
                .removeClass('text-success text-danger')
                .addClass(growth >= 0 ? 'text-success' : 'text-danger');
        });
    }

    return { init: init, load: load };
})(jQuery, Dashboard.Helpers);
