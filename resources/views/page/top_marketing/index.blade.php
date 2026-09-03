@extends('layouts.app', ['title' => 'Top Marketing'])

@section('content')
    <div class="row">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TOTAL DATA" value="{{ number_format($totalData, 0, ',', '.') }}" subtitle="Baris"
                subtitleColor="text-primary" icon="far fa-folder-open" color="primary">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="TARGET BULAN INI" value="{{ number_format($currentMonthValue, 0, ',', '.') }}"
                subtitle="Val Nett" subtitleColor="text-success" icon="fas fa-chart-line" color="success">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="PERIODE TERBARU"
                value="{{ $latestPeriod ? \Carbon\Carbon::create((int) $latestPeriod->tahun, (int) $latestPeriod->bulan, 1)->translatedFormat('M Y') : '-' }}"
                subtitle="Data" subtitleColor="text-info" icon="far fa-calendar-alt" color="info">
            </x-stat-card>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <x-stat-card title="UPDATE TERAKHIR"
                value="{{ $latestUpload ? \Carbon\Carbon::parse($latestUpload)->format('d/m/Y') : '-' }}" subtitle="Upload"
                subtitleColor="text-warning" icon="fas fa-history" color="warning">
            </x-stat-card>
        </div>
    </div>

    <x-datatable-card title="Top Marketing" subtitle="Upload dan monitoring target top marketing per bulan"
        table-id="table_top_marketing">
        <x-slot name="beforeTable">
            <div class="row align-items-end mb-5">
                <div class="col-md-3">
                    <label>Periode</label>
                    <input type="text" class="form-control top-marketing-monthpicker" id="filter_periode"
                        value="{{ now()->format('Y-m') }}" autocomplete="off">
                </div>
                <div class="col-md-9 text-md-right mt-4 mt-md-0">
                    <button type="button" class="btn btn-light-primary font-weight-bolder mr-2" id="btnRefresh">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-primary font-weight-bolder" data-toggle="modal"
                        data-target="#modalUploadTopMarketing">
                        <i class="fas fa-file-upload"></i> Upload Excel
                    </button>
                </div>
            </div>
        </x-slot>
        <th>No</th>
        <th>Periode</th>
        <th>Divisi</th>
        <th>Region</th>
        <th>Kode PH</th>
        <th>Nama Produk</th>
        <th>Pilar</th>
        <th>PM</th>
        <th>MR</th>
        <th>AM</th>
        <th>SAM</th>
        <th>RM</th>
        <th>GM</th>
        <th>Segment</th>
        <th>Cabang</th>
        <th>Unit</th>
        <th>Val Nett</th>
    </x-datatable-card>

    <div class="modal fade" id="modalUploadTopMarketing" data-backdrop="static" tabindex="-1" role="dialog"
        aria-labelledby="modalUploadTopMarketingLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUploadTopMarketingLabel">Upload Top Marketing
                        <span class="d-block text-muted pt-2 font-size-sm">Data bulan yang dipilih akan diganti dari file
                            upload.</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i aria-hidden="true" class="ki ki-close"></i>
                    </button>
                </div>
                <form id="formUploadTopMarketing" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Periode Upload</label>
                            <input type="text" class="form-control top-marketing-monthpicker" name="periode"
                                id="upload_periode" value="{{ now()->format('Y-m') }}" autocomplete="off" required>
                            <input type="hidden" name="tahun" id="upload_tahun" value="{{ now()->year }}">
                            <input type="hidden" name="bulan" id="upload_bulan" value="{{ now()->month }}">
                        </div>
                        <div class="form-group mb-0">
                            <label>File Excel</label>
                            <input type="file" class="form-control" name="file" id="upload_file" accept=".xlsx,.csv"
                                required>
                            <span class="form-text text-muted mt-2">
                                Belum punya template?
                                <a href="{{ asset('storage/template/Template_Upload_TOP_per_Bulan.xlsx') }}" download
                                    class="text-primary font-weight-bold">
                                    Unduh Template Excel di sini
                                </a>
                            </span>
                            <div class="invalid-feedback d-block" id="upload_file_error"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-danger font-weight-bold" data-dismiss="modal">
                            Close
                        </button>
                        <button type="submit" class="btn btn-success font-weight-bold" id="btnUploadTopMarketing">
                            Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.TopMarketingPageConfig = {
            routes: {
                data: '{{ route('top-marketing.data') }}',
                upload: '{{ route('top-marketing.upload') }}',
            }
        };
    </script>
    <script src="{{ asset('js/top_marketing/index.js') }}?v={{ filemtime(public_path('js/top_marketing/index.js')) }}">
    </script>
@endpush

