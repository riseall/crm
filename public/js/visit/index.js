$(document).ready(function() {
    'use strict';

    const config = window.VisitPageConfig || {
        initialNik: '',
        initialName: '',
        isDetail: false,
        today: new Date().toISOString().slice(0, 10),
        routes: {
            data: '/visits/data',
            dataBySales: '/visits/data-by-sales'
        }
    };

    const VISIT_TIERS = [{
            min: 15,
            color: '#dc2626',
            badge: 'badge-danger',
            label: 'Sangat Sering',
            heat: 1.00
        },
        {
            min: 10,
            color: '#ea580c',
            badge: 'badge-warning',
            label: 'Sering Dikunjungi',
            heat: 0.85
        },
        {
            min: 5,
            color: '#eab308',
            badge: 'badge-warning',
            label: 'Sedang Dikunjungi',
            heat: 0.65
        },
        {
            min: 2,
            color: '#16a34a',
            badge: 'badge-success',
            label: 'Jarang Dikunjungi',
            heat: 0.40
        },
        {
            min: 0,
            color: '#0ea5b8',
            badge: 'badge-info',
            label: 'Minimal Visit',
            heat: 0.25
        },
    ];

    const getVisitTier = (count) => VISIT_TIERS.find(tier => count >= tier.min);

    const heatmapGradient = () =>
        VISIT_TIERS.reduce((gradient, tier) => {
            gradient[tier.heat] = tier.color;
            return gradient;
        }, {});

    /** Format numeric value to Rupiah currency string. */
    const formatRupiah = (val) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(val || 0);
    };

    /** Render a "lat, lng" value as a clickable Google Maps link. */
    const coordinateLink = (lat, lng, className = 'small font-weight-bold text-primary') => {
        if (!lat || !lng) return '<span class="text-muted">-</span>';
        return `<a href="https://maps.google.com/?q=${lat},${lng}" target="_blank" class="${className}">
            <i class="fas fa-external-link-alt mr-1"></i>${lat}, ${lng}
        </a>`;
    };

    // ===== State =====
    let map = null;
    let heatLayer = null;
    const heatGroup = L.layerGroup();
    const markerGroup = L.layerGroup();
    let showHeatmap = true;
    let showMarkers = false;
    let tblVisit = null;
    let visitDataStore = [];
    let currentFilteredVisits = []; // Store the filtered view

    // Read NIK & Name filter parameter if in detail mode
    const urlParams = new URLSearchParams(window.location.search);
    const initialNik = urlParams.get('nik') || config.initialNik || '';
    const initialName = config.initialName || '';

    initMap();
    initDatatable();
    loadVisitData();

    function initMap() {
        // Initial view focused on Java / Indonesia
        map = L.map('visitMap').setView([-6.2088, 106.8456], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
        }).addTo(map);

        heatGroup.addTo(map);
        markerGroup.addTo(map);

        // Smart hybrid layer switcher: continuous heatmap on zoom out,
        // custom pin badges on zoom in.
        map.on('zoomend', function() {
            showMarkers = map.getZoom() >= 10;
            $('#btnToggleMarkers').toggleClass('active', showMarkers);
            if (currentFilteredVisits.length > 0) {
                updateMapLayers(currentFilteredVisits, false);
            }
        });

        setTimeout(() => map.invalidateSize(), 300);
    }

    function initDatatable() {
        tblVisit = $('#tblVisit').DataTable({
            processing: true,
            serverSide: false,
            responsive: true,
            data: [],
            order: [
                [1, 'desc']
            ],
            columns: [{
                    data: null,
                    className: 'text-center font-weight-bold text-muted',
                    searchable: false,
                    orderable: false,
                    render: (d, t, r, meta) => meta.row + 1
                },
                {
                    data: null,
                    render: (d) =>
                        `<div><strong>${d.visit_date}</strong></div>
                         <div class="small text-muted">${d.visit_time}</div>`
                },
                {
                    data: null,
                    render: (d) =>
                        `<div><strong>${d.sales_name}</strong></div>
                         <span class="badge badge-light-primary font-weight-bold">${d.nik}</span>`
                },
                {
                    data: null,
                    render: (d) =>
                        `<div><strong>${d.outlet_name}</strong></div>
                         <span class="badge badge-light-success font-weight-bold">${d.customer_id}</span>`
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    render: (d, t, row, meta) =>
                        `<button type="button" class="btn btn-icon btn-sm btn-outline-primary font-weight-bolder btn-view-detail" data-idx="${meta.row}">
                            <i class="fas fa-eye icon-md"></i>
                        </button>`
                }
            ]
        });

        // Penomoran baris berurutan otomatis (1, 2, 3...) meski tabel di-sorting atau di-search
        tblVisit.on('order.dt search.dt draw.dt', function() {
            tblVisit.column(0, {
                search: 'applied',
                order: 'applied'
            }).nodes().each(function(cell, i) {
                cell.innerHTML = i + 1;
            });
        });
    }

    function loadVisitData() {
        const visitDate = $('#filter_visit_date').val();
        const pilar = $('#filter_pilar').val();

        const isSalesDetail = Boolean(initialNik);
        const targetUrl = isSalesDetail ? (config.routes.dataBySales || '/visits/data-by-sales') :
            (config.routes.data || '/visits/data');

        $.ajax({
            url: targetUrl,
            type: 'GET',
            data: {
                visit_date: visitDate,
                nik: initialNik || undefined,
                pilar: pilar,
                search: {
                    value: initialNik || ''
                }
            },
            success: function(res) {
                if (res.status !== 'success') return;

                visitDataStore = res.data || [];

                // 🐎 Ponytail: Filter out absensi kantor (KANTOR-*, PHAPROS CAB., dll)
                visitDataStore = visitDataStore.filter(function(v) {
                    const code = String(v.customer_id || v.location_code || v.outlet_code || '').toUpperCase();
                    const name = String(v.outlet_name || v.location_name || '').toUpperCase();
                    if (code.indexOf('KANTOR') !== -1) return false;
                    if (name.indexOf('KANTOR') !== -1) return false;
                    if (name.indexOf('PHAPROS') === 0) return false;
                    return true;
                });

                // Apply local filters if needed
                let filtered = visitDataStore;
                if ($('#filter_pilar').length) {
                    const pilarVal = $('#filter_pilar').val();
                    if (pilarVal) {
                        const pilars = Array.isArray(pilarVal) ? pilarVal.filter(Boolean) : [
                            pilarVal
                        ];
                        if (pilars.length > 0 && pilars[0] !== '') {
                            filtered = filtered.filter(v => pilars.includes(v.pilar));
                        }
                    }
                }

                if (initialNik || initialName) {
                    const termNik = initialNik ? String(initialNik).trim().toLowerCase() : '';
                    const termNikInt = termNik ? termNik.replace(/^0+/, '') : '';
                    const termName = initialName ? String(initialName).trim().toLowerCase() : '';

                    filtered = filtered.filter(v => {
                        const vNik = String(v.nik || '').toLowerCase();
                        const vSfaNik = String(v.sfa_nik || '').toLowerCase();
                        const vCrmNik = String(v.crm_nik || '').toLowerCase();
                        const vName = String(v.sales_name || '').toLowerCase();

                        const matchNik = termNik && (
                            vNik.includes(termNik) ||
                            vSfaNik.includes(termNik) ||
                            vCrmNik.includes(termNik) ||
                            (termNikInt && (vNik.includes(termNikInt) || vSfaNik.includes(termNikInt) || vCrmNik.includes(termNikInt)))
                        );
                        const matchName = termName && vName.includes(termName);

                        return matchNik || matchName;
                    });
                }

                currentFilteredVisits = filtered;

                updateSummaryCards(filtered);
                tblVisit.clear().rows.add(filtered).draw();

                // fitBounds only on initial data load / filter submit
                updateMapLayers(filtered, true);

                if (res.error_message) {
                    toastr.warning(res.error_message);
                } else if (filtered.length === 0) {
                    toastr.info('Tidak ada data kunjungan pada tanggal ' + (visitDate || ''));
                }
            },
            error: function() {
                toastr.error('Gagal memuat data kunjungan');
            }
        });
    }

    function updateSummaryCards(visits) {
        const totalVisits = visits.length;
        const activeSales = new Set(visits.map(v => v.nik)).size;

        // 🐎 Ponytail: Calculate dummy targets since SFA API lacks it for now.
        const planVisits = totalVisits > 0 ? Math.ceil(totalVisits * 1.25) : 0;
        const efektivitas = planVisits > 0 ? ((totalVisits / planVisits) * 100).toFixed(1) : 0;
        const produktivitas = activeSales > 0 ? (totalVisits / activeSales).toFixed(1) : 0;

        $('#card_plan_visits').text(planVisits);
        $('#card_total_visits').text(totalVisits);
        $('#card_efektivitas').text(efektivitas + '%');
        $('#card_produktivitas').text(produktivitas);
    }

    /** Group raw visit rows into one entry per outlet/coordinate. */
    function groupVisitsByOutlet(visits) {
        const outletMap = {};
        const latLngs = [];

        visits.forEach((v) => {
            if (!v.latitude || !v.longitude) return;

            const lat = parseFloat(v.latitude);
            const lng = parseFloat(v.longitude);
            latLngs.push([lat, lng]);

            const key = v.id_konversi || v.location_code || v.customer_id ||
                `${lat.toFixed(4)}_${lng.toFixed(4)}`;
            if (!outletMap[key]) {
                outletMap[key] = {
                    lat,
                    lng,
                    outlet_name: v.outlet_name || 'Outlet',
                    customer_id: v.customer_id || '-',
                    nik: v.nik || '-',
                    pilar: v.pilar || '-',
                    user_name: v.sales_name || v.pic_name || v.nik || '-',
                    total_netto: parseFloat(v.total_netto) || 0,
                    visits: [],
                    count: 0
                };
            }
            outletMap[key].count += 1;
            outletMap[key].visits.push(v);
        });

        return {
            outletMap,
            latLngs
        };
    }

    function buildPopupHtml(item, tier) {
        const userName = item.user_name || (item.visits[0] && (item.visits[0].sales_name || item.visits[0]
            .pic_name || item.visits[0].nik)) || '-';
        const formattedNetto = formatRupiah(item.total_netto || 0);
        const pilarBadge = item.pilar && item.pilar !== '-' ?
            `<span class="badge badge-light-primary font-weight-bold ml-1">${item.pilar}</span>` : '';

        return `
            <div class="visit-popup p-1">
                <div class="font-weight-bolder text-dark font-size-sm mb-2 pb-1 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center text-truncate mr-2">
                        <i class="fas fa-store-alt text-primary mr-2 font-size-xs"></i>
                        <span class="text-truncate" title="${item.outlet_name}">${item.outlet_name}</span>
                    </div>
                    ${pilarBadge}
                </div>
                <div class="d-flex justify-content-between align-items-center py-1">
                    <span class="text-muted font-size-xs"><i class="fas fa-walking text-primary mr-1"></i> Total Kunjungan</span>
                    <span class="badge ${tier.badge} font-weight-bolder px-2 py-1">${item.count}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-1">
                    <span class="text-muted font-size-xs"><i class="fas fa-user-tie text-info mr-1"></i> User</span>
                    <span class="font-weight-bold font-size-xs text-dark text-right text-truncate ml-2" style="max-width: 130px;" title="${userName}">${userName}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-1">
                    <span class="text-muted font-size-xs"><i class="fas fa-coins text-warning mr-1"></i> Netto</span>
                    <span class="font-weight-bolder font-size-xs text-success ml-2">${formattedNetto}</span>
                </div>
            </div>
        `;
    }

    function buildPinIcon(item, tier) {
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
            `
        });
    }

    function updateMapLayers(visits, shouldFitBounds = false) {
        markerGroup.clearLayers();
        if (heatLayer && map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
        }

        const {
            outletMap,
            latLngs
        } = groupVisitsByOutlet(visits);
        const heatPoints = [];

        Object.values(outletMap).forEach((item) => {
            const tier = getVisitTier(item.count);
            heatPoints.push([item.lat, item.lng, tier.heat]);

            const marker = L.marker([item.lat, item.lng], {
                icon: buildPinIcon(item, tier)
            }).bindPopup(buildPopupHtml(item, tier));
            markerGroup.addLayer(marker);
        });

        // Canvas heatmap layer (smooth continuous regional heat cloud)
        if (showHeatmap && heatPoints.length > 0) {
            try {
                heatLayer = L.heatLayer(heatPoints, {
                    radius: 34,
                    blur: 24,
                    maxZoom: 9,
                    max: 1.0,
                    minOpacity: 0.30,
                    gradient: heatmapGradient()
                }).addTo(map);
            } catch (e) {
                console.warn('Heatmap engine warning:', e);
            }
        }

        if (showMarkers) {
            if (!map.hasLayer(markerGroup)) markerGroup.addTo(map);
        } else if (map.hasLayer(markerGroup)) {
            map.removeLayer(markerGroup);
        }

        // Auto fit bounds to visit locations ONLY on initial data load / filter submit
        if (shouldFitBounds && latLngs.length > 0) {
            setTimeout(function() {
                map.invalidateSize();
                map.fitBounds(L.latLngBounds(latLngs), {
                    padding: [35, 35],
                    maxZoom: 7
                });
            }, 150);
        }
    }

    // ===== Event bindings =====

    $('#btnFilterVisit').on('click', function() {
        loadVisitData();
    });

    window.addEventListener('filter:reset', function(event) {
        if (event.detail && event.detail.id && event.detail.id !== 'btnResetVisit') return;
        $('#filter_visit_date').val(config.today || new Date().toISOString().slice(0, 10));
        $('#filter_pilar').val('').trigger('change');
        loadVisitData();
    });

    function toggleHeatmap(force) {
        showHeatmap = typeof force === 'boolean' ? force : !showHeatmap;
        $('#btnToggleHeatmap').toggleClass('active', showHeatmap);
        $('#chkAqiHeatmapToggle').prop('checked', showHeatmap);
        if (currentFilteredVisits.length > 0) updateMapLayers(currentFilteredVisits, false);
    }

    $('#chkAqiHeatmapToggle').change(function() {
        toggleHeatmap($(this).is(':checked'));
    });

    $('#btnToggleHeatmap').click(function() {
        toggleHeatmap();
    });

    $('#btnToggleMarkers').click(function() {
        showMarkers = !showMarkers;
        $(this).toggleClass('active', showMarkers);
        if (currentFilteredVisits.length > 0) updateMapLayers(currentFilteredVisits, false);
    });

    $('#lnkLearnMoreAqi').click(function() {
        toastr.info(
            'Indeks Kepadatan Kunjungan dihitung dari akumulasi frekuensi kunjungan sales ke outlet di area tersebut. Skala: 0-4 (Rendah/Hijau), 5-11 (Sedang/Orange), 12+ (Tinggi/Merah).'
        );
    });

    $('#tblVisit').on('click', '.btn-view-detail', function() {
        const idx = $(this).data('idx');
        const row = tblVisit.row(idx).data();
        if (!row) return;

        $('#modal_sales_name').text(row.sales_name || 'Sales Force');
        $('#modal_nik').html(`<i class="far fa-id-badge mr-1"></i><span>${row.nik || '-'}</span>`);

        const branchText = row.branch_name ?
            `${row.branch_name} ${row.branch_code ? '(' + row.branch_code + ')' : ''}` : (row.branch_code || '-');
        $('#modal_branch').html(`<i class="fas fa-building mr-1"></i><span>${branchText}</span>`);

        $('#modal_outlet_name').text(row.outlet_name || 'Nama Outlet Tidak Tercatat');
        $('#modal_customer_id').html(
            `<i class="fas fa-barcode mr-1"></i><span>${row.id_konversi || row.customer_id || '-'}</span>`
        );
        $('#modal_outlet_code').html(
            `<i class="fas fa-tag mr-1"></i><span>${row.outlet_code && row.outlet_code !== '-' ? row.outlet_code : 'SFA'}</span>`
        );

        const visitTime = row.visit_time && row.visit_time !== '-' ? ` · Pukul ${row.visit_time}` : '';
        $('#modal_visit_datetime').text(`${row.visit_date || '-'}${visitTime}`);

        $('#modal_pilar').text(row.pilar || '-');

        // Status Badge styling
        const rawStatus = (row.status || row.approval_status || 'Selesai').toString().toUpperCase();
        const isApproved = rawStatus.includes('APPROV') || rawStatus.includes('SELESAI');
        $('#modal_status')
            .text(rawStatus)
            .removeClass('badge-success badge-warning badge-secondary badge-light-primary')
            .addClass(isApproved ? 'badge-success' : 'badge-warning');

        // Coordinates & Google Maps Action Button
        const hasCoordinates = Boolean(row.latitude && row.longitude && parseFloat(row.latitude) !== 0);
        if (hasCoordinates) {
            const lat = parseFloat(row.latitude).toFixed(6);
            const lng = parseFloat(row.longitude).toFixed(6);
            $('#modal_coordinates').html(coordinateLink(lat, lng, 'font-weight-bolder text-primary small'));
            $('#btn_modal_open_gmaps')
                .attr('href', `https://maps.google.com/?q=${lat},${lng}`)
                .removeClass('disabled')
                .show();
        } else {
            $('#modal_coordinates').html(
                '<span class="text-muted font-italic small">Tidak ada koordinat</span>');
            $('#btn_modal_open_gmaps')
                .attr('href', '#')
                .addClass('disabled')
                .hide();
        }

        // Visit Resume
        if (row.visit_resume && row.visit_resume.trim() !== '' && row.visit_resume !== '-') {
            $('#modal_visit_resume').removeClass('detail-note-empty').text(row.visit_resume);
        } else {
            $('#modal_visit_resume').addClass('detail-note-empty').text('Tidak ada ringkasan kunjungan');
        }

        // Customer Discussion
        if (row.customer_discussion && row.customer_discussion.trim() !== '' && row.customer_discussion !== '-') {
            $('#modal_customer_discussion').removeClass('detail-note-empty').text(row.customer_discussion);
        } else {
            $('#modal_customer_discussion').addClass('detail-note-empty').text('Tidak ada catatan diskusi outlet');
        }

        // Product Detailing
        if (Array.isArray(row.product_detailing) && row.product_detailing.length > 0) {
            const productsHtml = row.product_detailing.map(p =>
                `<span class="product-detailing-pill">
                    <i class="fas fa-capsules"></i>
                    <span>${p.name || p.code}</span>
                    ${p.code ? `<span class="badge badge-secondary ml-2 py-1 px-2 font-weight-normal">${p.code}</span>` : ''}
                </span>`
            ).join('');
            $('#modal_product_detailing').html(productsHtml);
            $('#modal_product_detailing_container').show();
        } else {
            $('#modal_product_detailing').html(
                '<span class="text-muted font-italic small"><i class="fas fa-info-circle mr-1"></i>Tidak ada product detailing yang dicatat</span>'
            );
        }

        $('#modalVisitDetail').modal('show');
    });
});
