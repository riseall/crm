@extends('layouts.app', ['title' => 'History Outlet'])

@section('content')
    <x-filter-bar :show-pilar="false" :show-coverage="true" :show-outlet="true" :show-period="true" :show-bulan="false"
        :show-tahun="false" title="History Outlet" subtitle="Riwayat outlet berdasarkan periode, coverage, dan outlet"
        icon="fa-store" id-coverage="filter_coverage_id" id-outlet="filter_outlet_id" id-period="filter_period"
        id-start-date="filter_start_date" id-end-date="filter_end_date" id-apply="btnApplyHistoryFilter" :show-reset="true"
        id-reset="btnResetHistoryFilter" />

    <x-datatable-card title="History Outlet"
        subtitle="Riwayat outlet beserta status coverage dan total netto pada periode tersebut" table-id="tblHistoryOutlet"
        table-class="table-separate table-head-custom table-checkable text-nowrap table-sm">
        <x-slot name="beforeTable">
            <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded bg-light">
                <div class="font-size-sm text-dark font-weight-bold" id="infoFilterSummary">
                    @if (request('coverage_id') == 'UNMAPPED')
                        <span class="badge badge-warning font-weight-bold mr-2"><i
                                class="fas fa-exclamation-triangle text-white mr-1"></i>Filter: Data Belum Termapping
                            (UNMAPPED)</span>
                    @elseif(request('coverage_id'))
                        <span class="badge badge-primary font-weight-bold mr-2">Coverage:
                            {{ request('coverage_id') }}</span>
                    @endif
                </div>
                <div class="font-size-sm font-weight-bolder text-primary">
                    Total Netto: <span id="totalNettoDisplay" class="font-weight-boldest text-dark font-size-h6">Rp 0</span>
                </div>
            </div>
        </x-slot>
        <th>No</th>
        <th>Bulan</th>
        <th>Dist</th>
        <th>Kode Outlet</th>
        <th>Kode Cabang</th>
        <th>Nama Outlet</th>
        <th>NIK</th>
        <th>Nama</th>
        <th>Pilar</th>
        <th>Netto</th>
    </x-datatable-card>
@endsection

@push('scripts')
    <script>
        window.OutletHistoryPage = {
            routes: {
                index: "{{ route('outlet.history') }}",
                data: "{{ route('outlet.history.data') }}",
                coverage: "{{ route('outlet.coverage') }}",
                outlet: "{{ route('outlet.outlet') }}",
            }
        };
    </script>
    <script
        src="{{ asset('js/dashboard/date-range-filter.js') }}?v={{ filemtime(public_path('js/dashboard/date-range-filter.js')) }}">
    </script>
    <script src="{{ asset('js/outlet/history.js') }}"></script>
@endpush
