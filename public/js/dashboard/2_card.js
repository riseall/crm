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

    function render(res) {
        if (!res) return;
        $('#card_netto').text(H.formatRupiah(res.total_netto));
        $('#card_top').text(H.formatRupiah(res.top_value));

        var topValue = parseFloat(res.top_value) || 0;
        var achievement = parseFloat(res.top_achievement) || 0;
        $('#card_top_achievement').text(achievement.toLocaleString('id-ID', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
        }) + '%');

        var gapEl = $('#card_top_gap');
        if (topValue > 0) {
            var gap = topValue - (parseFloat(res.total_netto) || 0);
            gapEl
                .text((gap >= 0 ? 'Sisa ' : 'Lewat ') + H.formatRupiah(Math.abs(gap)))
                .removeClass('text-muted text-success text-danger')
                .addClass(gap >= 0 ? 'text-danger' : 'text-success');
        } else {
            gapEl
                .text('TOP belum tersedia')
                .removeClass('text-success text-danger')
                .addClass('text-muted');
        }

        var growth = parseFloat(res.diff_netto) || 0;
        var growthEl = $('#card_growth');
        growthEl
            .text((growth >= 0 ? '+' : '') + growth.toFixed(1) + '% vs periode sebelumnya')
            .removeClass('text-success text-danger')
            .addClass(growth >= 0 ? 'text-success' : 'text-danger');
    }

    function load() {
        return $.get(route, H.getFilters(), function (res) {
            render(res);
        });
    }

    return { init: init, load: load, render: render };
})(jQuery, Dashboard.Helpers);
