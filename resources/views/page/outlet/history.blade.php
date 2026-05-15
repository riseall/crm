@extends('layouts.app', ['title' => 'History Outlet'])

@section('content')
    <div class="card card-custom gutter-b">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col mb-4">
                    <label class="font-weight-bolder">Bulan</label>
                    <select id="filter_bulan" class="form-control">
                        @foreach ($months as $num => $label)
                            <option value="{{ $num }}" @selected((int) $currentMonth === (int) $num)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col mb-4">
                    <label class="font-weight-bolder">Tahun</label>
                    <select id="filter_tahun" class="form-control">
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected((int) $currentYear === (int) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col mb-4">
                    <label class="font-weight-bolder">Coverage</label>
                    <select id="filter_coverage_id" class="form-control">
                        <option value="">Semua Coverage</option>
                        @foreach ($coverageList as $cov)
                            <option value="{{ $cov->id_coverage }}">
                                {{ $cov->id_coverage }} - {{ $cov->cov_nik }}
                                @if ($cov->user)
                                    - {{ $cov->user->name }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col mb-4 text-right">
                    <button class="btn btn-primary font-weight-bolder" id="btnApplyHistoryFilter">
                        <i class="fas fa-search"></i> Tampilkan
                    </button>
                    <button type="button" class="btn btn-light-primary font-weight-bolder ml-2" id="btnAllOutlets">
                        <i class="fas fa-list"></i> Semua Outlet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <x-datatable-card title="History Outlet"
        subtitle="Riwayat outlet beserta status coverage dan total netto pada periode tersebut" table-id="tblHistoryOutlet"
        table-class="table-sm" :load-assets="false">
        <th>#</th>
        <th>Dist</th>
        <th>Kode Outlet</th>
        <th>Nama Outlet</th>
        <th>Coverage</th>
        <th>Coverage Desc</th>
        <th>NIK</th>
        <th>Nama</th>
        <th>Level</th>
        <th>Pilar</th>
        <th>Valid From</th>
        <th>Valid To</th>
        <th>Status</th>
        <th>Transaksi</th>
        <th>Netto</th>
    </x-datatable-card>
@endsection

@push('scripts')
    <script src="{{ asset('js/datatables.bundle.js') }}"></script>
    <script>
        $(document).ready(function() {
            const defaultMonth = '{{ $currentMonth }}';
            const defaultYear = '{{ $currentYear }}';

            $('#filter_bulan').val(defaultMonth);
            $('#filter_tahun').val(defaultYear);

            const table = $('#tblHistoryOutlet').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                searchDelay: 400,
                ajax: {
                    url: '{{ route('outlet.history.data') }}',
                    data: d => $.extend({}, d, {
                        bulan: $('#filter_bulan').val(),
                        tahun: $('#filter_tahun').val(),
                        coverage_id: $('#filter_coverage_id').val(),
                    })
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'dist',
                        name: 'so.dist',
                        searchable: false
                    },
                    {
                        data: 'id_outlet',
                        name: 'so.id_outlet',
                        searchable: false
                    },
                    {
                        data: 'nama_outlet',
                        name: 'vcd.nama_outlet',
                        searchable: false
                    },
                    {
                        data: 'id_coverage',
                        name: 'mo.id_coverage',
                        searchable: false,
                        render: (data) => `<span class="font-weight-bold">${data}</span>`
                    },
                    {
                        data: 'cov_desc',
                        name: 'mc.cov_desc',
                        defaultContent: '-',
                        searchable: false
                    },
                    {
                        data: 'cov_nik',
                        name: 'mc.cov_nik',
                        searchable: false
                    },
                    {
                        data: 'user_name',
                        name: 'ma.name',
                        searchable: false,
                        render: function(data) {
                            return data || '-';
                        }
                    },
                    {
                        data: 'level_name',
                        name: 'mc.cov_level',
                        searchable: false,
                        render: v => `<span class="badge badge-light-primary">${v}</span>`
                    },
                    {
                        data: 'pilar',
                        name: 'vp.pilar',
                        searchable: false
                    },
                    {
                        data: 'valid_from',
                        name: 'mc.valid_from',
                        searchable: false
                    },
                    {
                        data: 'valid_to',
                        name: 'mc.valid_to',
                        searchable: false,
                        render: v => v || 'Sekarang'
                    },
                    {
                        data: 'status_label',
                        name: 'status_label',
                        searchable: false,
                        render: (v, t, r) =>
                            `<span class="badge ${r.status_class || 'badge-secondary'}">${v || 'Aktif'}</span>`,
                        className: 'text-center'
                    },
                    {
                        data: 'total_transaksi',
                        name: 'total_transaksi',
                        searchable: false,
                        className: 'text-right',
                        render: $.fn.dataTable.render.number('.', ',', 0)
                    },
                    {
                        data: 'total_netto',
                        name: 'total_netto',
                        searchable: false,
                        className: 'text-right',
                        render: (data) =>
                            `<strong>Rp ${parseFloat(data).toLocaleString('id-ID')}</strong>`
                    }
                ],
                order: [
                    [14, 'desc']
                ],
            });

            $('#btnApplyHistoryFilter').on('click', function() {
                table.ajax.reload(null, false);
            });

            $('#btnAllOutlets').on('click', function() {
                window.location.href = '{{ route('outlet.history') }}';
            });
        });
    </script>
@endpush
