<div class="card">
    <div class="card-header flex-wrap border-0 pt-6 pb-0">
        <div class="card-title">
            <h3 class="card-label">{{ $title }}
                <span class="d-block text-muted pt-2 font-size-lg">{{ $subtitle }}</span>
            </h3>
        </div>
        <div class="card-toolbar">
            <!--begin::Button-->
            <button class="btn btn-primary font-weight-bolder" data-toggle="modal" data-target="#uploadModal">
                <span class="svg-icon svg-icon-md">
                    <i class="flaticon-upload icon-md"></i>
                </span>Upload Data</button>
            <!--end::Button-->
        </div>
    </div>
    <div class="card-body">
        <table class="table table-separate table-head-custom table-striped table-hover text-nowrap datats">
            <thead>
                <tr class="text-center">
                    <th style="width: 5%">No.</th>
                    @foreach ($columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <!-- Modal-->
    <div class="modal fade" id="uploadModal" data-backdrop="static" tabindex="-1" role="dialog"
        aria-labelledby="uploadLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadLabel">Upload File CSV<span
                            class="d-block text-muted pt-2 font-size-sm">{{ $subtitle }}</span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i aria-hidden="true" class="ki ki-close"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ $upload }}" method="POST" enctype="multipart/form-data" id="dropfile">
                        @csrf
                        <div class="form-group">
                            <!-- Input file asli yang disembunyikan -->
                            <input type="file" class="d-none" id="csvFile" name="file" required accept=".csv">

                            <!-- Area visual yang didesain seperti Dropzone -->
                            <div class="dropzone-like-area" id="dropzoneLikeArea">
                                <i class="ki ki-file-up text-primary" style="font-size: 2.5rem;"></i>
                                <h3 class="dropzone-msg-title">Klik untuk mengunggah.</h3>
                                <p class="dropzone-msg-desc">File yang diizinkan: .csv, text/csv. Maksimal ukuran file:
                                    2 MB</p>
                                <p id="selected-file-name" class="mt-2 text-primary font-weight-bold d-none"></p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-danger font-weight-bold" data-dismiss="modal">
                                Close
                            </button>
                            <button type="submit" class="btn btn-success font-weight-bold" id="uploadFileBtn">
                                Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal-->
</div>

<style>
    .dropzone-like-area {
        border: 2px dashed #78bedf;
        border-radius: 8px;
        padding: 3rem 1rem;
        text-align: center;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .dropzone-like-area:hover {
        background-color: #f8f8f8;
    }

    .dropzone-msg-title {
        font-size: 1rem;
        font-weight: 500;
        color: #3f4254;
        margin-top: 0.5rem;
    }

    .dropzone-msg-desc {
        font-size: 0.875rem;
        color: #b5b5c3;
        margin-bottom: 0;
    }

    .modal-footer {
        border-top: none;
    }
</style>
