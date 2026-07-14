@extends('layouts.app', ['title' => 'Detail Performa Anggota', 'sidebar' => 'dashboard'])

@push('styles')
    <style>
        .bg-pareto-a {
            background-color: #1e3a8a !important;
            color: #fff;
        }

        .bg-pareto-b {
            background-color: #0d9488 !important;
            color: #fff;
        }

        .bg-pareto-c {
            background-color: #cbd5e1 !important;
            color: #333;
        }

        .table-pareto tbody tr.pareto-a td {
            background-color: rgba(30, 58, 138, .9);
            color: #fff;
            border-color: #fff;
        }

        .table-pareto tbody tr.pareto-b td {
            background-color: rgba(13, 148, 136, .9);
            color: #fff;
            border-color: #fff;
        }

        .table-pareto tbody tr.pareto-c td {
            background-color: rgba(203, 213, 225, .9);
            color: #333;
            border-color: #fff;
        }

        .anggota-trend-chart {
            min-height: 90px;
        }

        /* ==== Pagination outlet ala DataTables ==== */
        .outlet-pagination-wrap {
            font-size: .95rem;
        }

        .outlet-paginate {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            margin: 0;
        }

        .outlet-paginate .paginate_button {
            display: inline-block;
            min-width: 2rem;
            padding: .35rem .65rem;
            text-align: center;
            border: 1px solid transparent;
            border-radius: .42rem;
            color: #3f4254;
            cursor: pointer;
            user-select: none;
            transition: background-color .15s ease;
        }

        .outlet-paginate .paginate_button:hover:not(.disabled):not(.current) {
            background-color: #f3f6f9;
        }

        .outlet-paginate .paginate_button.current {
            background-color: #005db6;
            color: #fff;
            font-weight: 600;
        }

        .outlet-paginate .paginate_button.disabled {
            color: #b5b5c3;
            cursor: default;
        }

        .outlet-paginate .paginate_button.ellipsis {
            cursor: default;
            padding: .35rem .25rem;
            color: #b5b5c3;
        }

        .outlet-length-select {
            width: auto;
            display: inline-block;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-5">
        <h3 class="font-weight-bolder text-dark mb-0">Halaman Detail Performa Anggota</h3>
        <div>
            <a href="{{ route('dashboard.index') }}" class="btn btn-light-danger font-weight-bolder mr-2">
                <i class="fas fa-arrow-circle-left"></i> Back
            </a>
            {{-- <button class="btn btn-primary font-weight-bolder" onclick="window.print()">
                <i class="la la-print"></i> Cetak PDF
            </button> --}}
        </div>
    </div>

    {{-- Begin::Profil MR Card --}}
    <div class="card card-custom gutter-b">
        <div class="card-header border-0 pt-5">
            <h3 class="card-title font-weight-bolder text-dark">Profil MR & Ringkasan Performa</h3>
        </div>
        <div class="card-body pt-4">
            <div class="row">
                <div class="col-lg-7 d-flex align-items-center">
                    <div class="symbol symbol-100 symbol-light-primary mr-5">
                        <span
                            class="symbol-label font-size-h2 font-weight-bold">{{ mb_substr($anggota->name ?? '?', 0, 1) }}</span>
                    </div>
                    <div class="d-flex flex-column flex-grow-1">
                        <span
                            class="text-dark-75 font-weight-bolder font-size-lg mb-1">{{ $anggota->name ?? 'Tanpa Nama' }}</span>
                        <span class="text-muted font-weight-bold">NIK: {{ $anggota->nik }}</span>

                        <div class="row mt-4">
                            <div class="col-md-4">
                                <span class="d-block text-muted font-weight-bold">Level</span>
                                <span class="font-weight-bolder font-size-h6">{{ $anggota->level ?? '-' }}</span>
                            </div>
                            <div class="col-md-4">
                                <span class="d-block text-muted font-weight-bold">Divisi</span>
                                <span class="font-weight-bolder font-size-h6">{{ $anggota->divisi ?? '-' }}</span>
                            </div>
                            <div class="col-md-4">
                                <span class="d-block text-muted font-weight-bold">Pilar</span>
                                <span class="font-weight-bolder font-size-h6">{{ $anggota->pilar ?? '-' }}</span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <span class="d-block text-muted font-weight-bold">Total Outlet Cover</span>
                                <span class="font-weight-bolder font-size-h6">{{ count($outlets) }}</span>
                            </div>
                            <div class="col-md-4">
                                <span class="d-block text-muted font-weight-bold">Netto Total (YTD)</span>
                                <span class="font-weight-bolder text-primary font-size-h6">Rp
                                    {{ number_format($anggota->total_netto ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="font-weight-bolder text-muted">Performance Trend (YTD)</span>
                    </div>
                    <div id="anggota_trend_chart" class="anggota-trend-chart" style="height: 110px;"></div>
                </div>
            </div>
        </div>
    </div>
    {{-- End::Profil MR Card --}}

    {{-- Begin::Pareto Analysis Card --}}
    <div class="card card-custom gutter-b">
        <div class="card-header border-0 pt-5">
            <h3 class="card-title font-weight-bolder text-dark">Produk per Outlet</h3>
        </div>
        <div class="card-body pt-2">
            {{-- <div class="mb-8">
                <span class="font-weight-bold mr-3">Key:</span>
                <span class="label label-xl label-inline mr-2 bg-pareto-a">Group Pareto A (80% Kontribusi)</span>
                <span class="label label-xl label-inline mr-2 bg-pareto-b">Group Pareto B (15% Kontribusi)</span>
                <span class="label label-xl label-inline bg-pareto-c">Group Pareto C (5% Kontribusi)</span>
            </div> --}}

            <div class="accordion accordion-solid accordion-toggle-plus" id="accordionOutlet">
                @foreach ($outlets as $index => $outlet)
                    <div class="card outlet-card" data-page="{{ intdiv($index, 10) + 1 }}">
                        <div class="card-header" id="heading-{{ $index }}">
                            <div class="card-title collapsed" data-toggle="collapse"
                                data-target="#collapse-{{ $index }}">
                                <i class="fas fa-hospital text-primary mr-2"></i>
                                {{ $outlet->outlet_name }} ({{ $outlet->id_outlet }}) - Total Netto: Rp
                                {{ number_format($outlet->total_netto, 0, ',', '.') }}
                            </div>
                        </div>
                        <div id="collapse-{{ $index }}" class="collapse" data-parent="#accordionOutlet">
                            {{-- <div id="chart_pareto_{{ $index }}" class="mb-5" style="height: 320px;"></div> --}}
                            <div class="card-body">
                                <table class="table table-bordered table-striped datatable-produk">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Product Name</th>
                                            <th>Total Netto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($outlet->products as $pIndex => $product)
                                            <tr>
                                                <td>{{ $pIndex + 1 }}</td>
                                                <td class="font-weight-bold">{{ $product->product_name }}</td>
                                                <td>Rp {{ number_format($product->netto, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (count($outlets) > 10)
                <div class="d-flex align-items-center justify-content-between flex-wrap mt-5 outlet-pagination-wrap">
                    <div class="d-flex align-items-center">
                        <label class="mb-0 mr-2">Tampilkan</label>
                        <select id="outletLengthSelect" class="form-control form-control-sm outlet-length-select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="-1">Semua</option>
                        </select>
                        <label class="mb-0 ml-2">outlet</label>
                    </div>

                    <span class="text-muted" id="outletPageInfo"></span>

                    <ul class="outlet-paginate" id="outletPagination"></ul>
                </div>
            @endif
        </div>
    </div>
    {{-- End::Pareto Analysis Card --}}
@endsection

@push('scripts')
    <script>
        jQuery(document).ready(function() {
            const $cards = $('.outlet-card');
            let perPage = 10;
            let totalPages = Math.max(1, Math.ceil($cards.length / perPage));
            let currentPage = 1;

            function initDataTable($card) {
                const $table = $card.find('.datatable-produk');
                // Cegah re-init kalau accordion dibuka-tutup berkali-kali
                if ($table.length && !$.fn.DataTable.isDataTable($table[0])) {
                    $table.DataTable({
                        "pageLength": 5,
                        "lengthChange": false,
                        "language": {
                            "search": "Cari Produk:"
                        }
                    });
                }
            }

            // Bangun daftar nomor halaman dengan ellipsis, mirip DataTables
            // contoh: 1 ... 4 5 [6] 7 8 ... 20
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
        });
    </script>
    <script>
        window.ANGGOTA_DETAIL = {
            trend: @json($trend ?? ['labels' => [], 'data' => []]),
            outlets: {!! $outletsJson ?? '[]' !!}
        };
    </script>
    <script>
        jQuery(document).ready(function() {
            if (typeof ApexCharts === 'undefined') return;

            const trendEl = document.querySelector('#anggota_trend_chart');
            if (trendEl && window.ANGGOTA_DETAIL.trend.labels.length) {
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

            window.ANGGOTA_DETAIL.outlets.forEach(function(outlet, idx) {
                const el = document.querySelector('#chart_pareto_' + idx);
                if (!el || !outlet.products.length) return;

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
        });
    </script>
@endpush
