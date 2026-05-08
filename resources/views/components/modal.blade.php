@props([
    'id',
    'title' => 'Modal Title',
    'size' => 'modal-lg',
    'submitBtnId' => 'btnSimpan',
    'submitBtnText' => 'Simpan',
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label"
    aria-hidden="true">
    <div class="modal-dialog {{ $size }} modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}</h5>
                <button type="button" class="close text-dark" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-danger" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="{{ $submitBtnId }}">{{ $submitBtnText }}</button>
            </div>
        </div>
    </div>
</div>
