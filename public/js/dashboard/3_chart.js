/**
 * dashboard/3_chart.js
 * Semua chart dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Charts = (function ($, H) {
    var routes = {};
    var chartMode = "hari";
    var chartTrend = null;
    var chartDivisi = null;
    var chartDivisiEl = null;
    var chartDivisiResizeObserver = null;
    var chartDivisiResizeFrame = null;
    var chartDivisiLastWidth = 0;
    var chartPilar = null;
    var chartCoverage = null;

    var GRID_LIGHT = { borderColor: "#f0f0f0", strokeDashArray: 4 };
    var COVERAGE_COLORS = [
        "#6366F1",
        "#8B5CF6",
        "#EC4899",
        "#F43F5E",
        "#F59E0B",
        "#10B981",
        "#3B82F6",
        "#14B8A6",
        "#F97316",
        "#84CC16",
    ];

    function init(appRoutes) {
        routes = appRoutes || {};
        initChartTrend();
        initChartDivisi();
        initChartPilar();
        initChartCoverage();
    }

    function initChartTrend() {
        var el = document.querySelector("#chartPerBulan");
        if (!el) return;

        chartTrend = new ApexCharts(el, {
            chart: {
                type: "area",
                height: 320,
                toolbar: {
                    show: true,
                    tools: {
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        reset: true,
                        download: false,
                    },
                },
                animations: { enabled: true, easing: "easeinout", speed: 500 },
            },
            series: [{ name: "Netto", data: [] }],
            xaxis: {
                categories: [],
                labels: { rotate: -30, style: { fontSize: "11px" } },
            },
            yaxis: {
                labels: {
                    formatter: function (v) {
                        return H.formatShort(v);
                    },
                },
            },
            colors: ["#5B7CFA"],
            fill: {
                type: "gradient",
                gradient: {
                    shade: "dark",
                    type: "vertical",
                    shadeIntensity: 0.4,
                    gradientToColors: ["#A78BFA"],
                    opacityFrom: 0.5,
                    opacityTo: 0.05,
                },
            },
            stroke: { curve: "smooth", width: 2.5, colors: ["#5B7CFA"] },
            markers: {
                size: 4,
                colors: ["#fff"],
                strokeColors: "#5B7CFA",
                strokeWidth: 2,
                hover: { size: 6 },
            },
            dataLabels: {
                enabled: true,
                formatter: function (v) {
                    return v > 0 ? H.formatShort(v) : "";
                },
                style: {
                    fontSize: "10px",
                    colors: ["#3F4254"],
                    fontWeight: 600,
                },
                background: {
                    enabled: true,
                    foreColor: "#fff",
                    borderRadius: 3,
                    padding: 3,
                    opacity: 0.85,
                    borderColor: "#e0e0e0",
                    borderWidth: 1,
                },
                offsetY: -8,
            },
            tooltip: {
                y: {
                    formatter: function (v) {
                        return H.formatRupiah(v);
                    },
                },
                theme: "light",
            },
            grid: GRID_LIGHT,
        });
        chartTrend.render();

        $("#btnModeBulan").on("click", function () {
            if (chartMode === "bulan") return;
            chartMode = "bulan";
            $(this).addClass("active");
            $("#btnModeHari").removeClass("active");
            loadChartTrend();
        });

        $("#btnModeHari").on("click", function () {
            if (chartMode === "hari") return;
            chartMode = "hari";
            $(this).addClass("active");
            $("#btnModeBulan").removeClass("active");
            loadChartTrend();
        });
    }

    function loadChartTrend() {
        if (!chartTrend) return Promise.resolve();

        var routeKey = chartMode === "hari" ? "chartHari" : "chartBulan";
        return H.apiGet(routeKey, H.getFilters()).then(function (res) {
            var title =
                res.mode === "hari"
                    ? "Netto per Hari - " + res.period
                    : "Netto per Bulan - " + res.period;
            $("#chartTrendTitle").text(title);

            var showLabel = res.data.length <= 31;
            return Promise.resolve(chartTrend.updateOptions({
                xaxis: { categories: res.labels },
                dataLabels: { enabled: showLabel },
            })).then(function () {
                return chartTrend.updateSeries([{ name: "Netto", data: res.data }]);
            });
        });
    }

    function initChartDivisi() {
        var el = document.querySelector("#chartPerDivisi");
        if (!el) return;

        chartDivisiEl = el;
        chartDivisi = new ApexCharts(el, {
            chart: {
                type: "donut",
                height: getDivisiChartOptions(el).chart.height,
                redrawOnParentResize: true,
                redrawOnWindowResize: true,
            },
            series: [],
            labels: [],
            colors: ["#8B5CF6", "#EC4899", "#3B82F6"],
            legend: getDivisiChartOptions(el).legend,
            plotOptions: {
                pie: {
                    expandOnClick: false,
                    donut: {
                        size: getDivisiChartOptions(el).plotOptions.pie.donut.size,
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: "Total",
                                formatter: function (w) {
                                    var total = w.globals.seriesTotals.reduce(
                                        function (a, b) {
                                            return a + b;
                                        },
                                        0,
                                    );
                                    return H.formatShort(total);
                                },
                            },
                            value: {
                                formatter: function (v) {
                                    return H.formatShort(v);
                                },
                                fontSize: "14px",
                            },
                        },
                    },
                },
            },
            dataLabels: {
                enabled: getDivisiChartOptions(el).dataLabels.enabled,
                formatter: function (val, opts) {
                    return (
                        opts.w.config.labels[opts.seriesIndex] +
                        "\n" +
                        val.toFixed(1) +
                        "%"
                    );
                },
                style: { fontSize: "11px" },
            },
            fill: { type: "gradient" },
            tooltip: {
                y: {
                    formatter: function (v) {
                        return H.formatRupiah(v);
                    },
                },
            },
            stroke: { width: 2, colors: ["#fff"] },
        });
        chartDivisi.render();
        bindChartDivisiResize();
    }

    function getDivisiChartOptions(el) {
        var width = el && el.parentElement ? el.parentElement.clientWidth : window.innerWidth;
        var isMobile = width < 576;
        var isTablet = width >= 576 && width < 992;

        return {
            chart: {
                height: isMobile ? 340 : isTablet ? 320 : 300,
            },
            legend: {
                position: isMobile ? "bottom" : "right",
                horizontalAlign: "center",
                fontSize: isMobile ? "11px" : "12px",
                itemMargin: {
                    horizontal: isMobile ? 8 : 10,
                    vertical: isMobile ? 2 : 4,
                },
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: isMobile ? "58%" : "65%",
                    },
                },
            },
            dataLabels: {
                enabled: !isMobile,
            },
        };
    }

    function applyChartDivisiResponsiveOptions() {
        if (!chartDivisi || !chartDivisiEl) return;

        var width = chartDivisiEl.parentElement ? chartDivisiEl.parentElement.clientWidth : chartDivisiEl.clientWidth;
        if (width === chartDivisiLastWidth) return;

        chartDivisiLastWidth = width;
        chartDivisi.updateOptions(getDivisiChartOptions(chartDivisiEl), false, true);
    }

    function bindChartDivisiResize() {
        if (!chartDivisiEl) return;

        chartDivisiLastWidth = chartDivisiEl.parentElement ? chartDivisiEl.parentElement.clientWidth : chartDivisiEl.clientWidth;

        if (window.ResizeObserver) {
            chartDivisiResizeObserver = new ResizeObserver(function () {
                if (chartDivisiResizeFrame) {
                    window.cancelAnimationFrame(chartDivisiResizeFrame);
                }

                chartDivisiResizeFrame = window.requestAnimationFrame(function () {
                    chartDivisiResizeFrame = null;
                    applyChartDivisiResponsiveOptions();
                });
            });
            chartDivisiResizeObserver.observe(chartDivisiEl.parentElement || chartDivisiEl);
        } else {
            $(window).on("resize.dashboardChartDivisi", applyChartDivisiResponsiveOptions);
        }
    }

    function loadChartDivisi() {
        if (!chartDivisi) return Promise.resolve();

        return H.apiGet("chartDivisi", H.getFilters()).then(function (res) {
            return Promise.resolve(chartDivisi.updateOptions({ labels: res.labels })).then(function () {
                return chartDivisi.updateSeries(res.data);
            });
        });
    }

    function initChartPilar() {
        var el = document.querySelector("#chartPerPilar");
        if (!el) return;

        chartPilar = new ApexCharts(el, {
            chart: {
                type: "bar",
                height: 300,
                toolbar: { show: false },
                animations: { enabled: true, easing: "easeinout", speed: 500 },
            },
            series: [{ name: "Netto", data: [] }],
            xaxis: {
                categories: [],
                labels: { style: { fontSize: "12px", fontWeight: 600 } },
            },
            yaxis: {
                labels: {
                    formatter: function (v) {
                        return H.formatShort(v);
                    },
                },
            },
            colors: [
                "#F43F5E",
                "#F59E0B",
                "#10B981",
                "#3B82F6",
                "#6366F1",
                "#8B5CF6",
                "#EC4899",
            ],
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: "45%",
                    distributed: true,
                    dataLabels: { position: "top" },
                },
            },
            dataLabels: {
                enabled: true,
                formatter: function (v) {
                    return v > 0 ? H.formatShort(v) : "";
                },
                style: {
                    fontSize: "11px",
                    colors: ["#3F4254"],
                    fontWeight: 700,
                },
                offsetY: -22,
            },
            legend: { show: false },
            tooltip: {
                y: {
                    formatter: function (v) {
                        return H.formatRupiah(v);
                    },
                },
            },
            fill: {
                type: "gradient",
                gradient: {
                    shade: "light",
                    type: "horizontal",
                    shadeIntensity: 0.25,
                    inverseColors: true,
                    opacityFrom: 0.85,
                    opacityTo: 0.85,
                    stops: [50, 0, 100],
                },
            },
            grid: GRID_LIGHT,
        });
        chartPilar.render();
    }

    function loadChartPilar() {
        if (!chartPilar) return Promise.resolve();

        return H.apiGet("chartPilar", H.getFilters()).then(function (res) {
            return Promise.resolve(chartPilar.updateOptions({ xaxis: { categories: res.labels } })).then(function () {
                return chartPilar.updateSeries([{ name: "Netto", data: res.data }]);
            });
        });
    }

    function initChartCoverage() {
        var el = document.querySelector("#chartPerCoverage");
        if (!el) return;

        chartCoverage = new ApexCharts(el, {
            chart: {
                type: "bar",
                height: 360,
                toolbar: { show: false },
                animations: { enabled: true, speed: 600 },
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 5,
                    distributed: true,
                    barHeight: "62%",
                    dataLabels: { position: "center" },
                },
            },
            series: [{ name: "Netto", data: [] }],
            xaxis: {
                categories: [],
                labels: {
                    formatter: function (v) {
                        return H.formatShort(v);
                    },
                },
            },
            yaxis: { labels: { style: { fontSize: "11px" } } },
            colors: COVERAGE_COLORS,
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: function (v) {
                    return v > 0 ? H.formatShort(v) : "";
                },
                style: { fontSize: "11px", fontWeight: 700, colors: ["#fff"] },
                dropShadow: { enabled: true, blur: 2, opacity: 0.3 },
            },
            tooltip: {
                y: {
                    formatter: function (v) {
                        return H.formatRupiah(v);
                    },
                },
                theme: "dark",
            },
            grid: { borderColor: "#f3f3f3", strokeDashArray: 4 },
        });
        chartCoverage.render();
    }

    function loadChartCoverage() {
        if (!chartCoverage) return Promise.resolve();

        return H.apiGet("chartCoverage", H.getFilters()).then(function (res) {
            return Promise.resolve(chartCoverage.updateOptions({ xaxis: { categories: res.labels } })).then(function () {
                return chartCoverage.updateSeries([{ name: "Netto", data: res.data }]);
            });
        });
    }

    function loadAll() {
        return Promise.all([
            loadChartTrend(),
            loadChartDivisi(),
            loadChartPilar(),
            loadChartCoverage(),
        ]);
    }

    return {
        init: init,
        loadAll: loadAll,
    };
})(jQuery, Dashboard.Helpers);
