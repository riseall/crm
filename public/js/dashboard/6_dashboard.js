/**
 * dashboard/6_dashboard.js
 * Entry point dashboard.
 */
$(document).ready(function () {
    var routes = (window.DashboardPage && window.DashboardPage.routes) || {};
    var access = (window.DashboardPage && window.DashboardPage.access) || {};
    var initialLoad = true;
    var defaultDrilldownLevel = access.default_drilldown_level || 'GM';

    Dashboard.Cards.init(routes.summary);
    Dashboard.Charts.init({
        chartHari: routes.chartHari,
        chartBulan: routes.chartBulan,
        chartDivisi: routes.chartDivisi,
        chartPilar: routes.chartPilar,
        chartCoverage: routes.chartCoverage,
    });
    Dashboard.Drilldown.init(routes.drilldown, {
        defaultLevel: defaultDrilldownLevel,
    });
    Dashboard.Tables.init({
        tableAnggota: routes.tableAnggota,
    });

    function loadAll() {
        Dashboard.Cards.load();
        Dashboard.Charts.loadAll();
        Dashboard.Drilldown.reset(defaultDrilldownLevel);

        if (!initialLoad) {
            Dashboard.Tables.reloadAll();
        }

        initialLoad = false;
    }

    $('#btnApplyFilter').on('click', loadAll);
    loadAll();
});
