@extends('layouts.app', ['title' => 'Detail Performa Anggota', 'sidebar' => 'dashboard'])

@push('styles')
    <link rel="stylesheet"
        href="{{ asset('css/dashboard/detail-anggota.css') }}?v={{ filemtime(public_path('css/dashboard/detail-anggota.css')) }}">
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
                                {{ $outlet->outlet_name }} ({{ $outlet->id_konversi ?? $outlet->id_outlet }}) - Total Netto: Rp
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
        window.ANGGOTA_DETAIL = {
            trend: @json($trend ?? ['labels' => [], 'data' => []]),
            outlets: {!! $outletsJson ?? '[]' !!}
        };
    </script>
    <script
        src="{{ asset('js/dashboard/detail-anggota.js') }}?v={{ filemtime(public_path('js/dashboard/detail-anggota.js')) }}">
    </script>
@endpush

