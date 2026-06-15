/**
 * dashboard/1_helper_rupiah.js
 * Helper bersama untuk semua modul dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Helpers = (function () {
    function formatRupiah(val) {
        if (!val) return 'Rp 0';
        return 'Rp ' + parseFloat(val).toLocaleString('id-ID', { minimumFractionDigits: 0 });
    }

    function formatShort(val) {
        var v = parseFloat(val) || 0;
        if (v >= 1e9) return 'Rp ' + (v / 1e9).toFixed(1) + 'M';
        if (v >= 1e6) return 'Rp ' + (v / 1e6).toFixed(1) + 'jt';
        if (v >= 1e3) return 'Rp ' + (v / 1e3).toFixed(0) + 'rb';
        return 'Rp ' + v;
    }

    function formatInt(val) {
        return parseInt(val || 0, 10).toLocaleString('id-ID');
    }

    function formatBadge(val) {
        return '<span class="level-badge level-' + val + '">' + val + '</span>';
    }

    function getFilters() {
        return {
            start_date: $('#filter_start_date').val() || null,
            end_date: $('#filter_end_date').val() || null,
            level: $('#filter_level').val(),
            pilar: $('#filter_pilar').val() || [],
            distributor: $('#filter_distributor').val() || [],
        };
    }

    function getRoutes() {
        return (window.DashboardPage && window.DashboardPage.routes) || {};
    }

    function apiGet(routeKey, params) {
        var routes = getRoutes();
        return $.get(routes[routeKey], params);
    }

    function loadingSpinner() {
        return '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
    }

    return {
        formatRupiah: formatRupiah,
        formatShort: formatShort,
        formatInt: formatInt,
        formatBadge: formatBadge,
        getFilters: getFilters,
        apiGet: apiGet,
        loadingSpinner: loadingSpinner,
    };
})();
