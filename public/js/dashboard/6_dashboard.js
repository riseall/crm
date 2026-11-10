/**
 * dashboard/6_dashboard.js
 * Entry point dashboard.
 */
$(document).ready(function () {
    var routes = (window.DashboardPage && window.DashboardPage.routes) || {};
    var access = (window.DashboardPage && window.DashboardPage.access) || {};
    var initialLoad = true;
    var defaultDrilldownLevel = access.default_drilldown_level || "GM";
    var canViewDrilldown =
        access.can_view_drilldown !== false &&
        !!routes.drilldown &&
        $("#drilldownTable").length > 0;

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
        var productFilter = $("#filter_product");
        if (productFilter.length && routes.productLookup) {
            productFilter.select2({
                placeholder: "All",
                closeOnSelect: false,
                width: "100%",
                ajax: {
                    url: routes.productLookup,
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || "" };
                    },
                    processResults: function (data) {
                        return data;
                    },
                    cache: true,
                },
            });
        }

        var pmFilter = $("#filter_pm");
        if (pmFilter.length && !pmFilter.hasClass("select2-hidden-accessible")) {
            pmFilter.select2({
                placeholder: "All",
                closeOnSelect: false,
                width: "100%",
            });
        }

        var empFilter = $("#filter_employee");
        if (empFilter.length && !empFilter.hasClass("select2-hidden-accessible")) {
            empFilter.select2({
                placeholder: "All",
                closeOnSelect: false,
                width: "100%",
            });
        }
    }

    function loadAll() {
        var raceEl = $("#achievementRaceRows");
        if (raceEl.length) {
            raceEl.html(
                '<div class="achievement-race__empty"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat achievement...</div>',
            );
        }

        var consolidatedJob = $.get(
            routes.consolidatedData,
            Dashboard.Helpers.getFilters(),
        )
            .then(function (res) {
                Dashboard.Cards.render(res.summary);
                return Dashboard.Charts.renderAll(res);
            })
            .catch(function (error) {
                console.error("Gagal memuat data dashboard:", error);
                if (raceEl.length) {
                    raceEl.html(
                        '<div class="achievement-race__empty text-danger">Gagal memuat achievement</div>',
                    );
                }
            });

        if (
            window.CrmLoading &&
            typeof window.CrmLoading.track === "function"
        ) {
            window.CrmLoading.track(consolidatedJob);
        }

        if (canViewDrilldown) {
            Dashboard.Drilldown.reset(defaultDrilldownLevel);
        }

        if (!initialLoad) {
            Dashboard.Tables.reloadAll();
        }

        initialLoad = false;
    }

    $("#btnApplyFilter").on("click", loadAll);
    initProductFilter();
    loadAll();

    window.addEventListener("filter:reset", function (event) {
        if (event.detail && event.detail.id !== "btnResetSellingOut") return;
        if (window.$ && $.fn && $.fn.select2) {
            $("#filter_product").val(null).trigger("change");
            $("#filter_distributor").val(null).trigger("change");
            $("#filter_pm").val(null).trigger("change");
            $("#filter_employee").val(null).trigger("change");
        }
        loadAll();
    });
});
