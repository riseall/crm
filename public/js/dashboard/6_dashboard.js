/**
 * dashboard/6_dashboard.js
 * Entry point dashboard.
 */
$(document).ready(function () {
    var routes = (window.DashboardPage && window.DashboardPage.routes) || {};
    var access = (window.DashboardPage && window.DashboardPage.access) || {};
    var initialLoad = true;
    var defaultDrilldownLevel = access.default_drilldown_level || 'GM';
    var canViewDrilldown = access.can_view_drilldown !== false && !!routes.drilldown && $('#drilldownTable').length > 0;

    Dashboard.Cards.init(routes.summary);
    Dashboard.Charts.init({
        chartHari: routes.chartHari,
        chartBulan: routes.chartBulan,
        chartDivisi: routes.chartDivisi,
        chartSegment: routes.chartSegment,
        chartPilar: routes.chartPilar,
        chartCoverage: routes.chartCoverage,
        chartAchievementRace: routes.chartAchievementRace,
    });
    if (canViewDrilldown) {
        Dashboard.Drilldown.init(routes.drilldown, {
            defaultLevel: defaultDrilldownLevel,
        });
    }
    Dashboard.Tables.init({
        tableAnggota: routes.tableAnggota,
    });

    function initProductFilter() {
        var productFilter = $('#filter_product');
        if (!productFilter.length || !routes.productLookup) return;

        productFilter.select2({
            placeholder: 'Semua produk',
            closeOnSelect: false,
            width: '100%',
            ajax: {
                url: routes.productLookup,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return data;
                },
                cache: true,
            },
        });
    }

    function loadAll() {
        var chartAndCardJob = Promise.all([
            Dashboard.Cards.load(),
            Dashboard.Charts.loadAll(),
        ].map(function (job) {
            return Promise.resolve(job).catch(function (error) {
                console.error(error);
            });
        }));

        if (window.CrmLoading && typeof window.CrmLoading.track === 'function') {
            window.CrmLoading.track(chartAndCardJob);
        }

        if (canViewDrilldown) {
            Dashboard.Drilldown.reset(defaultDrilldownLevel);
        }

        if (!initialLoad) {
            Dashboard.Tables.reloadAll();
        }

        initialLoad = false;
    }

    $('#btnApplyFilter').on('click', loadAll);
    initProductFilter();
    loadAll();
});
