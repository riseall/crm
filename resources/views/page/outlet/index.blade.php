@extends('layouts.app', ['title' => 'Outlet'])

@section('content')
    <div class="row mb-3">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="total master outlet" value="{{ $totalData }}" subtitle="Entitas di Coverage"
                subtitleColor="text-primary" icon="far fa-folder-open" color="primary">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="outlet tercoverage" value="{{ $totalTercoverage }}" subtitle="Sudah memiliki Sales Rep"
                subtitleColor="text-info" icon="far fa-file-alt" color="info">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="outlet belum tercoverage" value="{{ $totalBelumCoverage }}"
                subtitle="Master belum ada Sales Rep" subtitleColor="text-danger" icon="far fa-file-excel" color="danger">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
            <x-stat-card title="sellout belum termapping" value="{{ $totalUnmappedSellout }}" subtitle="Perlu Dipetakan"
                subtitleColor="text-danger" icon="fas fa-exclamation-triangle" color="danger">
            </x-stat-card>
        </div>
    </div>

    <div class="card card-custom gutter-b">
        <div class="card-header card-header-tabs-line border-0 pt-2">
            <div class="card-toolbar">
                <ul class="nav nav-tabs nav-bold nav-tabs-line" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#kt_tab_mapping" id="tabMappingLink">
                            <span class="nav-icon"><i class="fas fa-network-wired"></i></span>
                            <span class="nav-text font-weight-bolder">Master Mapping Outlet - Coverage</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#kt_tab_unmapped_sellout" id="tabUnmappedLink">
                            <span class="nav-icon"><i class="fas fa-exclamation-triangle text-danger"></i></span>
                            <span class="nav-text font-weight-bolder">Outlet Sellout Tanpa Master</span>
                            <span class="badge badge-danger font-weight-bolder ml-2"
                                id="badgeUnmappedCount">{{ $totalUnmappedSellout }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#kt_tab_uncovered_pilar" id="tabUncoveredLink">
                            <span class="nav-icon"><i class="fas fa-user-times text-warning"></i></span>
                            <span class="nav-text font-weight-bolder">Outlet Belum Ada Coverage Pilar</span>
                            <span class="badge badge-warning font-weight-bolder ml-2"
                                id="badgeUncoveredCount">{{ $totalUncoveredPilar }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body p-4 p-xl-6">
            <div class="tab-content">
                <!-- TAB 1: MASTER MAPPING OUTLET - COVERAGE -->
                <div class="tab-pane fade show active" id="kt_tab_mapping" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="font-weight-bolder text-dark mb-1">Daftar Mapping Master Outlet ke Coverage</h4>
                            <span class="text-muted font-size-sm">Hubungan Master Customer (ID Konversi) dengan Coverage dan
                                Sales Rep</span>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <button type="button" class="btn btn-sm btn-xl-md btn-warning font-weight-bolder mr-2"
                                id="btnBulkUpdate">
                                <i class="fas fa-exchange-alt mr-1"></i> Bulk Update
                            </button>
                            <button type="button" class="btn btn-sm btn-xl-md btn-primary font-weight-bolder"
                                id="btnTambah">
                                <i class="fas fa-plus mr-1"></i> Tambah Mapping
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-separate table-head-custom table-checkable w-100" id="tblMapping">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px">No</th>
                                    <th>Kode Outlet (ID Konversi)</th>
                                    <th>Nama Outlet</th>
                                    <th>Segment</th>
                                    <th class="text-center">Pilar</th>
                                    <th class="text-center">ID Coverage</th>
                                    <th class="text-center">NIK</th>
                                    <th>Nama Sales</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 100px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: OUTLET SELLOUT TANPA MASTER (ID KONVERSI) -->
                <div class="tab-pane fade" id="kt_tab_unmapped_sellout" role="tabpanel">
                    <div class="alert alert-custom alert-light-danger fade show mb-4 p-4" role="alert">
                        <div class="alert-icon"><i class="fas fa-info-circle text-danger"></i></div>
                        <div class="alert-text font-size-sm">
                            <strong>Master Distributor Belum Terdaftar:</strong> Outlet di bawah ini tercatat dalam data
                            Sellout, namun <strong>belum memiliki ID Konversi</strong> di Master
                            Customer. Gunakan tombol <strong>Export XLSX</strong> untuk mengisi secara massal atau klik
                            <strong>Petakan</strong> untuk menyambungkan ke Master.
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="font-weight-bolder text-dark mb-1">Outlet Sellout Tanpa Master (ID Konversi)</h4>
                            <span class="text-muted font-size-sm">Data transaksi dari KFTD, AAM, dan METD yang belum
                                memiliki ID Konversi CRM</span>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <a href="{{ route('outlet.unmapped.export') }}"
                                class="btn btn-sm btn-success font-weight-bolder mr-2" id="btnExportUnmapped">
                                <i class="fas fa-file-excel mr-1"></i> Export XLSX
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold"
                                id="btnRefreshUnmapped">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-separate table-head-custom table-checkable w-100"
                            id="tblUnmappedSellout">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px">No</th>
                                    <th class="text-center">Dist</th>
                                    <th>Kode Cabang</th>
                                    <th>Kode Outlet Dist</th>
                                    <th>Nama Outlet Dist</th>
                                    <th>Segment Dist</th>
                                    <th class="text-center" style="width: 100px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: OUTLET SELLOUT BELUM ADA COVERAGE PILAR -->
                <div class="tab-pane fade" id="kt_tab_uncovered_pilar" role="tabpanel">
                    <div class="alert alert-custom alert-light-warning fade show mb-4 p-4" role="alert">
                        <div class="alert-icon"><i class="fas fa-exclamation-triangle text-warning"></i></div>
                        <div class="alert-text font-size-sm">
                            <strong>Pilar Coverage Belum Ada:</strong> Outlet di bawah ini <strong>sudah memiliki ID
                                Konversi</strong> di Master Customer, namun terdapat transaksi penjualan pada <strong>Pilar
                                yang belum ditugaskan ke Sales PIC</strong>. Klik tombol <strong>Tetapkan Coverage</strong>
                            untuk menugaskan sales PIC peng-cover pilar tersebut.
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="font-weight-bolder text-dark mb-1">Outlet Sellout Belum Ter-Coverage Pilar</h4>
                            <span class="text-muted font-size-sm">Master outlet yang memerlukan penetapan Sales Rep untuk
                                pilar transaksi bersangkutan</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold"
                                id="btnRefreshUncovered">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-separate table-head-custom table-checkable w-100"
                            id="tblUncoveredPilar">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px">No</th>
                                    <th>Kode Master (ID Konversi)</th>
                                    <th>Nama Master Outlet</th>
                                    <th>Segment Master</th>
                                    <th class="text-center">Pilar Transaksi</th>
                                    <th class="text-center">Jml Outlet Dist</th>
                                    <th class="text-center" style="width: 160px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('page.outlet.modal')
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Inisialisasi DataTable Mapping (Tab 1)
            window.tableMapping = $('#tblMapping').DataTable({
                processing: true,
                serverSide: true,
                searchDelay: 700,
                autoWidth: false,
                ajax: '{{ route('outlet.data') }}',
                initComplete: function() {
                    var api = this.api();
                    var $input = $('#tblMapping_filter input');
                    var timer = null;

                    $input
                        .off('.DT')
                        .attr('placeholder', 'Min. 2 karakter')
                        .on('keyup search', function(e) {
                            var value = this.value.trim();

                            if (e.type === 'keyup' && e.key === 'Enter') {
                                clearTimeout(timer);
                                if (value.length === 0 || value.length >= 2) {
                                    api.search(value).draw();
                                }
                                return;
                            }

                            clearTimeout(timer);
                            timer = setTimeout(function() {
                                if (value.length === 0 || value.length >= 2) {
                                    api.search(value).draw();
                                }
                            }, 700);
                        });
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'id_outlet',
                        name: 'cov.id_konversi'
                    },
                    {
                        data: 'nama_outlet',
                        name: 'master.nama_outlet',
                        defaultContent: '-'
                    },
                    {
                        data: 'segment',
                        name: 'master.segment',
                        defaultContent: '-'
                    },
                    {
                        data: 'pilar',
                        name: 'cov.pilar',
                        defaultContent: '-',
                        className: 'text-center'
                    },
                    {
                        data: 'id_coverage',
                        name: 'cov.id_coverage',
                        className: 'text-center'
                    },
                    {
                        data: 'cov_nik',
                        name: 'mapping.cov_nik',
                        defaultContent: '-',
                        className: 'text-center'
                    },
                    {
                        data: 'user_name',
                        name: 'users.name',
                        defaultContent: '-'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        className: 'text-center'
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center text-nowrap',
                        width: '100px'
                    },
                ]
            });

            // Lazy initialization untuk DataTable Unmapped (Tab 2)
            window.tableUnmappedInitialized = false;

            function initUnmappedTable() {
                if (window.tableUnmappedInitialized) return;

                window.tableUnmapped = $('#tblUnmappedSellout').DataTable({
                    processing: true,
                    serverSide: true,
                    searchDelay: 700,
                    autoWidth: false,
                    ajax: '{{ route('outlet.unmapped.data') }}',
                    initComplete: function() {
                        var api = this.api();
                        var $input = $('#tblUnmappedSellout_filter input');
                        var timer = null;

                        $input
                            .off('.DT')
                            .attr('placeholder', 'Cari cabang/outlet...')
                            .on('keyup search', function(e) {
                                var value = this.value.trim();

                                if (e.type === 'keyup' && e.key === 'Enter') {
                                    clearTimeout(timer);
                                    if (value.length === 0 || value.length >= 2) {
                                        api.search(value).draw();
                                    }
                                    return;
                                }

                                clearTimeout(timer);
                                timer = setTimeout(function() {
                                    if (value.length === 0 || value.length >= 2) {
                                        api.search(value).draw();
                                    }
                                }, 700);
                            });
                    },
                    columns: [{
                            data: 'DT_RowIndex',
                            orderable: false,
                            searchable: false,
                            className: 'text-center'
                        },
                        {
                            data: 'dist',
                            name: 'so.dist',
                            className: 'text-center font-weight-bolder'
                        },
                        {
                            data: 'id_cbg',
                            name: 'so.id_cbg'
                        },
                        {
                            data: 'id_outlet',
                            name: 'so.id_outlet',
                            className: 'font-weight-bold'
                        },
                        {
                            data: 'nama_outlet',
                            name: 'so.nama_outlet',
                            defaultContent: '-'
                        },
                        {
                            data: 'segment_dist',
                            name: 'so.segment_dist',
                            defaultContent: '-'
                        },
                        {
                            data: 'action',
                            orderable: false,
                            searchable: false,
                            className: 'text-center text-nowrap',
                            width: '100px'
                        },
                    ]
                });

                window.tableUnmappedInitialized = true;
            }

            // Lazy initialization untuk DataTable Uncovered Pilar (Tab 3)
            window.tableUncoveredInitialized = false;

            function initUncoveredTable() {
                if (window.tableUncoveredInitialized) return;

                window.tableUncovered = $('#tblUncoveredPilar').DataTable({
                    processing: true,
                    serverSide: true,
                    searchDelay: 700,
                    autoWidth: false,
                    ajax: '{{ route('outlet.uncovered.data') }}',
                    initComplete: function() {
                        var api = this.api();
                        var $input = $('#tblUncoveredPilar_filter input');
                        var timer = null;

                        $input
                            .off('.DT')
                            .attr('placeholder', 'Cari ID konversi/nama...')
                            .on('keyup search', function(e) {
                                var value = this.value.trim();

                                if (e.type === 'keyup' && e.key === 'Enter') {
                                    clearTimeout(timer);
                                    if (value.length === 0 || value.length >= 2) {
                                        api.search(value).draw();
                                    }
                                    return;
                                }

                                clearTimeout(timer);
                                timer = setTimeout(function() {
                                    if (value.length === 0 || value.length >= 2) {
                                        api.search(value).draw();
                                    }
                                }, 700);
                            });
                    },
                    columns: [{
                            data: 'DT_RowIndex',
                            orderable: false,
                            searchable: false,
                            className: 'text-center'
                        },
                        {
                            data: 'id_konversi',
                            name: 'so.id_konversi',
                            className: 'font-weight-bold'
                        },
                        {
                            data: 'nama_outlet',
                            name: 'master.nama_outlet',
                            defaultContent: '-'
                        },
                        {
                            data: 'segment',
                            name: 'master.segment',
                            defaultContent: '-'
                        },
                        {
                            data: 'pilar',
                            name: 'pilar',
                            className: 'text-center',
                            searchable: false
                        },
                        {
                            data: 'total_dist_outlets',
                            name: 'total_dist_outlets',
                            className: 'text-center',
                            searchable: false
                        },
                        {
                            data: 'action',
                            orderable: false,
                            searchable: false,
                            className: 'text-center text-nowrap',
                            width: '160px'
                        },
                    ]
                });

                window.tableUncoveredInitialized = true;
            }

            // Tab change event handler
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                var target = $(e.target).attr("href");
                if (target === '#kt_tab_unmapped_sellout') {
                    if (!window.tableUnmappedInitialized) {
                        initUnmappedTable();
                    } else if (window.tableUnmapped) {
                        window.tableUnmapped.columns.adjust().draw();
                    }
                } else if (target === '#kt_tab_uncovered_pilar') {
                    if (!window.tableUncoveredInitialized) {
                        initUncoveredTable();
                    } else if (window.tableUncovered) {
                        window.tableUncovered.columns.adjust().draw();
                    }
                } else if (target === '#kt_tab_mapping') {
                    if (window.tableMapping) {
                        window.tableMapping.columns.adjust().draw();
                    }
                }
            });

            $('#btnExportUnmapped').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var originalHtml = $btn.html();
                $btn.addClass('disabled').html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengunduh...');

                fetch('{{ route('outlet.unmapped.export') }}')
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Gagal mengunduh file export.');
                        }
                        var filename = 'Outlet_Sellout_Belum_Termapping.xlsx';
                        var disposition = response.headers.get('content-disposition');
                        if (disposition && disposition.indexOf('filename=') !== -1) {
                            var matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                            if (matches != null && matches[1]) {
                                filename = matches[1].replace(/['"]/g, '');
                            }
                        }
                        return response.blob().then(function(blob) {
                            return {
                                blob: blob,
                                filename: filename
                            };
                        });
                    })
                    .then(function(data) {
                        var url = window.URL.createObjectURL(data.blob);
                        var a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = data.filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        a.remove();
                    })
                    .catch(function(err) {
                        Swal.fire('Error', err.message || 'Gagal mendownload file Excel.', 'error');
                    })
                    .finally(function() {
                        $btn.removeClass('disabled').html(originalHtml);
                        if (typeof KTApp !== 'undefined' && typeof KTApp.hidePageLoading ===
                            'function') {
                            KTApp.hidePageLoading();
                        }
                        $('body').removeClass('page-loading');
                    });
            });

            $('#btnRefreshUnmapped').on('click', function() {
                if (window.tableUnmapped) {
                    window.tableUnmapped.ajax.reload(null, false);
                }
                updateUnmappedCounters();
            });

            $('#btnRefreshUncovered').on('click', function() {
                if (window.tableUncovered) {
                    window.tableUncovered.ajax.reload(null, false);
                }
                updateUnmappedCounters();
            });

            window.updateUnmappedCounters = function() {
                $.get('{{ route('outlet.unmapped.stats') }}', function(res) {
                    $('#badgeUnmappedCount').text(res.total_unmapped_outlet || 0);
                    $('#badgeUncoveredCount').text(res.total_uncovered_pilar || 0);
                });
            };
        });
    </script>
@endpush
