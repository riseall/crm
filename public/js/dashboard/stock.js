(function () {
    "use strict";

    const config = window.StockDashboardConfig || {};
    const ROUTES = config.routes || {};
    const H = window.Dashboard && window.Dashboard.Helpers;
    const CSRF =
        config.csrf ||
        document.querySelector('meta[name="csrf-token"]')?.content ||
        "";

    const AGING_COLORS = {
        "Sudah ED": "#F43F5E", // rose-500    — kritis
        "1-3 Bln": "#F97316", // orange-500  — sangat mendesak
        "1–3 Bln": "#F97316",
        "4-7 Bln": "#FBBF24", // amber-400   — mendesak
        "4–7 Bln": "#FBBF24",
        "8-12 Bln": "#8B5CF6", // violet-500  — perlu perhatian
        "8–12 Bln": "#8B5CF6",
        "13-17 Bln": "#06B6D4", // cyan-500    — aman
        "13–17 Bln": "#06B6D4",
        ">18 Bln": "#4F46E5", // indigo-600  — sangat aman
    };

    const AGING_PILL = {
        gt_18: ["#EEF2FF", "#3730A3", ">18 Bln"], // indigo tint
        "13_17": ["#ECFEFF", "#0E7490", "13-17 Bln"], // cyan tint
        "8_12": ["#F5F3FF", "#6D28D9", "8-12 Bln"], // violet tint
        "4_7": ["#FFFBEB", "#B45309", "4-7 Bln"], // amber tint
        "1_3": ["#FFF7ED", "#C2410C", "1-3 Bln"], // orange tint
        sudah_ed: ["#FFF1F2", "#BE123C", "Sudah ED"], // rose tint
    };

    const SRC_STYLE = {
        GPJ: { bg: "#EEF2FF", tc: "#3730A3" }, // indigo
        AAM: { bg: "#ECFEFF", tc: "#0E7490" }, // cyan
        KFTD: { bg: "#FFF7ED", tc: "#C2410C" }, // orange
        METD: { bg: "#F5F3FF", tc: "#6D28D9" }, // violet
    };

    const SRC_COLOR = {
        GPJ: "#4F46E5", // indigo-600
        AAM: "#0891B2", // cyan-600
        KFTD: "#F97316", // orange-500
        METD: "#8B5CF6", // violet-500
    };

    const CHART_HEIGHT = 280;
    let chartAging = null;
    let chartTopCabang = null;
    let dataTable = null;

    function byId(id) {
        return document.getElementById(id);
    }

    function esc(value) {
        return String(value ?? "").replace(
            /[&<>"']/g,
            (char) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#039;",
                })[char],
        );
    }

    function fmtVal(value) {
        if (value === null || value === undefined || value === "") return "-";
        if (H && typeof H.formatShort === "function") return H.formatShort(value);

        const n = Number(value);
        if (Number.isNaN(n)) return "-";
        if (Math.abs(n) >= 1000000000)
            return (n / 1000000000).toFixed(1).replace(".", ",") + " Mrd";
        if (Math.abs(n) >= 1000000)
            return (n / 1000000).toFixed(1).replace(".", ",") + " M";
        if (Math.abs(n) >= 1000) return (n / 1000).toFixed(0) + " rb";

        return n.toLocaleString("id-ID");
    }

    function fmtInt(value) {
        if (H && typeof H.formatInt === "function") return H.formatInt(value);

        return Number(value || 0).toLocaleString("id-ID");
    }

    function getFilters() {
        return {
            source: byId("filterSource").value,
            pilar: byId("filterPilar").value,
            cabang: byId("filterCabang").value,
            kelompok_ed: byId("filterKelEd").value,
        };
    }

    async function apiFetch(url, data = {}, method = "POST") {
        const options = {
            method,
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
                Accept: "application/json",
            },
        };

        if (method === "POST") {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        return response.json();
    }

    async function loadSummary(filters) {
        const data = await apiFetch(ROUTES.summary, filters);

        byId("kpiGpj").textContent = fmtVal(data.gpj_value);
        byId("kpiGpjSub").textContent =
            `${fmtInt(data.total_sku)} total SKU`;
        byId("kpiCabang").textContent = fmtVal(data.cabang_value);
        byId("kpiCabangSub").textContent = "AAM, KFTD, METD";
        byId("kpiKritis").textContent = fmtVal(data.kritis_value);
        byId("kpiKritisSub").textContent =
            `${fmtInt(data.kritis_skus)} SKU perlu tindakan`;
        byId("kpiEd").textContent = fmtVal(data.sudah_ed_value);
        byId("kpiEdSub").textContent =
            `${fmtInt(data.sudah_ed_skus)} SKU perlu disposal`;
    }

    async function loadChartAging(filters) {
        const data = await apiFetch(ROUTES.chartAging, filters);
        const agingRows = data.labels
            .map((label, index) => ({
                label,
                value: Number(data.values[index] || 0),
                pct: data.pcts[index],
                sku: data.skus[index],
                color: AGING_COLORS[label] || "#94A3B8",
            }))
            .sort((a, b) => b.value - a.value);
        const labels = agingRows.map((row) => row.label);
        const values = agingRows.map((row) => row.value);
        const colors = agingRows.map((row) => row.color);
        const options = {
            chart: {
                type: "bar",
                height: CHART_HEIGHT,
                parentHeightOffset: 0,
                redrawOnParentResize: true,
                redrawOnWindowResize: true,
                toolbar: { show: false },
            },
            series: [{ name: "Nilai", data: values }],
            xaxis: {
                categories: labels,
                labels: {
                    formatter: (value) => fmtVal(value),
                    style: { fontSize: "10px" },
                },
            },
            yaxis: {
                labels: {
                    formatter: (value) => {
                        const label = String(value || "");
                        return label.length > 12
                            ? `${label.slice(0, 12)}...`
                            : label;
                    },
                    style: { fontSize: "10px", fontWeight: 600 },
                },
            },
            colors,
            dataLabels: {
                enabled: true,
                formatter: (value, opts) => {
                    const row = agingRows[opts.dataPointIndex];
                    return row?.pct ? `${row.pct}%` : "";
                },
                offsetY: -18,
                style: {
                    fontSize: "10px",
                    fontWeight: 700,
                    colors: ["#3F4254"],
                },
            },
            legend: { show: false },
            plotOptions: {
                bar: {
                    horizontal: true,
                    distributed: true,
                    borderRadius: 4,
                    barHeight: "56%",
                    dataLabels: { position: "center" },
                },
            },
            grid: {
                borderColor: "#E5E7EB",
                strokeDashArray: 4,
                padding: {
                    top: 0,
                    right: 8,
                    bottom: 0,
                    left: 8,
                },
            },
            tooltip: {
                y: {
                    formatter: (value, opts) => {
                        const row = agingRows[opts.dataPointIndex] || {};
                        return `${fmtVal(value)} | ${row.pct || 0}% | ${row.sku || 0} SKU`;
                    },
                },
            },
        };

        if (chartAging) {
            chartAging.updateOptions(options, true, true);
        } else {
            chartAging = new ApexCharts(byId("chartAging"), options);
            chartAging.render();
        }
    }

    async function loadPerSource(filters) {
        const data = await apiFetch(ROUTES.perSource, filters);
        const max = Math.max(
            ...data.map((row) => Number(row.total_value || 0)),
            1,
        );

        byId("sourceList").innerHTML = data
            .map((row) => {
                const style = SRC_STYLE[row.source] || {
                    bg: "#F8FAFC",
                    tc: "#475569",
                };
                const pct = Math.round(
                    (Number(row.total_value || 0) / max) * 100,
                );

                return `
                <div class="sk-list-item d-flex align-items-center p-3 mb-2">
                    <div class="sk-src-badge mr-3" style="background:${style.bg};color:${style.tc}">${esc(row.source)}</div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between font-size-sm mb-1">
                            <span class="text-muted">${fmtInt(row.total_sku)} SKU, ${fmtInt(row.total_stock)} unit</span>
                            <span class="font-weight-bold">${fmtVal(row.total_value)}</span>
                        </div>
                        <div class="sk-bar-track">
                            <div class="sk-bar-fill" style="width:${pct}%;background:${SRC_COLOR[row.source] || "#94A3B8"}"></div>
                        </div>
                        <div class="text-muted font-size-xs mt-1">${row.pct}% dari total</div>
                    </div>
                </div>
            `;
            })
            .join("");
    }

    async function loadTopCabang(filters) {
        const data = await apiFetch(ROUTES.topCabang, filters);
        const labels = data.map((row) => row.cabang || "-");
        const values = data.map((row) => Number(row.total_value || 0));
        const options = {
            chart: {
                type: "bar",
                height: CHART_HEIGHT,
                parentHeightOffset: 0,
                redrawOnParentResize: true,
                redrawOnWindowResize: true,
                toolbar: { show: false },
            },
            series: [{ name: "Nilai", data: values }],
            xaxis: {
                categories: labels,
                labels: {
                    rotate: -35,
                    trim: true,
                    formatter: (value) => {
                        const label = String(value || "");
                        return label.length > 12
                            ? `${label.slice(0, 12)}...`
                            : label;
                    },
                    style: { fontSize: "10px", fontWeight: 600 },
                },
            },
            yaxis: {
                labels: {
                    formatter: (value) => fmtVal(value),
                    style: { fontSize: "10px" },
                },
            },
            colors: ["#e54690"],
            dataLabels: {
                enabled: true,
                formatter: (value) => fmtVal(value),
                offsetY: -18,
                style: {
                    fontSize: "10px",
                    fontWeight: 700,
                    colors: ["#3F4254"],
                },
            },
            legend: { show: false },
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 4,
                    columnWidth: "52%",
                    dataLabels: { position: "top" },
                },
            },
            tooltip: {
                custom: ({ dataPointIndex }) => {
                    const row = data[dataPointIndex] || {};
                    return `
                        <div class="px-3 py-2">
                            <div class="font-weight-bold">${esc(row.cabang || "-")}</div>
                            <div class="text-muted font-size-xs">${esc(row.sources || "AAM, KFTD, METD")}</div>
                            <div class="mt-1">${fmtVal(row.total_value)}</div>
                        </div>
                    `;
                },
            },
            grid: {
                borderColor: "#E5E7EB",
                strokeDashArray: 4,
                padding: {
                    top: 16,
                    right: 12,
                    bottom: 0,
                    left: 8,
                },
            },
            noData: {
                text: "Tidak ada data",
                align: "center",
                verticalAlign: "middle",
                style: { color: "#64748B", fontSize: "12px" },
            },
        };

        if (chartTopCabang) {
            chartTopCabang.updateOptions(options, true, true);
        } else {
            chartTopCabang = new ApexCharts(byId("chartTopCabang"), options);
            chartTopCabang.render();
        }
    }

    function initDataTable() {
        dataTable = $("#tableStock").DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: ROUTES.table,
                type: "POST",
                headers: {
                    "X-CSRF-TOKEN": CSRF,
                },
                data: (data) => Object.assign(data, getFilters()),
            },
            columns: [
                {
                    data: "source",
                    render: (source) => {
                        const style = SRC_STYLE[source] || {
                            bg: "#F8FAFC",
                            tc: "#475569",
                        };
                        return `<span class="font-size-xs font-weight-bold px-3 py-1 rounded" style="background:${style.bg};color:${style.tc}">${esc(source)}</span>`;
                    },
                },
                { data: "pt_part", className: "text-muted font-size-sm" },
                { data: "pt_desc" },
                { data: "pilar" },
                { data: "cabang" },
                {
                    data: "stock",
                    className: "text-right",
                    render: (value) => fmtInt(value),
                },
                {
                    data: "value",
                    className: "text-right font-weight-bold",
                    render: (value) => fmtVal(value),
                },
                { data: "exp_date" },
                {
                    data: "kelompok_ed",
                    render: (key) => {
                        const pill = AGING_PILL[key] || [
                            "#F8FAFC",
                            "#475569",
                            key,
                        ];
                        return `<span class="font-size-xs font-weight-bold px-3 py-1 rounded" style="background:${pill[0]};color:${pill[1]}">${esc(pill[2])}</span>`;
                    },
                },
            ],
            order: [[6, "desc"]],
        });
    }

    async function loadFilterOptions() {
        const data = await apiFetch(ROUTES.filterOptions, {}, "GET");
        const cabangEl = byId("filterCabang");
        const pilarEl = byId("filterPilar");

        data.cabangs.forEach((cabang) => {
            cabangEl.appendChild(new Option(cabang, cabang));
        });

        data.pilars.forEach((pilar) => {
            pilarEl.appendChild(new Option(pilar, pilar));
        });

        $(".selectpicker").selectpicker("refresh");
    }

    async function loadAll() {
        const filters = getFilters();

        const results = await Promise.allSettled([
            loadSummary(filters),
            loadChartAging(filters),
            loadPerSource(filters),
            loadTopCabang(filters),
        ]);

        const failed = results.some((result) => result.status === "rejected");
        if (failed) {
            console.warn("Sebagian data dashboard stock gagal dimuat");
        }

        if (dataTable) {
            dataTable.ajax.reload();
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        if (typeof ApexCharts === "undefined") {
            console.warn("ApexCharts tidak tersedia");
            return;
        }

        initDataTable();
        byId("btnApply").addEventListener("click", loadAll);
        loadFilterOptions().then(loadAll);
    });
})();
