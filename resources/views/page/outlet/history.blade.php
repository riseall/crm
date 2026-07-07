@extends('layouts.app', ['title' => 'History Outlet'])

@section('content')
    <x-filter-bar :show-pilar="false" :show-coverage="true" :show-outlet="true" :show-period="true" :show-bulan="false"
        :show-tahun="false" title="History Outlet" subtitle="Riwayat outlet berdasarkan periode, coverage, dan outlet"
        icon="fa-store" id-coverage="filter_coverage_id" id-outlet="filter_outlet_id" id-period="filter_period"
        id-start-date="filter_start_date" id-end-date="filter_end_date" id-apply="btnApplyHistoryFilter" :show-reset="true"
        id-reset="btnResetHistoryFilter" />

    <x-datatable-card title="History Outlet"
        subtitle="Riwayat outlet beserta status coverage dan total netto pada periode tersebut" table-id="tblHistoryOutlet"
        table-class="table-separate table-head-custom table-checkable text-nowrap">
        <th>No</th>
        <th>Bulan</th>
        <th>Dist</th>
        <th>Kode Outlet</th>
        <th>Nama Outlet</th>
        {{-- <th>Coverage</th>
        <th>Coverage Desc</th> --}}
        <th>NIK</th>
        <th>Nama</th>
        <th>Level</th>
        <th>Pilar</th>
        {{-- <th>Status</th> --}}
        <th>Transaksi</th>
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
