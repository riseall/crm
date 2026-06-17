(function () {
    'use strict';

    const config = window.SellingInDashboardConfig || {};
    const ROUTES = config.routes || {};
    const CSRF = config.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const COVERAGE_COLORS = [
        '#4F46E5',
        '#0891B2',
        '#8B5CF6',
        '#EC4899',
        '#0EA5E9',
        '#7C3AED',
        '#06B6D4',
        '#F43F5E',
        '#3B82F6',
        '#A855F7',
    ];

    const PILAR_COLORS = {
        ETHIPROM: '#4F46E5',
        ETHICOM: '#1D4ED8',
        OGB: '#0891B2',
        OTC: '#EC4899',
        ALKES: '#A855F7',
        'TOLL IN': '#14B8A6',
        EXPORT: '#0EA5E9',
        LAINNYA: '#94A3B8',
    };

    const CHART_COLORS = {
        line: '#0EA5E9',
        gradientTo: '#6366F1',
        labelText: '#374151',
        grid: '#E5E7EB',
        fallback: '#94A3B8',
    };

    let chartTrend = null;
    let chartPilar = null;
    let chartTopPm = null;
    let chartMode = 'hari';
    let dataTable = null;

    function byId(id) {
        return document.getElementById(id);
    }

    function getFilters() {
        return {
            start_date: byId('filterStartDate').value,
            end_date: byId('filterEndDate').value,
            pilar: $('#filterPilar').val() || [],
            product: $('#filterProduct').val() || [],
            pm: $('#filterPm').val() || [],
        };
    }

    function formatRupiah(val) {
        if (val === null || val === undefined || val === '') return '-';
        return 'Rp ' + parseFloat(val || 0).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
    }

    function formatRupiahCompact(val) {
        if (val === null || val === undefined || val === '') return '-';

        const n = Number(val);
        const abs = Math.abs(n);

        if (abs >= 1000000000) return 'Rp ' + (n / 1000000000).toFixed(1).replace('.', ',') + ' M';
        if (abs >= 1000000) return 'Rp ' + (n / 1000000).toFixed(1).replace('.', ',') + ' jt';
        if (abs >= 1000) return 'Rp ' + (n / 1000).toFixed(0) + ' rb';

        return formatRupiah(n);
    }

    function formatGrowth(pct) {
        if (pct === null || pct === undefined) return '-';

        const sign = pct >= 0 ? '+' : '';
        const cls = pct >= 0 ? 'text-success' : 'text-danger';
        const icon = pct >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';

        return `<span class="${cls}"><i class="fas ${icon} fa-xs"></i> ${sign}${pct.toFixed(1)}%</span>`;
    }

    async function apiFetch(url, filters) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                Accept: 'application/json',
            },
            body: JSON.stringify(filters),
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        return res.json();
    }

    async function loadSummary(filters) {
        const data = await apiFetch(ROUTES.summary, filters);

        byId('kpiNetto').textContent = formatRupiah(data.total_netto);
        byId('kpiNettoSub').innerHTML = formatGrowth(data.diff_netto) + ' vs periode sebelumnya';
        byId('kpiYtd').textContent = formatRupiah(data.ytd_netto);
        byId('kpiYtdSub').textContent = data.ytd_period || '-';
        byId('kpiYtdGrowth').innerHTML = formatGrowth(data.ytd_growth);
        byId('kpiYtdGrowthSub').textContent = `YTD tahun lalu ${formatRupiah(data.last_year_ytd_netto)}`;
    }

    async function loadChartTrend(filters) {
        const url = chartMode === 'hari' ? ROUTES.chartHari : ROUTES.chartBulan;
        const data = await apiFetch(url, filters);
        const showLabel = data.data.length <= 31;
        const options = {
            chart: {
                type: 'area',
                height: 220,
                toolbar: { show: false },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 500,
                },
            },
            series: [{
                name: 'Nilai (Rp)',
                data: data.data,
            }],
            xaxis: {
                categories: data.labels,
                labels: {
                    rotate: 0,
                    style: { fontSize: '10px' },
                },
            },
            yaxis: {
                labels: {
                    formatter: value => formatRupiahCompact(value),
                },
            },
            colors: [CHART_COLORS.line],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.3,
                    gradientToColors: [CHART_COLORS.gradientTo],
                    opacityFrom: 0.45,
                    opacityTo: 0.02,
                },
            },
            stroke: {
                curve: 'smooth',
                width: 2.5,
                colors: [CHART_COLORS.line],
            },
            markers: {
                size: 4,
                colors: ['#fff'],
                strokeColors: CHART_COLORS.line,
                strokeWidth: 2,
                hover: { size: 6 },
            },
            dataLabels: {
                enabled: showLabel,
                formatter: value => value > 0 ? formatRupiahCompact(value) : '',
                style: {
                    fontSize: '10px',
                    colors: ['#1E1B4B'],
                    fontWeight: 700,
                },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderRadius: 4,
                    padding: 4,
                    opacity: 0.9,
                    borderColor: CHART_COLORS.line,
                    borderWidth: 1,
                },
                offsetY: -8,
            },
            tooltip: {
                y: { formatter: value => formatRupiah(value) },
                theme: 'light',
            },
            grid: {
                borderColor: CHART_COLORS.grid,
                strokeDashArray: 4,
            },
        };

        if (chartTrend) {
            chartTrend.updateOptions(options, true, true);
            return;
        }

        chartTrend = new ApexCharts(byId('chartTrend'), options);
        chartTrend.render();
    }

    async function loadChartPilar(filters) {
        const data = await apiFetch(ROUTES.chartPilar, filters);
        const el = byId('chartPilar');
        const legend = byId('legendPilar');

        if (!data.data.length) {
            if (chartPilar) {
                chartPilar.destroy();
                chartPilar = null;
            }

            el.innerHTML = '<p class="text-muted small">Tidak ada data.</p>';
            legend.innerHTML = '';
            return;
        }

        const colors = data.labels.map((label, index) => PILAR_COLORS[label] ?? COVERAGE_COLORS[index] ?? CHART_COLORS.fallback);
        const options = {
            chart: {
                type: 'polarArea',
                height: 300,
                toolbar: { show: false },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 500,
                },
            },
            series: data.data,
            labels: data.labels,
            colors,
            legend: { show: false },
            yaxis: { show: false },
            stroke: {
                width: 2,
                colors: ['#fff'],
            },
            plotOptions: {
                polarArea: {
                    rings: {
                        strokeWidth: 1,
                        strokeColor: CHART_COLORS.grid,
                    },
                    spokes: {
                        strokeWidth: 1,
                        connectorColors: CHART_COLORS.grid,
                    },
                },
            },
            dataLabels: {
                enabled: true,
                formatter: (value, opts) => {
                    const index = opts.seriesIndex ?? opts.dataPointIndex ?? 0;
                    const pct = data.pct[index] ?? value;
                    return `${pct}%`;
                },
                style: {
                    colors: ['#fff'],
                    fontSize: '10px',
                    fontWeight: 700,
                },
                dropShadow: {
                    enabled: true,
                    top: 1,
                    left: 0,
                    blur: 3,
                    opacity: 0.35,
                },
            },
            fill: { opacity: 0.92 },
            tooltip: {
                y: {
                    formatter: value => formatRupiah(value),
                },
            },
        };

        if (chartPilar) {
            chartPilar.updateOptions(options, true, true);
        } else {
            chartPilar = new ApexCharts(el, options);
            chartPilar.render();
        }

        legend.innerHTML = data.labels.map((label, index) => `
            <div class="col-6 p-1">
                <div class="si-pilar-legend-item d-flex align-items-start h-100">
                    <span class="pilar-dot mt-1 mr-2" style="background:${colors[index]}"></span>
                    <div>
                        <span class="d-block text-dark font-weight-bold" style="font-size: 12px;">${label} ${data.pct[index]}%</span>
                        <span class="si-pilar-legend-value text-muted" style="font-size: 11px;">${formatRupiah(data.data[index])}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async function loadTopPm(filters) {
        const data = await apiFetch(ROUTES.topPm, filters);
        const el = byId('chartTopPm');

        if (!data.length) {
            if (chartTopPm) {
                chartTopPm.destroy();
                chartTopPm = null;
            }

            el.innerHTML = '<p class="text-muted small">Tidak ada data.</p>';
            return;
        }

        const labels = data.map(pm => pm.name || pm.nik || '-');
        const colors = data.map((pm, index) => PILAR_COLORS[pm.pilar] ?? COVERAGE_COLORS[index] ?? CHART_COLORS.fallback);
        const options = {
            chart: {
                type: 'bar',
                height: 430,
                toolbar: { show: false },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 500,
                },
            },
            series: [{
                name: 'Selling In',
                data: data.map(pm => pm.total_netto),
            }],
            colors,
            plotOptions: {
                bar: {
                    horizontal: true,
                    distributed: true,
                    borderRadius: 6,
                    barHeight: '58%',
                },
            },
            dataLabels: {
                enabled: true,
                formatter: value => formatRupiahCompact(value),
                style: {
                    colors: ['#fff'],
                    fontSize: '10px',
                    fontWeight: 700,
                },
                dropShadow: {
                    enabled: true,
                    top: 1,
                    left: 0,
                    blur: 2,
                    opacity: 0.3,
                },
            },
            xaxis: {
                categories: labels,
                labels: {
                    formatter: value => formatRupiahCompact(value),
                    style: {
                        colors: '#8A92A6',
                        fontSize: '10px',
                    },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    maxWidth: 92,
                    style: {
                        colors: CHART_COLORS.labelText,
                        fontSize: '10px',
                        fontWeight: 600,
                    },
                },
            },
            grid: {
                borderColor: CHART_COLORS.grid,
                strokeDashArray: 4,
                xaxis: { lines: { show: true } },
                yaxis: { lines: { show: false } },
            },
            legend: { show: false },
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: value => formatRupiah(value),
                    title: { formatter: () => '' },
                },
            },
        };

        if (chartTopPm) {
            chartTopPm.updateOptions(options, true, true);
            return;
        }

        chartTopPm = new ApexCharts(el, options);
        chartTopPm.render();
    }

    async function loadTopProduk(filters) {
        const data = await apiFetch(ROUTES.topProduk, filters);
        const el = byId('chartTopProduk');

        if (!data.length) {
            el.innerHTML = '<p class="text-muted small">Tidak ada data.</p>';
            return;
        }

        const topData = data.slice(0, 10);
        const max = Math.max(...topData.map(product => Number(product.total_netto || 0))) || 1;
        const colors = topData.map((product, index) => PILAR_COLORS[product.pilar] ?? COVERAGE_COLORS[index] ?? CHART_COLORS.fallback);

        el.innerHTML = `
            <div class="si-funnel">
                ${topData.map((product, index) => {
            const value = Number(product.total_netto || 0);
            const width = Math.max(58, Math.round((value / max) * 100));
            const name = product.nama || product.kode_produk || '-';
            const pilar = product.pilar || '-';

            return `
                        <div class="si-funnel-stage" style="width:${width}%;background:${colors[index]}" title="${name} | ${formatRupiah(value)} | ${pilar}">
                            <div class="si-funnel-content">
                                <div class="si-funnel-title">${name}</div>
                                <div class="si-funnel-value">${formatRupiahCompact(value)} | ${product.pct || 0}%</div>
                            </div>
                        </div>
                    `;
        }).join('')}
            </div>
        `;
    }

    function initDataTable() {
        dataTable = $('#tableDetail').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: ROUTES.table,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                },
                data: d => Object.assign(d, getFilters()),
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (value, type, row, meta) => {
                        const no = meta.settings._iDisplayStart + meta.row + 1;
                        return type === 'display' ? `${no}.` : no;
                    },
                    className: 'text-center',
                },
                { data: 'tanggal' },
                { data: 'kode_produk' },
                { data: 'nama_produk' },
                {
                    data: 'pilar',
                    render: pilar => `<span class="badge text-white" style="background:${PILAR_COLORS[pilar] ?? CHART_COLORS.fallback};font-size:10px">${pilar}</span>`,
                    className: 'text-center',
                },
                {
                    data: 'pm_name',
                    render: (value, type, row) => {
                        const name = value || '-';
                        const nik = row.pm_nik && row.pm_nik !== '-' ? ` (${row.pm_nik})` : '';
                        return `${name}${nik}`;
                    },
                },
                {
                    data: 'qty',
                    className: 'text-end',
                    render: value => Number(value).toLocaleString('id-ID'),
                },
                {
                    data: 'harga',
                    className: 'text-end',
                    render: value => formatRupiah(value),
                },
                {
                    data: 'netto',
                    className: 'text-end fw-semibold',
                    render: value => formatRupiah(value),
                },
            ],
            order: [[8, 'desc']],
        });
    }

    async function loadAll() {
        const filters = getFilters();

        await Promise.all([
            loadSummary(filters),
            loadChartTrend(filters),
            loadChartPilar(filters),
            loadTopPm(filters),
            loadTopProduk(filters),
        ].map(job => job.catch(err => console.error(err))));

        if (dataTable) dataTable.ajax.reload();
    }

    function initProductFilter() {
        $('#filterProduct').select2({
            placeholder: 'Semua produk',
            closeOnSelect: false,
            width: '100%',
            ajax: {
                url: ROUTES.productLookup,
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term || '',
                }),
                processResults: data => data,
                cache: true,
            },
        });
    }

    function initPmFilter() {
        $('#filterPm').select2({
            placeholder: 'Semua PM',
            closeOnSelect: false,
            width: '100%',
            ajax: {
                url: ROUTES.pmLookup,
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term || '' }),
                processResults: data => ({ results: data.results }),
                cache: true,
            },
        });
    }

    function initChartModeToggle() {
        byId('chartModeToggle').addEventListener('click', event => {
            const btn = event.target.closest('button[data-mode]');
            if (!btn) return;

            document.querySelectorAll('#chartModeToggle button').forEach(button => button.classList.remove('active'));
            btn.classList.add('active');
            chartMode = btn.dataset.mode;

            const job = loadChartTrend(getFilters());
            if (window.CrmLoading && typeof window.CrmLoading.track === 'function') {
                window.CrmLoading.track(job);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        byId('btnApply').addEventListener('click', loadAll);
        initProductFilter();
        initPmFilter();
        initChartModeToggle();
        initDataTable();
        loadAll();
    });
})();
