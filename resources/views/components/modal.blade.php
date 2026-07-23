@props([
    'id',
    'title' => 'Modal Title',
    'size' => 'modal-lg',
    'submitBtnId' => 'btnSimpan',
    'submitBtnText' => 'Simpan',
])

<div class="modal fade" id="{{ $id }}" data-backdrop="static" tabindex="-1"
    aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog {{ $size }} modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-3 px-4 py-xl-4 px-xl-5">
                <h5 class="modal-title font-size-h5 font-weight-bold" id="{{ $id }}Label">{{ $title }}</h5>
                <button type="button" class="close text-dark" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4 p-xl-5">
                {{ $slot }}
            </div>
            <div class="modal-footer py-3 px-4 py-xl-4 px-xl-5">
                <button type="button" class="btn btn-sm btn-light-danger font-weight-bold" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary font-weight-bold" id="{{ $submitBtnId }}">{{ $submitBtnText }}</button>
            </div>
        </div>
    </div>
</div>
