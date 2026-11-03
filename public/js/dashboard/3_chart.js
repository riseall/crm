/**
 * dashboard/3_chart.js
 * Semua chart dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Charts = (function ($, H) {
    var routes = {};
    var consolidatedData = null;
    var chartMode = "hari";
    var chartTrend = null;
    var chartDivisi = null;
    var chartDivisiEl = null;
    var chartDivisiResizeObserver = null;
    var chartDivisiResizeFrame = null;
    var chartDivisiLastWidth = 0;
    var chartPilar = null;
    var chartSegment = null;
    var chartAchievementRaceEl = null;

    var GRID_LIGHT = { borderColor: "#f0f0f0", strokeDashArray: 4 };

    function init(appRoutes) {
        routes = appRoutes || {};
        initChartTrend();
        initChartDivisi();
        initChartSegment();
        initChartPilar();
        initAchievementRace();
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatPercent(value) {
        var number = parseFloat(value) || 0;
        return number.toLocaleString("id-ID", {
            minimumFractionDigits: number % 1 === 0 ? 0 : 1,
            maximumFractionDigits: 1,
        }) + "%";
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

    var isLoadingChartBulan = false;

    function renderTrendSeries(res) {
        if (!chartTrend || !res) return Promise.resolve();

        var title =
            res.mode === "hari"
                ? "Netto per Hari - " + res.period
                : "Netto per Bulan - " + res.period;
        $("#chartTrendTitle").text(title);

        var showLabel = res.data && res.data.length <= 31;
        return Promise.resolve(chartTrend.updateOptions({
            xaxis: { categories: res.labels || [] },
            dataLabels: { enabled: showLabel },
        })).then(function () {
            return chartTrend.updateSeries([{ name: "Netto", data: res.data || [] }]);
        });
    }

    function fetchChartBulan() {
        if (!routes.chartBulan || isLoadingChartBulan) return Promise.resolve();

        isLoadingChartBulan = true;
        $("#chartTrendTitle").html('Netto per Bulan <span class="spinner-border spinner-border-sm text-primary ml-2" role="status"></span>');

        var filters = typeof Dashboard.Helpers !== "undefined" && typeof Dashboard.Helpers.getFilters === "function"
            ? Dashboard.Helpers.getFilters()
            : {};

        return $.get(routes.chartBulan, filters)
            .then(function (res) {
                if (consolidatedData) {
                    consolidatedData.chart_bulan = res;
                }
                if (chartMode === "bulan") {
                    return renderTrendSeries(res);
                }
            })
            .catch(function (err) {
                console.error("Gagal memuat chart bulan:", err);
                $("#chartTrendTitle").text("Netto per Bulan - Gagal memuat data");
            })
            .always(function () {
                isLoadingChartBulan = false;
            });
    }

    function loadChartTrend() {
        if (!chartTrend || !consolidatedData) return Promise.resolve();

        if (chartMode === "bulan") {
            if (consolidatedData.chart_bulan) {
                return renderTrendSeries(consolidatedData.chart_bulan);
            }
            return fetchChartBulan();
        }

        return renderTrendSeries(consolidatedData.chart_hari);
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
            colors: ["#8B5CF6", "#EC4899", "#3B82F6", "#10B981", "#F59E0B", "#6366F1", "#14B8A6"],
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

        return {
            chart: {
                height: isMobile ? 320 : 310,
            },
            legend: {
                position: "bottom",
                horizontalAlign: "center",
                fontSize: "11px",
                itemMargin: {
                    horizontal: 6,
                    vertical: 3,
                },
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: "68%",
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
        if (!chartDivisi || !consolidatedData || !consolidatedData.chart_divisi) return Promise.resolve();
        var res = consolidatedData.chart_divisi;

        return Promise.resolve(chartDivisi.updateOptions({ labels: res.labels })).then(function () {
            return chartDivisi.updateSeries(res.data);
        });
    }

    function initChartSegment() {
        var el = document.querySelector("#chartPerSegment");
        if (!el) return;

        chartSegment = new ApexCharts(el, {
            chart: {
                type: "bar",
                height: 310,
                toolbar: { show: false },
                animations: { enabled: true, easing: "easeinout", speed: 500 },
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    distributed: true,
                    barHeight: "62%",
                    dataLabels: { position: "top" },
                },
            },
            grid: {
                borderColor: "#eef0f5",
                strokeDashArray: 4,
                padding: { right: 0, left: 5, top: -10, bottom: -10 },
            },
            series: [{ name: "Netto", data: [] }],
            xaxis: {
                categories: [],
                labels: {
                    show: false,
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { fontSize: "10px", fontWeight: 600, colors: "#4B5563" },
                    offsetX: 0,
                },
            },
            colors: [
                "#378ADD",
                "#1D9E75",
                "#EF9F27",
                "#E24B4A",
                "#7F77DD",
                "#0F6E56",
                "#888780",
                "#D4537E",
                "#0D9488",
                "#A16207",
            ],
            dataLabels: {
                enabled: true,
                formatter: function (v) {
                    return v > 0 ? H.formatShort(v) : "";
                },
                textAnchor: "start",
                offsetX: 4,
                style: { fontSize: "10px", fontWeight: 700, colors: ["#1F2937"] },
                dropShadow: { enabled: false },
                background: {
                    enabled: false,
                },
            },
            legend: { show: false },
            tooltip: {
                y: {
                    formatter: function (v) {
                        return H.formatRupiah(v);
                    },
                },
            },
        });
        chartSegment.render();
    }

    function loadChartSegment() {
        if (!chartSegment || !consolidatedData || !consolidatedData.chart_segment) return Promise.resolve();
        var res = consolidatedData.chart_segment;

        var values = (res.data || []).map(function (value) {
            return Number(value) || 0;
        });
        var maxValue = values.length ? Math.max.apply(null, values) : 0;
        var axisMax = maxValue > 0 ? maxValue * 1.35 : undefined;

        return Promise.resolve(chartSegment.updateOptions({
            xaxis: {
                categories: res.labels,
                max: axisMax,
                labels: {
                    show: false,
                },
            },
        })).then(function () {
            return chartSegment.updateSeries([{ name: "Netto", data: res.data }]);
        });
    }

    function initChartPilar() {
        var el = document.querySelector("#chartPerPilar");
        if (!el) return;

        chartPilar = new ApexCharts(el, {
            chart: {
                type: "bar",
                height: 310,
                toolbar: { show: false },
                animations: { enabled: true, easing: "easeinout", speed: 500 },
            },
            series: [{ name: "Netto", data: [] }],
            xaxis: {
                categories: [],
                labels: {
                    rotate: -30,
                    style: { fontSize: "10px", fontWeight: 600, colors: "#4B5563" },
                },
            },
            yaxis: {
                labels: {
                    formatter: function (v) {
                        return H.formatShort(v);
                    },
                    style: { fontSize: "10px", colors: "#6B7280" },
                },
            },
            colors: [
                "#D85A30",
                "#EF9F27",
                "#1D9E75",
                "#378ADD",
                "#7F77DD",
                "#D4537E",
            ],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: "38%",
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
                    fontSize: "10px",
                    colors: ["#1F2937"],
                    fontWeight: 700,
                },
                offsetY: -18,
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
            grid: {
                borderColor: "#eef0f5",
                strokeDashArray: 4,
                padding: { top: 10, right: 10, bottom: 0, left: 10 },
            },
        });
        chartPilar.render();
    }

    function loadChartPilar() {
        if (!chartPilar || !consolidatedData || !consolidatedData.chart_pilar) return Promise.resolve();
        var res = consolidatedData.chart_pilar;

        var values = (res.data || []).map(function (value) {
            return Number(value) || 0;
        });
        var maxValue = values.length ? Math.max.apply(null, values) : 0;
        var axisMax = maxValue > 0 ? maxValue * 1.3 : undefined;

        return Promise.resolve(chartPilar.updateOptions({
            xaxis: {
                categories: res.labels,
                labels: {
                    rotate: -30,
                    style: { fontSize: "10px", fontWeight: 600, colors: "#4B5563" },
                },
            },
            yaxis: {
                max: axisMax,
                labels: {
                    formatter: function (v) {
                        return H.formatShort(v);
                    },
                    style: { fontSize: "10px", colors: "#6B7280" },
                },
            },
        })).then(function () {
            return chartPilar.updateSeries([{ name: "Netto", data: res.data }]);
        });
    }


    function initAchievementRace() {
        chartAchievementRaceEl = document.querySelector("#achievementRaceRows");
    }

    function getAchievementTier(achievement) {
        if (achievement >= 90) return "over";
        if (achievement >= 70) return "near";
        if (achievement >= 40) return "mid";
        return "low";
    }

    function loadAchievementRace() {
        if (!chartAchievementRaceEl || !consolidatedData || !consolidatedData.achievement_race) {
            return Promise.resolve();
        }
        var res = consolidatedData.achievement_race;
        var rows = res.data || [];

        if (!rows.length) {
            chartAchievementRaceEl.innerHTML = '<div class="achievement-race__empty">Data achievement belum tersedia</div>';
            return Promise.resolve();
        }

        chartAchievementRaceEl.innerHTML = rows.map(function (row) {
            var achievement = Math.max(parseFloat(row.achievement) || 0, 0);
            var width = Math.min(achievement, 100);
            var markerLeft = Math.min(Math.max(width, 4), 100);
            var tier = getAchievementTier(achievement);
            var title = [
                "TOP: " + H.formatRupiah(row.top),
                "REAL: " + H.formatRupiah(row.real),
                "ACH: " + formatPercent(achievement),
            ].join("\n");

            return [
                '<div class="achievement-race__row" title="' + escapeHtml(title) + '">',
                    '<div class="achievement-race__label">' + escapeHtml(row.label) + '</div>',
                    '<div class="achievement-race__track">',
                        '<div class="achievement-race__bar achievement-race__bar--' + tier + '" style="width:' + width + '%">',
                            '<span class="achievement-race__value">' + formatPercent(achievement) + '</span>',
                        '</div>',
                        '<span class="achievement-race__marker achievement-race__marker--' + tier + '" style="left:' + markerLeft + '%">',
                            '<i class="fas ' + (achievement >= 100 ? "fa-flag-checkered" : "fa-running") + '"></i>',
                        '</span>',
                    '</div>',
                '</div>',
            ].join("");
        }).join("");

        return Promise.resolve();
    }

    function loadAll() {
        return Promise.all([
            loadChartTrend(),
            loadChartDivisi(),
            loadChartSegment(),
            loadChartPilar(),
            loadAchievementRace(),
        ]);
    }

    function renderAll(data) {
        consolidatedData = data;
        return loadAll();
    }

    return {
        init: init,
        loadAll: loadAll,
        renderAll: renderAll,
    };
})(jQuery, Dashboard.Helpers);