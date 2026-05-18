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
        :load-assets="true">
        <th>No</th>
        <th>Dist</th>
        <th>Kode Outlet</th>
        <th>Nama Outlet</th>
        {{-- <th>Coverage</th>
        <th>Coverage Desc</th> --}}
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
    <script>
        window.OutletHistoryPage = {
            defaultMonth: @json($currentMonth),
            defaultYear: @json($currentYear),
            routes: {
                index: "{{ route('outlet.history') }}",
                data: "{{ route('outlet.history.data') }}",
            }
        };
    </script>
    <script src="{{ asset('js/outlet/history.js') }}"></script>
@endpush
