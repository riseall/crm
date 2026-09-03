jQuery(document).ready(function() {
    'use strict';

    const $cards = $('.outlet-card');
    let perPage = 10;
    let totalPages = Math.max(1, Math.ceil($cards.length / perPage));
    let currentPage = 1;

    function initDataTable($card) {
        const $table = $card.find('.datatable-produk');
        // Cegah re-init kalau accordion dibuka-tutup berkali-kali
        if ($table.length && !$.fn.DataTable.isDataTable($table[0])) {
            $table.DataTable({
                pageLength: 5,
                lengthChange: false,
                language: {
                    search: 'Cari Produk:'
                }
            });
        }
    }

    // Bangun daftar nomor halaman dengan ellipsis, mirip DataTables
    function buildPageNumbers(current, total) {
        const delta = 2;
        const pages = [];
        const range = [];

        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
                range.push(i);
            }
        }

        let prev = null;
        range.forEach(function(p) {
            if (prev !== null && p - prev > 1) pages.push('...');
            pages.push(p);
            prev = p;
        });

        return pages;
    }

    function renderPagination() {
        const $pg = $('#outletPagination');
        if (!$pg.length) return;
        $pg.empty();

        const addButton = (label, page, classes = '') => {
            $pg.append(
                `<li class="paginate_button ${classes}" data-page="${page}">${label}</li>`
            );
        };

        addButton('Previous', currentPage - 1, currentPage === 1 ? 'disabled' : '');

        buildPageNumbers(currentPage, totalPages).forEach(function(p) {
            if (p === '...') {
                addButton('&hellip;', '', 'ellipsis');
            } else {
                addButton(p, p, p === currentPage ? 'current' : '');
            }
        });

        addButton('Next', currentPage + 1, currentPage === totalPages ? 'disabled' : '');

        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, $cards.length);
        $('#outletPageInfo').text(
            `Menampilkan ${start} - ${end} dari ${$cards.length} outlet`
        );
    }

    function showPage(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;

        const start = (currentPage - 1) * perPage;
        const end = start + perPage;

        $cards.each(function(idx) {
            $(this).toggleClass('d-none', !(idx >= start && idx < end));
        });

        renderPagination();

        $('html, body').animate({
            scrollTop: $('#accordionOutlet').offset().top - 100
        }, 200);
    }

    $(document).on('click', '#outletPagination .paginate_button', function(e) {
        e.preventDefault();
        const $btn = $(this);
        if ($btn.hasClass('disabled') || $btn.hasClass('ellipsis') || $btn.hasClass('current'))
            return;
        const page = parseInt($btn.data('page'), 10);
        if (!isNaN(page)) showPage(page);
    });

    $('#outletLengthSelect').on('change', function() {
        const val = parseInt($(this).val(), 10);
        perPage = val === -1 ? $cards.length : val;
        totalPages = Math.max(1, Math.ceil($cards.length / perPage));
        showPage(1);
    });

    // Init DataTable & chart pareto hanya saat accordion-nya benar-benar dibuka
    $('#accordionOutlet').on('shown.bs.collapse', '.collapse', function() {
        const $card = $(this).closest('.outlet-card');
        initDataTable($card);
    });

    showPage(1);

    // Render Charts
    if (typeof ApexCharts !== 'undefined' && window.ANGGOTA_DETAIL) {
        const trendEl = document.querySelector('#anggota_trend_chart');
        if (trendEl && window.ANGGOTA_DETAIL.trend && window.ANGGOTA_DETAIL.trend.labels && window.ANGGOTA_DETAIL.trend.labels.length) {
            new ApexCharts(trendEl, {
                chart: {
                    type: 'area',
                    height: 110,
                    sparkline: {
                        enabled: true
                    },
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Netto',
                    data: window.ANGGOTA_DETAIL.trend.data
                }],
                xaxis: {
                    categories: window.ANGGOTA_DETAIL.trend.labels
                },
                stroke: {
                    curve: 'smooth',
                    width: 2
                },
                colors: ['#1D9E75'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        opacityFrom: 0.45,
                        opacityTo: 0.05
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(v) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(v);
                        }
                    }
                }
            }).render();
        }

        if (Array.isArray(window.ANGGOTA_DETAIL.outlets)) {
            window.ANGGOTA_DETAIL.outlets.forEach(function(outlet, idx) {
                const el = document.querySelector('#chart_pareto_' + idx);
                if (!el || !outlet.products || !outlet.products.length) return;

                const total = outlet.products.reduce(function(s, p) {
                    return s + p.netto;
                }, 0);
                let cum = 0;
                const kontribusi = [];
                const kumulatif = [];
                outlet.products.forEach(function(p) {
                    const pct = total > 0 ? (p.netto / total) * 100 : 0;
                    cum += pct;
                    kontribusi.push(+pct.toFixed(1));
                    kumulatif.push(+cum.toFixed(1));
                });

                new ApexCharts(el, {
                    chart: {
                        type: 'bar',
                        height: 320,
                        toolbar: {
                            show: false
                        }
                    },
                    series: [{
                            name: 'Contribution %',
                            type: 'bar',
                            data: kontribusi
                        },
                        {
                            name: 'Cumulative %',
                            type: 'line',
                            data: kumulatif
                        },
                    ],
                    xaxis: {
                        categories: outlet.products.map(function(p) {
                            return p.name;
                        }),
                        labels: {
                            rotate: -30,
                            style: {
                                fontSize: '10px'
                            }
                        }
                    },
                    yaxis: [{
                            title: {
                                text: 'Contribution %'
                            },
                            max: 100
                        },
                        {
                            opposite: true,
                            title: {
                                text: 'Cumulative %'
                            },
                            max: 100
                        }
                    ],
                    stroke: {
                        width: [0, 3],
                        curve: 'smooth'
                    },
                    colors: ['#1e3a8a', '#0d9488'],
                    dataLabels: {
                        enabled: false
                    },
                    legend: {
                        position: 'top'
                    },
                    grid: {
                        borderColor: '#e5e7eb'
                    },
                    tooltip: {
                        shared: true,
                        intersect: false
                    }
                }).render();
            });
        }
    }
});
