/**
 * Visit Heatmap page module.
 * Place at: public/js/visit-heatmap.js
 * Loaded via: <script src="{{ asset('js/visit-heatmap.js') }}"></script>
 *
 * Expects `window.VisitHeatmapConfig` to be set by the Blade view before
 * this file loads, e.g.:
 *   window.VisitHeatmapConfig = {
 *       dataUrl: "{{ route('visits.data') }}",
 *       defaultDate: "{{ date('Y-m-d') }}",
 *       initialNik: null,
 *   };
 *
 * Everything lives under one namespaced object instead of loose globals,
 * split into small sub-modules (Tiers, Map, Table, Modal, Filters) so each
 * concern can be read, tested, or reused on its own.
 */
(function (window, $, L) {
    'use strict';

    const VisitHeatmap = {};

    // ===== Config & DOM selectors (single source of truth) =====
    VisitHeatmap.config = Object.assign({
        dataUrl: null,
        defaultDate: '',
        initialNik: null,
    }, window.VisitHeatmapConfig || {});

    VisitHeatmap.selectors = {
        mapContainer: '#visitMap',
        mapCard: '#visitMapCard',
        loadingOverlay: '#visitMapLoading',
        emptyState: '#visitMapEmpty',
        filterDate: '#filter_visit_date',
        filterPilar: '#filter_pilar',
        btnApplyFilter: '#btnFilterVisit',
        btnResetFilter: '#btnResetVisit',
        btnToggleHeatmap: '#btnToggleHeatmap',
        btnToggleMarkers: '#btnToggleMarkers',
        chkHeatmapToggle: '#chkAqiHeatmapToggle',
        lnkLearnMore: '#lnkLearnMoreAqi',
        table: '#tblVisit',
        cardTotalVisits: '#card_total_visits',
        cardUniqueOutlets: '#card_unique_outlets',
        cardActiveSales: '#card_active_sales',
        modal: '#modalVisitDetail',
    };

    // ===== Visit intensity tiers (single source for color / badge / heat weight) =====
    VisitHeatmap.tiers = {
        list: [
            { min: 15, color: '#dc2626', badge: 'badge-danger', label: 'Sangat Sering', heat: 1.00 },
            { min: 10, color: '#ea580c', badge: 'badge-warning', label: 'Sering Dikunjungi', heat: 0.85 },
            { min: 5, color: '#eab308', badge: 'badge-warning', label: 'Sedang Dikunjungi', heat: 0.65 },
            { min: 2, color: '#16a34a', badge: 'badge-success', label: 'Jarang Dikunjungi', heat: 0.40 },
            { min: 0, color: '#0ea5b8', badge: 'badge-info', label: 'Minimal Visit', heat: 0.25 },
        ],
        get(count) {
            return this.list.find((tier) => count >= tier.min);
        },
        gradient() {
            return this.list.reduce((acc, tier) => {
                acc[tier.heat] = tier.color;
                return acc;
            }, {});
        },
    };

    // ===== Small shared utilities =====
    VisitHeatmap.utils = {
        truncate(text, length = 40) {
            if (!text) return '-';
            return text.length > length ? `${text.substring(0, length)}...` : text;
        },
        coordinateLink(lat, lng, className = 'small font-weight-bold text-primary') {
            if (!lat || !lng) return '<span class="text-muted">-</span>';
            return `<a href="https://maps.google.com/?q=${lat},${lng}" target="_blank" class="${className}">
                <i class="fas fa-external-link-alt mr-1"></i>${lat}, ${lng}
            </a>`;
        },
    };

    // ===== State shared across sub-modules =====
    VisitHeatmap.state = {
        visits: [],
    };

    // ===== Map sub-module =====
    VisitHeatmap.Map = {
        instance: null,
        heatLayer: null,
        markerGroup: null,
        showHeatmap: true,
        showMarkers: false,

        init() {
            const sel = VisitHeatmap.selectors;
            this.instance = L.map(sel.mapContainer.slice(1)).setView([-6.2088, 106.8456], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
            }).addTo(this.instance);

            this.markerGroup = L.layerGroup().addTo(this.instance);

            // Smart hybrid layer switcher: heatmap on zoom out, pin badges on zoom in.
            this.instance.on('zoomend', () => {
                this.showMarkers = this.instance.getZoom() >= 10;
                $(sel.btnToggleMarkers).toggleClass('active', this.showMarkers);
                this.render(VisitHeatmap.state.visits, false);
            });

            setTimeout(() => this.instance.invalidateSize(), 300);
        },

        groupByOutlet(visits) {
            const outletMap = {};
            const latLngs = [];

            visits.forEach((v) => {
                if (!v.latitude || !v.longitude) return;

                const lat = parseFloat(v.latitude);
                const lng = parseFloat(v.longitude);
                latLngs.push([lat, lng]);

                const key = v.customer_id || `${lat.toFixed(4)}_${lng.toFixed(4)}`;
                if (!outletMap[key]) {
                    outletMap[key] = {
                        lat, lng,
                        outlet_name: v.outlet_name || 'Outlet',
                        customer_id: v.customer_id || '-',
                        visits: [],
                        count: 0,
                    };
                }
                outletMap[key].count += 1;
                outletMap[key].visits.push(v);
            });

            return { outletMap, latLngs };
        },

        buildPopupHtml(item, tier) {
            const lastVisit = item.visits[0] || {};
            const { truncate } = VisitHeatmap.utils;
            return `
                <div class="visit-popup">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">${item.outlet_name}</strong>
                        <span class="badge ${tier.badge} font-weight-bolder ml-2">${item.count} Visit</span>
                    </div>
                    <div class="small text-muted mb-2">
                        Cust ID: ${item.customer_id} ·
                        <span class="font-weight-bold" style="color:${tier.color}">${tier.label}</span>
                    </div>
                    <hr />
                    <div class="small">
                        <i class="fas fa-user-tie text-primary mr-1"></i> ${lastVisit.sales_name || '-'} (${lastVisit.nik || '-'})<br/>
                        <i class="far fa-clock text-muted mr-1"></i> ${lastVisit.visit_date || ''} ${lastVisit.visit_time || ''}<br/>
                        <span class="text-dark font-italic">${truncate(lastVisit.visit_resume, 120)}</span>
                    </div>
                </div>
            `;
        },

        buildPinIcon(item, tier) {
            return L.divIcon({
                className: 'custom-pin-marker',
                iconSize: [40, 32],
                iconAnchor: [20, 32],
                popupAnchor: [0, -28],
                html: `
                    <div class="pin-badge-wrapper">
                        <div class="pin-badge-body" style="background-color: ${tier.color}">
                            <i class="fas fa-map-marker-alt text-white mr-1" style="font-size:10px;"></i>
                            <span>${item.count}</span>
                        </div>
                        <div class="pin-badge-arrow" style="border-top-color: ${tier.color}"></div>
                    </div>
                `,
            });
        },

        render(visits, shouldFitBounds = false) {
            const sel = VisitHeatmap.selectors;
            this.markerGroup.clearLayers();
            if (this.heatLayer && this.instance.hasLayer(this.heatLayer)) {
                this.instance.removeLayer(this.heatLayer);
            }

            const { outletMap, latLngs } = this.groupByOutlet(visits);
            const heatPoints = [];

            Object.values(outletMap).forEach((item) => {
                const tier = VisitHeatmap.tiers.get(item.count);
                heatPoints.push([item.lat, item.lng, tier.heat]);

                const marker = L.marker([item.lat, item.lng], {
                    icon: this.buildPinIcon(item, tier),
                }).bindPopup(this.buildPopupHtml(item, tier));
                this.markerGroup.addLayer(marker);
            });

            if (this.showHeatmap && heatPoints.length > 0) {
                try {
                    // Wider radius + heavier blur blend nearby points into
                    // smooth regional clouds instead of patchy dots.
                    this.heatLayer = L.heatLayer(heatPoints, {
                        radius: 34,
                        blur: 24,
                        maxZoom: 9,
                        max: 1.0,
                        minOpacity: 0.30,
                        gradient: VisitHeatmap.tiers.gradient(),
                    }).addTo(this.instance);
                } catch (e) {
                    console.warn('Heatmap engine warning:', e);
                }
            }

            if (this.showMarkers) {
                if (!this.instance.hasLayer(this.markerGroup)) this.markerGroup.addTo(this.instance);
            } else if (this.instance.hasLayer(this.markerGroup)) {
                this.instance.removeLayer(this.markerGroup);
            }

            if (shouldFitBounds && latLngs.length > 0) {
                setTimeout(() => {
                    this.instance.invalidateSize();
                    this.instance.fitBounds(L.latLngBounds(latLngs), { padding: [35, 35], maxZoom: 7 });
                }, 150);
            }

            $(sel.emptyState).toggleClass('is-visible', visits.length === 0);
        },

        toggleHeatmap(force) {
            const sel = VisitHeatmap.selectors;
            this.showHeatmap = typeof force === 'boolean' ? force : !this.showHeatmap;
            $(sel.btnToggleHeatmap).toggleClass('active', this.showHeatmap);
            $(sel.chkHeatmapToggle).prop('checked', this.showHeatmap);
            this.render(VisitHeatmap.state.visits, false);
        },

        toggleMarkers() {
            const sel = VisitHeatmap.selectors;
            this.showMarkers = !this.showMarkers;
            $(sel.btnToggleMarkers).toggleClass('active', this.showMarkers);
            this.render(VisitHeatmap.state.visits, false);
        },

        setLoading(isLoading) {
            $(VisitHeatmap.selectors.loadingOverlay).toggleClass('is-visible', isLoading);
        },
    };

    // ===== Table sub-module =====
    VisitHeatmap.Table = {
        instance: null,

        init() {
            const { truncate, coordinateLink } = VisitHeatmap.utils;

            this.instance = $(VisitHeatmap.selectors.table).DataTable({
                processing: true,
                serverSide: false,
                responsive: true,
                data: [],
                order: [[1, 'desc']],
                language: {
                    emptyTable: 'Belum ada data kunjungan untuk filter ini',
                    zeroRecords: 'Tidak ditemukan data yang cocok',
                },
                columns: [
                    { data: null, className: 'text-center', render: (d, t, r, meta) => meta.row + 1 },
                    {
                        data: null,
                        render: (d) => `<div><strong>${d.visit_date}</strong></div>
                                        <div class="small text-muted">${d.visit_time}</div>`,
                    },
                    {
                        data: null,
                        render: (d) => `<div><strong>${d.sales_name}</strong></div>
                                        <span class="badge badge-light-primary font-weight-bold">${d.nik}</span>`,
                    },
                    {
                        data: null,
                        render: (d) => `<div><strong>${d.outlet_name}</strong></div>
                                        <span class="badge badge-light-success font-weight-bold">${d.customer_id}</span>`,
                    },
                    {
                        data: 'pilar',
                        className: 'text-center',
                        render: (v) => `<span class="badge badge-info font-weight-bold">${v}</span>`,
                    },
                    { data: null, render: (d) => coordinateLink(d.latitude, d.longitude) },
                    { data: 'visit_resume', render: (v) => truncate(v) },
                    { data: 'customer_discussion', render: (v) => truncate(v) },
                    {
                        data: null,
                        className: 'text-center',
                        render: (d, t, row, meta) => `
                            <button type="button" class="btn btn-icon btn-sm btn-outline-primary font-weight-bolder btn-view-detail" data-idx="${meta.row}">
                                <i class="fas fa-eye icon-md"></i>
                            </button>`,
                    },
                ],
            });

            $(VisitHeatmap.selectors.table).on('click', '.btn-view-detail', (e) => {
                const idx = $(e.currentTarget).data('idx');
                const row = this.instance.row(idx).data();
                if (row) VisitHeatmap.Modal.open(row);
            });
        },

        setData(visits) {
            this.instance.clear().rows.add(visits).draw();
        },
    };

    // ===== Modal sub-module =====
    VisitHeatmap.Modal = {
        open(row) {
            const { coordinateLink } = VisitHeatmap.utils;

            $('#modal_sales_name').text(row.sales_name || '-');
            $('#modal_nik').text(row.nik || '-');
            $('#modal_branch').text(row.branch_name ? `${row.branch_name} (${row.branch_code})` : '-');
            $('#modal_outlet_name').text(row.outlet_name || '-');
            $('#modal_customer_id').text(`ID Konversi: ${row.id_konversi || row.customer_id || '-'}`);
            $('#modal_outlet_code').text(row.outlet_code ? `Code: ${row.outlet_code}` : '-');
            $('#modal_visit_datetime').text(`${row.visit_date} ${row.visit_time}`);
            $('#modal_pilar').text(row.pilar || '-');
            $('#modal_status').text(row.status || 'Selesai');
            $('#modal_coordinates').html(coordinateLink(row.latitude, row.longitude, 'font-weight-bold'));
            $('#modal_visit_resume').text(row.visit_resume || 'Tidak ada ringkasan kunjungan');
            $('#modal_customer_discussion').text(row.customer_discussion || 'Tidak ada catatan diskusi');

            if (Array.isArray(row.product_detailing) && row.product_detailing.length > 0) {
                const html = row.product_detailing.map((p) => `
                    <span class="badge badge-light-primary mr-2 mb-1 p-2 font-weight-bold">
                        <i class="fas fa-pills mr-1"></i>${p.name || p.code} <small class="text-muted">(${p.code})</small>
                    </span>`).join('');
                $('#modal_product_detailing').html(html);
            } else {
                $('#modal_product_detailing').text('Tidak ada product detailing');
            }

            $(VisitHeatmap.selectors.modal).modal('show');
        },
    };

    // ===== Filters & data loading =====
    VisitHeatmap.Filters = {
        load() {
            const sel = VisitHeatmap.selectors;
            const cfg = VisitHeatmap.config;
            const visitDate = $(sel.filterDate).val();
            const pilarValue = $(sel.filterPilar).val();

            VisitHeatmap.Map.setLoading(true);

            $.ajax({
                url: cfg.dataUrl,
                type: 'GET',
                data: {
                    visit_date: visitDate,
                    pilar: pilarValue,
                    search: { value: cfg.initialNik || '' },
                },
                success: (res) => {
                    if (res.status !== 'success') return;

                    VisitHeatmap.state.visits = res.data || [];
                    let filtered = VisitHeatmap.state.visits;

                    const pilars = Array.isArray(pilarValue) ? pilarValue : [pilarValue];
                    if (pilars.length > 0 && pilars[0] !== '') {
                        filtered = filtered.filter((v) => pilars.includes(v.pilar));
                    }
                    if (cfg.initialNik) {
                        filtered = filtered.filter((v) => v.nik.toLowerCase().includes(cfg.initialNik.toLowerCase()));
                    }

                    VisitHeatmap.state.visits = filtered;
                    VisitHeatmap.Summary.update(filtered);
                    VisitHeatmap.Table.setData(filtered);
                    VisitHeatmap.Map.render(filtered, true);

                    if (res.error_message) {
                        toastr.warning(res.error_message);
                    } else if (filtered.length === 0) {
                        toastr.info('Tidak ada data kunjungan pada tanggal ' + (visitDate || ''));
                    }
                },
                error: () => toastr.error('Gagal memuat data kunjungan'),
                complete: () => VisitHeatmap.Map.setLoading(false),
            });
        },

        reset() {
            const sel = VisitHeatmap.selectors;
            $(sel.filterDate).val(VisitHeatmap.config.defaultDate);
            $(sel.filterPilar).val('').trigger('change');
            this.load();
        },
    };

    // ===== Summary cards =====
    VisitHeatmap.Summary = {
        update(visits) {
            const sel = VisitHeatmap.selectors;
            $(sel.cardTotalVisits).text(visits.length);
            $(sel.cardUniqueOutlets).text(new Set(visits.map((v) => v.customer_id)).size);
            $(sel.cardActiveSales).text(new Set(visits.map((v) => v.nik)).size);
        },
    };

    // ===== Event bindings =====
    VisitHeatmap.bindEvents = function () {
        const sel = this.selectors;

        $(sel.btnApplyFilter).on('click', () => this.Filters.load());

        window.addEventListener('filter:reset', (event) => {
            if (event.detail && event.detail.id && event.detail.id !== 'btnResetVisit') return;
            this.Filters.reset();
        });

        $(sel.chkHeatmapToggle).on('change', (e) => this.Map.toggleHeatmap($(e.currentTarget).is(':checked')));
        $(sel.btnToggleHeatmap).on('click', () => this.Map.toggleHeatmap());
        $(sel.btnToggleMarkers).on('click', () => this.Map.toggleMarkers());

        $(sel.lnkLearnMore).on('click', () => {
            toastr.info(
                'Indeks Kepadatan Kunjungan dihitung dari akumulasi frekuensi kunjungan sales ke outlet di area tersebut. Skala: 0-4 (Rendah/Hijau), 5-11 (Sedang/Orange), 12+ (Tinggi/Merah).'
            );
        });
    };

    // ===== Bootstrap =====
    VisitHeatmap.init = function () {
        const urlParams = new URLSearchParams(window.location.search);
        this.config.initialNik = this.config.initialNik || urlParams.get('nik');

        this.Map.init();
        this.Table.init();
        this.bindEvents();
        this.Filters.load();
    };

    window.VisitHeatmap = VisitHeatmap;

    $(document).ready(() => VisitHeatmap.init());
})(window, jQuery, L);