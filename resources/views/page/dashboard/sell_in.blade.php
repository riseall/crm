@extends('layouts.app', ['title' => 'Monitoring Selling In'])

@section('content')
    <x-filter-bar :months="$months" :years="$years" :pilars="$pilars" title="Monitoring Selling In"
        subtitle="Pantau performa selling in berdasarkan data transaksi terbaru" icon="fa-chart-line" id-bulan="filterBulan"
        id-tahun="filterTahun" id-pilar="filterPilar" id-apply="btnApply" />


    {{-- ===================== KPI CARDS ===================== --}}
    <div class="row g-3 mb-4" id="kpiCards">
        @foreach ([['id' => 'kpiNetto', 'label' => 'Total Selling In', 'icon' => 'fa-money-bill-wave'], ['id' => 'kpiSku', 'label' => 'SKU Terjual', 'icon' => 'fa-box'], ['id' => 'kpiTransaksi', 'label' => 'Total Transaksi', 'icon' => 'fa-receipt']] as $card)
            <div class="col">
                <x-stat-card title="{{ $card['label'] }}" value="" valueId="{{ $card['id'] }}" subtitle="-"
                    subtitleId="{{ $card['id'] }}Sub" subtitleColor="metric-growth" icon="fas {{ $card['icon'] }}"
                    color="success" />
            </div>
        @endforeach
    </div>

    {{-- ===================== ROW 1: Tren + Pilar ===================== --}}
    <div class="card card-custom mb-4">
        <div class="card-header">
            <div class="card-title">
                <h3 class="card-label">Tren Selling In</h3>
            </div>
            <div class="card-toolbar">
                <div class="btn-group btn-group-sm" id="chartModeToggle">
                    <button class="btn btn-outline-secondary active" data-mode="hari">Harian</button>
                    <button class="btn btn-outline-secondary" data-mode="bulan">Bulanan (YTD)</button>
                </div>
            </div>
        </div>
        <div class="card-body p-3">
            <div id="chartTrend" style="height:220px"></div>
        </div>
    </div>

    {{-- ===================== ROW 2: Top PM + Top Produk + Dist ===================== --}}
    <div class="row g-3 mb-4 h-auto">
        <div class="col-md-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">by PM</h3>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small" id="topPmPeriod"></span>
                    </div>
                    <div id="chartTopPm" style="height:260px"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">by Pilar</h3>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="">
                        <div id="chartPilar" class="mb-3"></div>
                        <div id="legendPilar" class="row no-gutters" style="gap: 8px 0;"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">by Produk</h3>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div id="chartTopProduk" style="height:260px"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== TABEL DETAIL ===================== --}}
    <x-datatable-card title="Detail transaksi" subtitle="" table-id="tableDetail" table-class="table-sm">
        <th>No</th>
        <th>Tanggal</th>
        <th>Kode</th>
        <th>Nama Produk</th>
        <th>Pilar</th>
        <th class="text-end">Qty</th>
        <th class="text-end">Harga</th>
        <th class="text-end">Netto</th>
    </x-datatable-card>
@endsection

@push('styles')
    <style>
        .pilar-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.85);
        }

        .si-bar-track {
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #EEF2FF;
        }

        .si-bar-fill {
            height: 100%;
            border-radius: inherit;
            box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.10);
            transition: width .3s ease;
        }

        .si-pilar-chart {
            height: 220px;
        }

        .si-pilar-legend-item {
            padding: 6px 8px;
            border: 1px solid #E0E7FF;
            border-radius: 8px;
            background: #FAFAFA;
            line-height: 1.2;
            box-sizing: border-box;
        }

        .si-pilar-legend-value {
            color: #6B7280;
            font-size: 10px;
        }

        .si-pilar-tooltip {
            padding: 6px 9px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.90);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.18);
        }

        .si-funnel {
            display: grid;
            gap: 6px;
            min-height: 260px;
            align-content: center;
        }

        .si-funnel-stage {
            position: relative;
            min-height: 36px;
            margin-right: auto;
            margin-left: auto;
            overflow: hidden;
            border-radius: 4px;
            color: #fff;
            clip-path: polygon(7% 0, 93% 0, 100% 100%, 0 100%);
            box-shadow: inset 0 -1px 0 rgba(15, 23, 42, 0.16);
        }

        .si-funnel-stage:last-child {
            clip-path: polygon(0 0, 100% 0, 58% 100%, 42% 100%);
        }

        .si-funnel-content {
            display: grid;
            height: 100%;
            min-height: 36px;
            align-content: center;
            justify-items: center;
            padding: 5px 18px;
            text-align: center;
            text-shadow: 0 1px 2px rgba(15, 23, 42, 0.22);
        }

        .si-funnel-title {
            width: 100%;
            overflow: hidden;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.15;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .si-funnel-value {
            font-size: 10px;
            font-weight: 600;
            line-height: 1.15;
            opacity: .95;
        }

        @media (max-width: 575.98px) {
            .si-pilar-chart {
                height: 200px;
            }

            .si-funnel-title,
            .si-funnel-value {
                font-size: 9px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        // -------------------------------------------------------
        // CONFIG & STATE
        // -------------------------------------------------------
        const ROUTES = {
            summary: '{{ route('selling-in.summary') }}',
            chartHari: '{{ route('selling-in.chart.hari') }}',
            chartBulan: '{{ route('selling-in.chart.bulan') }}',
            chartPilar: '{{ route('selling-in.chart.pilar') }}',
            topPm: '{{ route('selling-in.top-pm') }}',
            topProduk: '{{ route('selling-in.top-produk') }}',
            table: '{{ route('selling-in.table') }}',
        };

        const COVERAGE_COLORS = [
            '#4F46E5', // indigo
            '#0891B2', // cyan
            '#8B5CF6', // violet
            '#EC4899', // pink
            '#0EA5E9', // sky
            '#7C3AED', // purple
            '#06B6D4', // teal-cyan
            '#F43F5E', // rose
            '#3B82F6', // blue
            '#A855F7', // fuchsia
        ];

        const PILAR_COLORS = {
            ETHICAL: '#4F46E5', // indigo
            GENERIK: '#0891B2', // cyan-600
            OGB: '#0891B2', // cyan-600
            OTC: '#8B5CF6', // violet
            ALKES: '#A855F7', // fuchsia
            'TOLL IN': '#14B8A6', // teal
            EXPORT: '#0EA5E9', // sky
            LAINNYA: '#94A3B8', // slate
        };

        const CHART_COLORS = {
            line: '#0EA5E9', // sky-500
            gradientTo: '#6366F1', // indigo-500
            labelText: '#374151',
            grid: '#E5E7EB',
            fallback: '#94A3B8',
        };

        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        let chartTrend = null;
        let chartPilar = null;
        let chartTopPm = null;
        let chartMode = 'hari'; // 'hari' | 'bulan'
        let dataTable = null;

        // -------------------------------------------------------
        // HELPERS
        // -------------------------------------------------------

        function getFilters() {
            return {
                bulan: document.getElementById('filterBulan').value,
                tahun: document.getElementById('filterTahun').value,
                pilar: document.getElementById('filterPilar').value,
            };
        }

        function formatRupiah(val) {
            if (val === null || val === undefined || val === '') return '—';
            return 'Rp ' + parseFloat(val || 0).toLocaleString('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            });
        }

        function formatRupiahCompact(val) {
            if (val === null || val === undefined || val === '') return '—';
            const n = Number(val);
            const abs = Math.abs(n);
            if (abs >= 1_000_000_000) return 'Rp ' + (n / 1_000_000_000).toFixed(1).replace('.', ',') + ' M';
            if (abs >= 1_000_000) return 'Rp ' + (n / 1_000_000).toFixed(1).replace('.', ',') + ' jt';
            if (abs >= 1_000) return 'Rp ' + (n / 1_000).toFixed(0) + ' rb';
            return formatRupiah(n);
        }

        function formatGrowth(pct) {
            if (pct === null || pct === undefined) return '—';
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
                    'Accept': 'application/json',
                },
                body: JSON.stringify(filters),
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        }

        // -------------------------------------------------------
        // SUMMARY CARDS
        // -------------------------------------------------------

        async function loadSummary(filters) {
            const data = await apiFetch(ROUTES.summary, filters);

            document.getElementById('kpiNetto').textContent = formatRupiah(data.total_netto);
            document.getElementById('kpiNettoSub').innerHTML = formatGrowth(data.diff_netto) + ' vs bln lalu';
            document.getElementById('kpiSku').textContent = data.total_sku?.toLocaleString('id-ID') ?? '—';
            document.getElementById('kpiTransaksi').textContent = data.total_transaksi?.toLocaleString('id-ID') ?? '—';
        }

        // -------------------------------------------------------
        // CHART TREN
        // -------------------------------------------------------

        async function loadChartTrend(filters) {
            const url = chartMode === 'hari' ? ROUTES.chartHari : ROUTES.chartBulan;
            const data = await apiFetch(url, filters);
            const el = document.getElementById('chartTrend');
            const showLabel = chartMode === 'hari' || data.data.length <= 15;
            const options = {
                chart: {
                    type: 'area',
                    height: 220,
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 500
                    },
                },
                series: [{
                    name: 'Nilai (Rp)',
                    data: data.data
                }],
                xaxis: {
                    categories: data.labels,
                    labels: {
                        rotate: 0,
                        style: {
                            fontSize: '10px'
                        }
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
                    colors: [CHART_COLORS.line]
                },
                markers: {
                    size: 4,
                    colors: ['#fff'],
                    strokeColors: CHART_COLORS.line,
                    strokeWidth: 2,
                    hover: {
                        size: 6
                    },
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
                    y: {
                        formatter: value => formatRupiah(value),
                    },
                    theme: 'light',
                },
                grid: {
                    borderColor: CHART_COLORS.grid,
                    strokeDashArray: 4
                },
            };

            if (chartTrend) {
                chartTrend.updateOptions(options, true, true);
                return;
            }

            chartTrend = new ApexCharts(el, options);
            chartTrend.render();
        }

        // -------------------------------------------------------
        // CHART PILAR (POLAR AREA)
        // -------------------------------------------------------

        async function loadChartPilar(filters) {
            const data = await apiFetch(ROUTES.chartPilar, filters);
            const el = document.getElementById('chartPilar');

            if (!data.data.length) {
                if (chartPilar) {
                    chartPilar.destroy();
                    chartPilar = null;
                }
                el.innerHTML = '<p class="text-muted small">Tidak ada data.</p>';
                legendPilar.innerHTML = '';
                return;
            }
            const colors = data.labels.map((label, index) => PILAR_COLORS[label] ?? COVERAGE_COLORS[index] ??
                CHART_COLORS.fallback);
            const options = {
                chart: {
                    type: 'polarArea',
                    height: 300,
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 500
                    },
                },
                series: data.data,
                labels: data.labels,
                colors,
                legend: {
                    show: false
                },
                yaxis: {
                    show: false,
                },
                stroke: {
                    width: 2,
                    colors: ['#fff']
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
                fill: {
                    opacity: 0.92,
                },
                tooltip: {
                    y: {
                        formatter: (value, opts) => {
                            const index = opts.seriesIndex ?? opts.dataPointIndex ?? 0;
                            return `${formatRupiah(value)}`;
                        },
                    },
                },
            };

            if (chartPilar) {
                chartPilar.updateOptions(options, true, true);
            } else {
                chartPilar = new ApexCharts(el, options);
                chartPilar.render();
            }

            // Custom legend
            const legend = document.getElementById('legendPilar');
            legend.innerHTML = data.labels.map((l, i) => `
            <div class="col-6 p-1">
                <div class="si-pilar-legend-item d-flex align-items-start h-100">
                    <span class="pilar-dot mt-1 mr-2" style="background:${colors[i]}"></span>
                    <div>
                        <span class="d-block text-dark font-weight-bold" style="font-size: 12px;">${l} ${data.pct[i]}%</span>
                        <span class="si-pilar-legend-value text-muted" style="font-size: 11px;">${formatRupiah(data.data[i])}</span>
                    </div>
                </div>
            </div>
    `).join('');
        }

        // -------------------------------------------------------
        // TOP PM
        // -------------------------------------------------------

        async function loadTopPm(filters) {
            const data = await apiFetch(ROUTES.topPm, filters);
            const el = document.getElementById('chartTopPm');

            if (!data.length) {
                if (chartTopPm) {
                    chartTopPm.destroy();
                    chartTopPm = null;
                }
                el.innerHTML = '<p class="text-muted small">Tidak ada data.</p>';
                return;
            }

            const height = Math.max(260, data.length * 34);
            const labels = data.map(pm => pm.name || pm.nik || '-');
            const colors = data.map((pm, index) => PILAR_COLORS[pm.pilar] ?? COVERAGE_COLORS[index] ?? CHART_COLORS
                .fallback);
            const options = {
                chart: {
                    type: 'bar',
                    height,
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 500
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
                        barHeight: '62%',
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
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
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
                    xaxis: {
                        lines: {
                            show: true
                        }
                    },
                    yaxis: {
                        lines: {
                            show: false
                        }
                    },
                },
                legend: {
                    show: false
                },
                fill: {
                    opacity: 1,
                },
                tooltip: {
                    y: {
                        formatter: value => formatRupiah(value),
                        title: {
                            formatter: () => '',
                        },
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

        // -------------------------------------------------------
        // TOP PRODUK
        // -------------------------------------------------------

        async function loadTopProduk(filters) {
            const data = await apiFetch(ROUTES.topProduk, filters);
            const el = document.getElementById('chartTopProduk');

            if (!data.length) {
                el.innerHTML = '<p class="text-muted small">Tidak ada data.</p>';
                return;
            }

            const max = Math.max(...data.map(p => Number(p.total_netto || 0))) || 1;
            const colors = data.map((p, index) => PILAR_COLORS[p.pilar] ?? COVERAGE_COLORS[index] ?? CHART_COLORS
                .fallback);
            el.innerHTML = `
                <div class="si-funnel">
                    ${data.map((p, i) => {
                        const value = Number(p.total_netto || 0);
                        const width = Math.max(42, Math.round((value / max) * 100));
                        const name = p.nama || p.kode_produk || '-';
                        const shortName = name.length > 24 ? `${name.slice(0, 24)}...` : name;
                        const pilar = p.pilar || '-';

                        return `
                                        <div class="si-funnel-stage" style="width:${width}%;background:${colors[i]}" title="${name} | ${formatRupiah(value)} | ${pilar}">
                                            <div class="si-funnel-content">
                                                <div class="si-funnel-title">${shortName}</div>
                                                <div class="si-funnel-value">${formatRupiahCompact(value)} · ${p.pct || 0}%</div>
                                            </div>
                                        </div>
                                    `;
                    }).join('')}
                </div>
            `;
        }

        // -------------------------------------------------------
        // DATATABLE
        // -------------------------------------------------------

        function initDataTable() {
            dataTable = $('#tableDetail').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: ROUTES.table,
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF
                    },
                    data: d => Object.assign(d, getFilters()),
                },
                columns: [{
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: (v, t, r, m) => {
                            const no = m.settings._iDisplayStart + m.row + 1;
                            if (t === 'display') {
                                return `${no}.`;
                            }
                            return no;
                        },
                        className: 'text-center'
                    },
                    {
                        data: 'tanggal'
                    },
                    {
                        data: 'kode_produk'
                    },
                    {
                        data: 'nama_produk'
                    },
                    {
                        data: 'pilar',
                        render: p =>
                            `<span class="badge text-white" style="background:${PILAR_COLORS[p]??CHART_COLORS.fallback};font-size:10px">${p}</span>`,
                        className: 'text-center'
                    },
                    {
                        data: 'qty',
                        className: 'text-end',
                        render: v => Number(v).toLocaleString('id-ID')
                    },
                    {
                        data: 'harga',
                        className: 'text-end',
                        render: v => formatRupiah(v)
                    },
                    {
                        data: 'netto',
                        className: 'text-end fw-semibold',
                        render: v => formatRupiah(v)
                    },
                ],
                order: [
                    [7, 'desc']
                ],
                // language: {
                //     url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                // },
            });
        }

        // -------------------------------------------------------
        // LOAD ALL
        // -------------------------------------------------------

        async function loadAll() {
            const filters = getFilters();

            // Semua endpoint dipanggil parallel kecuali DataTable (dia punya mekanisme sendiri)
            await Promise.all([
                loadSummary(filters),
                loadChartTrend(filters),
                loadChartPilar(filters),
                loadTopPm(filters),
                loadTopProduk(filters),
            ].map(p => p.catch(err => console.error(err))));

            // Reload DataTable dengan filter terbaru
            if (dataTable) dataTable.ajax.reload();
        }

        // -------------------------------------------------------
        // EVENT LISTENERS
        // -------------------------------------------------------

        document.getElementById('btnApply').addEventListener('click', loadAll);

        document.getElementById('chartModeToggle').addEventListener('click', e => {
            const btn = e.target.closest('button[data-mode]');
            if (!btn) return;

            document.querySelectorAll('#chartModeToggle button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            chartMode = btn.dataset.mode;
            loadChartTrend(getFilters());
        });

        // -------------------------------------------------------
        // INIT
        // -------------------------------------------------------

        document.addEventListener('DOMContentLoaded', () => {
            initDataTable();
            loadAll();
        });
    </script>
@endpush
