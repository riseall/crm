<x-modal id="modalPm" title="Tambah PM" submitBtnId="btnSimpan">
    <input type="hidden" id="pmId">
    <div class="form-group">
        <label>Nama Produk <span class="text-danger">*</span></label>
        <select id="pt_ph" class="form-control select2-produk" style="width:100%"></select>
    </div>
    <div class="form-group">
        <label>Coverage <span class="text-danger">*</span></label>
        <select id="id_coverage_pm" class="form-control selectpicker" style="width:100%">
            <option value="">-- Pilih Coverage --</option>
            @foreach ($coverage as $cov)
                <option value="{{ $cov->id_coverage }}">
                    {{ $cov->id_coverage }} - {{ $cov->cov_nik }}
                    @if ($cov->user)
                        - {{ $cov->user->name }}
                    @endif
                </option>
            @endforeach
        </select>
    </div>
</x-modal>
