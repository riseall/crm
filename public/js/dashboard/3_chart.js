/**
 * dashboard/3_chart.js
 * Semua chart dashboard.
 */
var Dashboard = Dashboard || {};

Dashboard.Charts = (function ($, H) {
    var routes = {};
    var chartMode = 'bulan';
    var chartTrend = null;
    var chartDivisi = null;
    var chartPilar = null;
    var chartCoverage = null;

    var GRID_LIGHT = { borderColor: '#f0f0f0', strokeDashArray: 4 };
    var COVERAGE_COLORS = ['#6366F1', '#8B5CF6', '#EC4899', '#F43F5E', '#F59E0B', '#10B981', '#3B82F6', '#14B8A6', '#F97316', '#84CC16'];

    function init(appRoutes) {
        routes = appRoutes || {};
        initChartTrend();
        initChartDivisi();
        initChartPilar();
        initChartCoverage();
    }

    function initChartTrend() {
        var el = document.querySelector('#chartPerBulan');
        if (!el) return;

        chartTrend = new ApexCharts(el, {
            chart: {
                type: 'area',
                height: 320,
                toolbar: {
                    show: true,
                    tools: { zoom: true, zoomin: true, zoomout: true, reset: true, download: false }
                },
                animations: { enabled: true, easing: 'easeinout', speed: 500 },
            },
            series: [{ name: 'Netto', data: [] }],
            xaxis: {
                categories: [],
                labels: { rotate: -30, style: { fontSize: '11px' } }
            },
            yaxis: { labels: { formatter: function (v) { return H.formatShort(v); } } },
            colors: ['#5B7CFA'],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    type: 'vertical',
                    shadeIntensity: 0.4,
                    gradientToColors: ['#A78BFA'],
                    opacityFrom: 0.5,
                    opacityTo: 0.05
                }
            },
            stroke: { curve: 'smooth', width: 2.5, colors: ['#5B7CFA'] },
            markers: {
                size: 4,
                colors: ['#fff'],
                strokeColors: '#5B7CFA',
                strokeWidth: 2,
                hover: { size: 6 }
            },
            dataLabels: {
                enabled: true,
                formatter: function (v) { return v > 0 ? H.formatShort(v) : ''; },
                style: { fontSize: '10px', colors: ['#3F4254'], fontWeight: 600 },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderRadius: 3,
                    padding: 3,
                    opacity: 0.85,
                    borderColor: '#e0e0e0',
                    borderWidth: 1
                },
                offsetY: -8
            },
            tooltip: { y: { formatter: function (v) { return H.formatRupiah(v); } }, theme: 'light' },
            grid: GRID_LIGHT,
        });
        chartTrend.render();

        $('#btnModeBulan').on('click', function () {
            if (chartMode === 'bulan') return;
            chartMode = 'bulan';
            $(this).addClass('active');
            $('#btnModeHari').removeClass('active');
            loadChartTrend();
        });

        $('#btnModeHari').on('click', function () {
            if (chartMode === 'hari') return;
            chartMode = 'hari';
            $(this).addClass('active');
            $('#btnModeBulan').removeClass('active');
            loadChartTrend();
        });
    }

    function loadChartTrend() {
        if (!chartTrend) return;

        var routeKey = chartMode === 'hari' ? 'chartHari' : 'chartBulan';
        H.apiGet(routeKey, H.getFilters()).done(function (res) {
            var title = res.mode === 'hari'
                ? 'Netto per Hari - ' + res.month
                : 'Netto per Bulan - ' + $('#filter_tahun').val();
            $('#chartTrendTitle').text(title);

            var showLabel = res.mode === 'hari' || res.data.length <= 15;
            chartTrend.updateOptions({
                xaxis: { categories: res.labels },
                dataLabels: { enabled: showLabel }
            });
            chartTrend.updateSeries([{ name: 'Netto', data: res.data }]);
        });
    }

    function initChartDivisi() {
        var el = document.querySelector('#chartPerDivisi');
        if (!el) return;

        chartDivisi = new ApexCharts(el, {
            chart: { type: 'donut', height: 300 },
            series: [],
            labels: [],
            colors: ['#EC4899', '#8B5CF6', '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899'],
            legend: { position: 'right', fontSize: '12px' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function (w) {
                                    var total = w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0);
                                    return H.formatShort(total);
                                }
                            },
                            value: {
                                formatter: function (v) { return H.formatShort(v); }
                            }
                        }
                    }
                }
            },
            dataLabels: {
                formatter: function (val, opts) {
                    return opts.w.config.labels[opts.seriesIndex] + '\n' + val.toFixed(1) + '%';
                },
                style: { fontSize: '11px' },
            },
            fill: { type: 'gradient' },
            tooltip: { y: { formatter: function (v) { return H.formatRupiah(v); } } },
            stroke: { width: 2, colors: ['#fff'] },
        });
        chartDivisi.render();
    }

    function loadChartDivisi() {
        if (!chartDivisi) return;

        H.apiGet('chartDivisi', H.getFilters()).done(function (res) {
            chartDivisi.updateOptions({ labels: res.labels });
            chartDivisi.updateSeries(res.data);
        });
    }

    function initChartPilar() {
        var el = document.querySelector('#chartPerPilar');
        if (!el) return;

        chartPilar = new ApexCharts(el, {
            chart: {
                type: 'bar',
                height: 300,
                toolbar: { show: false },
                animations: { enabled: true, easing: 'easeinout', speed: 500 },
            },
            series: [{ name: 'Netto', data: [] }],
            xaxis: {
                categories: [],
                labels: { style: { fontSize: '12px', fontWeight: 600 } }
            },
            yaxis: { labels: { formatter: function (v) { return H.formatShort(v); } } },
            colors: ['#F43F5E', '#F59E0B', '#10B981', '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899'],
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '45%',
                    distributed: true,
                    dataLabels: { position: 'top' }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function (v) { return v > 0 ? H.formatShort(v) : ''; },
                style: { fontSize: '11px', colors: ['#3F4254'], fontWeight: 700 },
                offsetY: -22,
            },
            legend: { show: false },
            tooltip: { y: { formatter: function (v) { return H.formatRupiah(v); } } },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'horizontal',
                    shadeIntensity: 0.25,
                    inverseColors: true,
                    opacityFrom: 0.85,
                    opacityTo: 0.85,
                    stops: [50, 0, 100]
                },
            },
            grid: GRID_LIGHT,
        });
        chartPilar.render();
    }

    function loadChartPilar() {
        if (!chartPilar) return;

        H.apiGet('chartPilar', H.getFilters()).done(function (res) {
            chartPilar.updateOptions({ xaxis: { categories: res.labels } });
            chartPilar.updateSeries([{ name: 'Netto', data: res.data }]);
        });
    }

    function initChartCoverage() {
        var el = document.querySelector('#chartPerCoverage');
        if (!el) return;

        chartCoverage = new ApexCharts(el, {
            chart: { type: 'bar', height: 360, toolbar: { show: false }, animations: { enabled: true, speed: 600 } },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 5,
                    distributed: true,
                    barHeight: '62%',
                    dataLabels: { position: 'center' }
                }
            },
            series: [{ name: 'Netto', data: [] }],
            xaxis: { categories: [], labels: { formatter: function (v) { return H.formatShort(v); } } },
            yaxis: { labels: { style: { fontSize: '11px' } } },
            colors: COVERAGE_COLORS,
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: function (v) { return v > 0 ? H.formatShort(v) : ''; },
                style: { fontSize: '11px', fontWeight: 700, colors: ['#fff'] },
                dropShadow: { enabled: true, blur: 2, opacity: 0.3 },
            },
            tooltip: { y: { formatter: function (v) { return H.formatRupiah(v); } }, theme: 'dark' },
            grid: { borderColor: '#f3f3f3', strokeDashArray: 4 },
        });
        chartCoverage.render();
    }

    function loadChartCoverage() {
        if (!chartCoverage) return;

        H.apiGet('chartCoverage', H.getFilters()).done(function (res) {
            chartCoverage.updateOptions({ xaxis: { categories: res.labels } });
            chartCoverage.updateSeries([{ name: 'Netto', data: res.data }]);
        });
    }

    function loadAll() {
        loadChartTrend();
        loadChartDivisi();
        loadChartPilar();
        loadChartCoverage();
    }

    return {
        init: init,
        loadAll: loadAll
    };
})(jQuery, Dashboard.Helpers);
