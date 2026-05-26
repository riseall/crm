@extends('layouts.app', ['title' => 'History Outlet'])

@section('content')
    <x-filter-bar :months="$months" :years="$years" :show-pilar="false" :show-coverage="true" :bulan-multiple="true"
        :selected-month="$currentMonth" :selected-year="$currentYear" title="History Outlet"
        subtitle="Riwayat outlet berdasarkan bulan, tahun, dan coverage" icon="fa-store" btn-text="Tampilkan"
        id-bulan="filter_bulan" id-tahun="filter_tahun" id-coverage="filter_coverage_id" id-apply="btnApplyHistoryFilter" />

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
            defaultMonth: @json($currentMonth),
            defaultYear: @json($currentYear),
            routes: {
                index: "{{ route('outlet.history') }}",
                data: "{{ route('outlet.history.data') }}",
                coverage: "{{ route('outlet.coverage') }}",
            }
        };
    </script>
    <script src="{{ asset('js/outlet/history.js') }}"></script>
@endpush
