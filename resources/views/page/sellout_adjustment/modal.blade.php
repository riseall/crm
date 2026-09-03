<!-- Modal Single Edit -->
<x-modal id="modalSingleEdit" title="Ubah Divisi Transaksi" size="modal-md" submitBtnId="btnSaveSingleEdit" submitBtnText="Simpan Perubahan">
    <input type="hidden" id="single_edit_id">

    <div class="card bg-light border p-3 mb-4 rounded">
        <div class="row">
            <div class="col-6 mb-2">
                <span class="text-muted font-size-xs d-block">No Faktur:</span>
                <strong id="single_info_faktur" class="text-dark font-size-sm">-</strong>
            </div>
            <div class="col-6 mb-2">
                <span class="text-muted font-size-xs d-block">Pilar:</span>
                <strong id="single_info_pilar" class="text-dark font-size-sm">-</strong>
            </div>
            <div class="col-12 mb-2">
                <span class="text-muted font-size-xs d-block">Outlet:</span>
                <strong id="single_info_outlet" class="text-dark font-size-sm">-</strong>
            </div>
            <div class="col-12 mb-2">
                <span class="text-muted font-size-xs d-block">Produk:</span>
                <strong id="single_info_product" class="text-dark font-size-sm">-</strong>
            </div>
            <div class="col-12">
                <span class="text-muted font-size-xs d-block">Nilai Netto:</span>
                <strong id="single_info_netto" class="text-primary font-size-base">Rp 0</strong>
            </div>
        </div>
    </div>

    <div class="form-group mb-3">
        <label class="font-weight-bold">Divisi Baru <span class="text-danger">*</span></label>
        <select id="single_edit_divisi" class="form-control selectpicker">
            <option value="">-- Pilih Divisi --</option>
            @foreach ($divisiOptions as $div)
                <option value="{{ $div }}">{{ $div }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" id="error_single_divisi"></div>
    </div>

    <div class="form-group mb-1">
        <label class="font-weight-bold">Sub Divisi Baru <span class="text-danger">*</span></label>
        <select id="single_edit_sub_divisi" class="form-control selectpicker">
            <option value="">-- Pilih Sub Divisi --</option>
            @foreach ($subDivisiOptions as $sub)
                <option value="{{ $sub }}">{{ $sub }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" id="error_single_sub_divisi"></div>
    </div>
</x-modal>

<!-- Modal Bulk Edit (Checkbox Selected) -->
<x-modal id="modalBulkEdit" title="Adjust Divisi Data Terpilih" size="modal-md" submitBtnId="btnSaveBulkEdit" submitBtnText="Terapkan Perubahan">
    <div class="alert alert-custom alert-light-primary fade show mb-4 p-3" role="alert">
        <div class="alert-icon"><i class="fas fa-check-double text-primary"></i></div>
        <div class="alert-text font-weight-bold font-size-sm">
            Anda akan mengubah divisi untuk <span id="bulk_selected_count" class="badge badge-primary font-size-sm px-2">0</span> data transaksi sellout yang telah dipilih.
        </div>
    </div>

    <div class="form-group mb-3">
        <label class="font-weight-bold">Ubah Divisi Menjadi <span class="text-danger">*</span></label>
        <select id="bulk_edit_divisi" class="form-control selectpicker">
            <option value="">-- Pilih Divisi Target --</option>
            @foreach ($divisiOptions as $div)
                <option value="{{ $div }}">{{ $div }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" id="error_bulk_divisi"></div>
    </div>

    <div class="form-group mb-1">
        <label class="font-weight-bold">Sub Divisi Target <span class="text-danger">*</span></label>
        <select id="bulk_edit_sub_divisi" class="form-control selectpicker">
            <option value="">-- Pilih Sub Divisi Target --</option>
            @foreach ($subDivisiOptions as $sub)
                <option value="{{ $sub }}">{{ $sub }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" id="error_bulk_sub_divisi"></div>
    </div>
</x-modal>
