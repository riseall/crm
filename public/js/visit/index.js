$(document).ready(function() {
    'use strict';

    const config = window.VisitPageConfig || {
        initialNik: '',
        initialName: '',
        today: new Date().toISOString().slice(0, 10),
        routes: {
            data: '/visits/data'
        }
    };

    /**
     * Skala Intensitas Kunjungan (Inverted Sesuai Aturan Pengguna):
     * Kunjungan Semakin BANYAK = Semakin HIJAU
     * Kunjungan Semakin SEDIKIT = Semakin MERAH
     */
    const VISIT_TIERS = [{
            min: 15,
            color: '#16a34a', // Hijau pekat (Kunjungan Banyak)
            badge: 'badge-success',
            label: 'Sangat Sering',
            heat: 1.00
        },
        {
            min: 10,
            color: '#84cc16', // Hijau muda
            badge: 'badge-light-success',
            label: 'Sering Dikunjungi',
            heat: 0.75
        },
        {
            min: 5,
            color: '#eab308', // Kuning
            badge: 'badge-warning',
            label: 'Sedang Dikunjungi',
            heat: 0.50
        },
        {
            min: 2,
            color: '#ea580c', // Orange
            badge: 'badge-light-danger',
            label: 'Jarang Dikunjungi',
            heat: 0.28
        },
        {
            min: 0,
            color: '#dc2626', // Merah (Kunjungan Sedikit)
            badge: 'badge-danger',
            label: 'Minimal Visit',
            heat: 0.12
        },
    ];

    const getVisitTier = (count) => VISIT_TIERS.find(tier => count >= tier.min) || VISIT_TIERS[VISIT_TIERS.length - 1];

    const heatmapGradient = () => ({
        0.05: 'rgba(239, 68, 68, 0.72)',   // Vivid Soft Crimson (Merah)
        0.25: 'rgba(249, 115, 22, 0.74)',  // Soft Coral (Orange)
        0.45: 'rgba(250, 204, 21, 0.76)',  // Soft Amber Yellow (Kuning)
        0.70: 'rgba(132, 204, 22, 0.78)',  // Soft Lime Green (Hijau Muda)
        0.95: 'rgba(16, 185, 129, 0.82)',  // Soft Emerald (Hijau Pekat)
        1.00: 'rgba(5, 150, 105, 0.85)'    // Deep Forest Green
    });

    /** Format angka ke Rupiah standar */
    const formatRupiah = (val) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(val || 0);
    };

    /** Format angka ringkas Rupiah (M = Miliar, Jt = Juta) */
    const formatShortRupiah = (val) => {
        if (!val || val === 0) return 'Rp 0';
        const absVal = Math.abs(val);
        if (absVal >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toLocaleString('id-ID', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }) + ' M';
        }
        if (absVal >= 1000000) {
            return 'Rp ' + (val / 1000000).toLocaleString('id-ID', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }) + ' Jt';
        }
        return formatRupiah(val);
    };

    // ===== State Map & DataTables =====
    let map = null;
    let heatLayer = null;
    const markerGroup = L.layerGroup();
    let currentMapMarkers = [];
    let showHeatmap = true;
    let showMarkers = true;
    let tblVisit = null;

    initMap();
    initDatatable();
    loadVisitData();

    function initMap() {
        // Fokus awal wilayah Indonesia / Jawa
        map = L.map('visitMap').setView([-6.2088, 106.8456], 6);

        // Buat pane khusus untuk heatmap di bawah markerPane (600) & popupPane (700)
        if (!map.getPane('heatmapPane')) {
            const hPane = map.createPane('heatmapPane');
            hPane.style.zIndex = 205; // Tepat di atas tile OSM (200), di bawah SEMUA marker & popup
            hPane.style.pointerEvents = 'none';

            // Sisipkan fisik DOM tepat sebelum markerPane agar rendering z-index dan GPU layer 100% konsisten
            const mPane = map.getPane('markerPane');
            if (mPane && mPane.parentNode) {
                mPane.parentNode.insertBefore(hPane, mPane);
            }
        }

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        markerGroup.addTo(map);

        // Kinetic Decoupling: Saat zoom dimulai, sembunyikan marker agar animasi zoom berjalan 60 FPS GPU murni tanpa beban DOM
        let moveTimer = null;
        map.on('zoomstart', function() {
            $('#visitMap').addClass('map-zooming');
            markerGroup.clearLayers();
            if (map.closePopup) map.closePopup();
        });

        // Saat pergerakan/zoom berhenti (debounced 120ms), render marker LOD hanya di area viewport aktif
        map.on('moveend', function() {
            // Jangan re-render jika ada popup yang sedang terbuka agar tidak auto-close
            if (map._popup && map.hasLayer(map._popup)) {
                return;
            }

            clearTimeout(moveTimer);
            moveTimer = setTimeout(function() {
                if (currentMapMarkers && currentMapMarkers.length > 0) {
                    renderLodMarkers();
                }
                requestAnimationFrame(function() {
                    $('#visitMap').removeClass('map-zooming');
                });
            }, 120);
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
                [3, 'desc'] // Urutkan berdasarkan Total Kunjungan terbanyak
            ],
            columns: [{
                    data: null,
                    className: 'text-center font-weight-bolder',
                    searchable: false,
                    orderable: false,
                    render: (d, t, r, meta) => meta.row + 1
                },
                {
                    data: 'employee',
                    render: (d, t, r) =>
                        `<div><strong class="font-weight-bolder text-dark">${d || '-'}</strong></div>
                         <span class="badge badge-light-primary font-weight-bold">${r.nik || '-'}</span>`
                },
                {
                    data: 'outlet',
                    render: (d, t, r) =>
                        `<div><strong class="font-weight-bolder text-dark">${d || '-'}</strong></div>
                         <span class="badge badge-light-secondary font-weight-normal text-muted">${r.location_code || 'Outlet'}</span>`
                },
                {
                    data: 'total_kunjungan',
                    className: 'text-center font-weight-bolder text-dark',
                    render: (d) => `<span class="badge badge-light-info font-weight-bolder font-size-sm px-3 py-2">${d || 0}</span>`
                },
                {
                    data: 'realisasi_selling_out',
                    className: 'text-right font-weight-bolder text-success font-size-sm',
                    render: (d) => formatRupiah(d || 0)
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    render: (d, t, r) => {
                        const countUsers = r.doctor_count !== undefined ? r.doctor_count : (r.users_visited || []).length;
                        return `<button type="button" class="btn btn-icon btn-xs btn-light-primary btn-expand-row" title="Lihat ${countUsers} dokter/user">
                            <i class="fas fa-plus icon-xs"></i>
                        </button>`;
                    }
                }
            ],
            footerCallback: function(row, data, start, end, display) {
                const api = this.api();

                // Akumulasi Total Kunjungan (hanya baris yang terfilter)
                const totalKunjungan = api
                    .column(3, { search: 'applied' })
                    .data()
                    .reduce((sum, val) => sum + (Number(val) || 0), 0);

                // Akumulasi Realisasi Selling Out (hanya baris yang terfilter)
                const totalSellingOut = api
                    .column(4, { search: 'applied' })
                    .data()
                    .reduce((sum, val) => sum + (Number(val) || 0), 0);

                $('#foot_total_kunjungan').text(totalKunjungan.toLocaleString('id-ID'));
                $('#foot_total_selling_out').text(formatRupiah(totalSellingOut));
            }
        });

        // Penomoran baris otomatis berurutan (1, 2, 3...)
        tblVisit.on('order.dt search.dt draw.dt', function() {
            tblVisit.column(0, {
                search: 'applied',
                order: 'applied'
            }).nodes().each(function(cell, i) {
                cell.innerHTML = i + 1;
            });
        });

        // Event listener klik tombol (+) untuk expand child row (Gambar 2)
        $('#tblVisit tbody').on('click', '.btn-expand-row', function() {
            const btn = $(this);
            const tr = btn.closest('tr');
            const row = tblVisit.row(tr);

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
                btn.removeClass('expanded');
            } else {
                const rowData = row.data();
                if (rowData.users_visited && rowData.users_visited.length > 0) {
                    row.child(formatChildRow(rowData)).show();
                    tr.addClass('shown');
                    btn.addClass('expanded');
                } else if (config.routes.detailDoctors && rowData.location_code) {
                    // Lazy-load detail dokter via AJAX on-demand
                    row.child(`<div class="table-child-row-wrapper m-2 p-3 text-center text-muted font-size-sm">
                        <i class="fas fa-spinner fa-spin mr-2 text-primary"></i> Memuat rincian dokter/user...
                    </div>`).show();
                    tr.addClass('shown');
                    btn.addClass('expanded');

                    const [startDate, endDate] = getSelectedDateRange();
                    $.ajax({
                        url: config.routes.detailDoctors,
                        type: 'GET',
                        data: {
                            location_code: rowData.location_code,
                            start_date: startDate,
                            end_date: endDate,
                            employee: rowData.nik || ''
                        },
                        success: function(res) {
                            if (res.status === 'success' && res.data) {
                                rowData.users_visited = res.data;
                            } else {
                                rowData.users_visited = [];
                            }
                            if (row.child.isShown()) {
                                row.child(formatChildRow(rowData)).show();
                            }
                        },
                        error: function() {
                            if (row.child.isShown()) {
                                row.child(`<div class="table-child-row-wrapper m-2 p-3 text-danger small">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Gagal memuat rincian dokter.
                                </div>`).show();
                            }
                        }
                    });
                } else {
                    row.child(formatChildRow(rowData)).show();
                    tr.addClass('shown');
                    btn.addClass('expanded');
                }
            }
        });
    }

    /**
     * Render Sub-table Breakdown Dokter/User yang dikunjungi di Outlet tersebut
     */
    function formatChildRow(d) {
        const users = d.users_visited || [];
        if (users.length === 0) {
            return `
                <div class="table-child-row-wrapper m-2">
                    <span class="text-muted font-italic small">
                        <i class="fas fa-info-circle mr-1"></i> Tidak ada dokter / user spesifik yang tercatat pada kunjungan ini.
                    </span>
                </div>
            `;
        }

        let rowsHtml = '';
        users.forEach((u, i) => {
            rowsHtml += `
                <tr>
                    <td class="text-center font-weight-bold text-muted">${i + 1}</td>
                    <td class="font-weight-bolder text-dark">
                        <i class="fas fa-user-md text-primary mr-2"></i>${u.user_name}
                    </td>
                    <td>
                        <span class="badge badge-light-info font-weight-bold">${u.specialist || '-'}</span>
                    </td>
                    <td class="text-center font-weight-bolder text-success">
                        ${u.visit_count}x Kunjungan
                    </td>
                    <td class="text-center text-muted small">
                        ${u.last_visit_date || '-'}
                    </td>
                </tr>
            `;
        });

        return `
            <div class="table-child-row-wrapper m-2">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="font-weight-bolder text-dark font-size-sm">
                        <i class="fas fa-hospital-user text-primary mr-1"></i> Rincian Dokter / User Terkunjungi di <u>${d.outlet}</u>
                    </span>
                    <span class="badge badge-primary font-weight-bolder">${users.length} Dokter Terdata</span>
                </div>
                <div style="max-height: 320px; overflow-y: auto;" class="border rounded bg-white shadow-xs">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="thead-light" style="position: sticky; top: 0; z-index: 2;">
                            <tr>
                                <th style="width: 40px;" class="text-center">No</th>
                                <th>Nama User / Dokter</th>
                                <th>Spesialisasi</th>
                                <th style="width: 140px;" class="text-center">Total Kunjungan</th>
                                <th style="width: 130px;" class="text-center">Tgl Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>
            </div>
        `;
    }

    /**
     * Ambil rentang tanggal aktif dari form filter
     */
    function getSelectedDateRange() {
        const s = $('#filter_start_date').val();
        const e = $('#filter_end_date').val();
        return [
            s || config.today,
            e || config.today
        ];
    }

    /**
     * AJAX Fetch Data Kunjungan VS Realisasi
     */
    function loadVisitData() {
        const startDate = $('#filter_start_date').val();
        const endDate = $('#filter_end_date').val();
        const divisi = $('#filter_divisi').val();
        const level = $('#filter_level').val();
        const employee = $('#filter_employee').val();
        const outlet = $('#filter_outlet').val();

        if (typeof KTApp !== 'undefined') {
            KTApp.block('#tblVisit', {
                overlayColor: '#000000',
                state: 'primary',
                message: 'Memuat data kunjungan & realisasi...'
            });
        }

        $.ajax({
            url: config.routes.data || '/visits/data',
            type: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate,
                divisi: divisi,
                level: level,
                employee: employee,
                outlet: outlet,
            },
            success: function(res) {
                if (typeof KTApp !== 'undefined') KTApp.unblock('#tblVisit');
                if (res.status !== 'success') return;

                // 1. Update 3 Summary KPI Cards (Gambar 1)
                updateSummaryCards(res.summary);

                // 2. Update Map Markers & Inverted Heatmap (Gambar 1)
                updateMapMarkers(res.map_markers || []);

                // 3. Update Detail Table (Gambar 2)
                tblVisit.clear().rows.add(res.table_data || []).draw();

                if (!res.table_data || res.table_data.length === 0) {
                    toastr.info('Tidak ada data kunjungan pada periode yang dipilih.');
                }
            },
            error: function() {
                if (typeof KTApp !== 'undefined') KTApp.unblock('#tblVisit');
                toastr.error('Gagal memuat data kunjungan & realisasi.');
            }
        });
    }

    /**
     * Update 3 Summary KPI Cards
     */
    function updateSummaryCards(summary) {
        if (!summary) return;

        // Card 1: Jumlah User yang Ter-kunjungi
        $('#card_user_terkunjungi').text((summary.jumlah_user_terkunjungi || 0).toLocaleString('id-ID'));

        // Card 2: Total Realisasi Selling Out
        const totalRealisasi = summary.total_realisasi || 0;
        $('#card_total_realisasi').text(formatShortRupiah(totalRealisasi));

        // Card 3: Produktivitas per Level & Total FF
        const prodLevel = summary.produktivitas_per_level || 0;
        const prodFf = summary.total_produktivitas_ff || 0;
        $('#card_produktivitas_level').text(formatShortRupiah(prodLevel) + '/bln');
        $('#card_produktivitas_ff').text(formatShortRupiah(prodFf) + '/bln');
    }

    /**
     * Circular Bubble Marker ala Google Maps AQI & IQAir Earth
     */
    function buildAqiMarker(item, tier) {
        // Kontras teks otomatis: teks gelap untuk background kuning/lime terang
        const isLightBg = tier.color === '#eab308' || tier.color === '#84cc16';
        const contrastClass = isLightBg ? 'text-dark-contrast' : '';
        const count = item.total_kunjungan || 0;

        return L.divIcon({
            className: 'aqi-div-icon',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -16],
            html: `<div class="aqi-marker-badge ${contrastClass}" style="background-color: ${tier.color};">${count}</div>`
        });
    }

    /**
     * Smart Adaptive Grid LOD Thinning (Anti-Clumping di Semua Tingkat Zoom)
     * Mengatur jarak minimum antar-badge (~24-50px di layar)
     * Memastikan marker 30px tidak pernah saling menumpuk menjadi gumpalan
     * dan total DOM node selalu terjaga ringan (< 150 node) untuk 60 FPS
     */
    function filterMarkersForLod(items, zoom) {
        if (!items || items.length === 0) return [];

        // Viewport Bounding Box Clipping: Hanya proses item yang berada dalam pandangan layar (+10% margin)
        let candidates = items;
        if (map) {
            try {
                const bounds = map.getBounds().pad(0.10);
                const south = bounds.getSouth();
                const north = bounds.getNorth();
                const west = bounds.getWest();
                const east = bounds.getEast();

                candidates = items.filter(item => {
                    const lat = parseFloat(item.latitude);
                    const lng = parseFloat(item.longitude);
                    return lat >= south && lat <= north && lng >= west && lng <= east;
                });
            } catch (e) {
                candidates = items;
            }
        }

        // Ukuran sel piksel layar agar marker 30px tidak pernah tabrakan
        const cellSize = zoom >= 13 ? 24 : (zoom >= 10 ? 32 : (zoom >= 8 ? 40 : 50));
        const grid = {};

        candidates.forEach(item => {
            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);
            if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

            const pt = map.project([lat, lng], zoom);
            const cellKey = Math.floor(pt.x / cellSize) + '_' + Math.floor(pt.y / cellSize);

            if (!grid[cellKey] || (Number(item.total_kunjungan) > Number(grid[cellKey].total_kunjungan))) {
                grid[cellKey] = item;
            }
        });

        return Object.values(grid);
    }

    /**
     * Render marker bubble lingkaran bernomor dengan LOD adaptif
     */
    function renderLodMarkers() {
        markerGroup.clearLayers();
        if (!showMarkers || !currentMapMarkers || currentMapMarkers.length === 0) return;

        const currentZoom = map.getZoom();
        const visibleItems = filterMarkersForLod(currentMapMarkers, currentZoom);

        visibleItems.forEach(item => {
            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);
            if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

            const tier = getVisitTier(item.total_kunjungan);
            const marker = L.marker([lat, lng], {
                icon: buildAqiMarker(item, tier),
                pane: 'markerPane'
            });

            // Lazy popup evaluation: only built when clicked!
            marker.bindPopup(function() {
                return buildPopupHtml(item, tier);
            }, {
                autoPan: false
            });

            markerGroup.addLayer(marker);
        });

        if (!map.hasLayer(markerGroup)) {
            markerGroup.addTo(map);
        }
    }

    /**
     * Custom Popup HTML sesuai spesifikasi:
     * Nama RS, Total Kunjungan, Daftar User dokter (is_doctor=1), Selling Out total & breakdown pilar old
     */
    function buildPopupHtml(item, tier) {
        const usersList = (item.users || []).map(u =>
            `<div class="mb-1 text-truncate" title="${u}">
                <i class="fas fa-circle text-primary mr-1" style="font-size: 5px; vertical-align: middle;"></i>
                <span>${u}</span>
            </div>`
        ).join('') || '<span class="text-muted font-italic small">- Tidak ada dokter tercatat -</span>';

        const formattedSellingOut = formatRupiah(item.selling_out_total || 0);

        let breakdownHtml = '';
        if (item.selling_breakdown) {
            const bd = item.selling_breakdown;
            if (bd['Ethi-KOM'] > 0) {
                breakdownHtml += `<div class="d-flex justify-content-between"><span>Ethi-KOM:</span> <span class="font-weight-bold text-dark">${formatRupiah(bd['Ethi-KOM'])}</span></div>`;
            }
            if (bd['Ethi-PROM'] > 0) {
                breakdownHtml += `<div class="d-flex justify-content-between"><span>Ethi-PROM:</span> <span class="font-weight-bold text-dark">${formatRupiah(bd['Ethi-PROM'])}</span></div>`;
            }
            if (bd['OGB'] > 0) {
                breakdownHtml += `<div class="d-flex justify-content-between"><span>OGB:</span> <span class="font-weight-bold text-dark">${formatRupiah(bd['OGB'])}</span></div>`;
            }
            if (bd['OTC'] > 0) {
                breakdownHtml += `<div class="d-flex justify-content-between"><span>OTC:</span> <span class="font-weight-bold text-dark">${formatRupiah(bd['OTC'])}</span></div>`;
            }
        }

        return `
            <div class="visit-popup p-2" style="min-width: 250px; max-width: 320px;">
                <div class="font-weight-bolder text-dark font-size-sm mb-2 pb-1 border-bottom d-flex align-items-center">
                    <i class="fas fa-hospital-alt text-primary mr-2"></i>
                    <span class="text-truncate" title="${item.outlet_name}">${item.outlet_name}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-1">
                    <span class="text-muted font-size-xs font-weight-bold">TOTAL KUNJUNGAN:</span>
                    <span class="badge ${tier.badge} font-weight-bolder px-2 py-1">${item.total_kunjungan}</span>
                </div>
                <div class="py-1 text-left">
                    <span class="text-muted font-size-xs font-weight-bold d-block mb-1">USER (DOKTER):</span>
                    <div class="pl-2 small text-dark" style="max-height: 120px; overflow-y: auto;">
                        ${usersList}
                    </div>
                </div>
                <div class="pt-2 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted font-size-xs font-weight-bold">SELLING OUT:</span>
                        <span class="font-weight-bolder text-success font-size-xs">${formattedSellingOut}</span>
                    </div>
                    ${breakdownHtml ? `<div class="pl-2 font-size-xs text-muted" style="background:#f8f9fa; padding:4px; border-radius:4px;">${breakdownHtml}</div>` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Update Peta Leaflet: Markers (LOD) & Seamless Inverted Heatmap Layer
     */
    function updateMapMarkers(markersData) {
        currentMapMarkers = markersData || [];

        // 1. Render Marker Bubble Lingkaran dengan LOD (Zoom-Aware)
        renderLodMarkers();

        // 2. Render Heatmap Layer dengan Seamless Blending ala IQAir Earth
        if (heatLayer && map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
            heatLayer = null;
        }

        const latLngs = [];
        const coordMap = {};

        currentMapMarkers.forEach((item) => {
            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);
            if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

            latLngs.push([lat, lng]);
            const key = lat.toFixed(4) + '_' + lng.toFixed(4);
            const tier = getVisitTier(item.total_kunjungan);
            if (!coordMap[key] || tier.heat > coordMap[key][2]) {
                coordMap[key] = [lat, lng, tier.heat];
            }
        });

        const heatPoints = Object.values(coordMap);

        // Heatmap layer dengan gradien terbalik, zoom-adaptive radius, & natural Gaussian falloff
        if (showHeatmap && heatPoints.length > 0) {
            try {
                heatLayer = L.heatLayer(heatPoints, {
                    pane: 'heatmapPane',
                    radius: 28,
                    blur: 32,
                    maxZoom: 12,
                    max: 1.0,
                    minOpacity: 0.20,
                    gradient: heatmapGradient()
                }).addTo(map);

                // Pastikan canvas heatmap berada di heatmapPane dan tembus event mouse
                if (heatLayer && heatLayer._canvas) {
                    heatLayer._canvas.style.pointerEvents = 'none';
                    const hPane = map.getPane('heatmapPane');
                    if (hPane && heatLayer._canvas.parentNode !== hPane) {
                        hPane.appendChild(heatLayer._canvas);
                    }
                }
            } catch (e) {
                console.warn('Leaflet heat warning:', e);
            }
        }

        if (latLngs.length > 0) {
            setTimeout(function() {
                map.invalidateSize();
                map.fitBounds(L.latLngBounds(latLngs), {
                    padding: [35, 35],
                    maxZoom: 9
                });
            }, 200);
        }
    }

    // ===== Event Bindings =====

    $('#btnFilterVisit').on('click', function() {
        loadVisitData();
    });

    $('#btnResetVisit').on('click', function() {
        $('#filter_start_date').val(config.today ? config.today.slice(0, 8) + '01' : '');
        $('#filter_end_date').val(config.today || '');
        $('#filter_divisi').val('All').selectpicker('refresh');
        $('#filter_level').val('All').selectpicker('refresh');
        $('#filter_employee').val('All').selectpicker('refresh');
        $('#filter_outlet').val('All').selectpicker('refresh');
        loadVisitData();
    });

    function toggleHeatmap(force) {
        showHeatmap = typeof force === 'boolean' ? force : !showHeatmap;
        $('#btnToggleHeatmap').toggleClass('active', showHeatmap);
        $('#chkAqiHeatmapToggle').prop('checked', showHeatmap);
        if (heatLayer) {
            if (showHeatmap) {
                if (!map.hasLayer(heatLayer)) {
                    heatLayer.addTo(map);
                    if (heatLayer._canvas) {
                        heatLayer._canvas.style.pointerEvents = 'none';
                        const hPane = map.getPane('heatmapPane');
                        if (hPane && heatLayer._canvas.parentNode !== hPane) {
                            hPane.appendChild(heatLayer._canvas);
                        }
                    }
                }
            } else if (map.hasLayer(heatLayer)) {
                map.removeLayer(heatLayer);
            }
        }
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
        renderLodMarkers();
    });
});
