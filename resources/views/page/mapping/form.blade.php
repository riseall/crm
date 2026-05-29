<input type="hidden" id="coverageId">

<div class="alert alert-danger d-none" id="formErrorAlert"></div>

<div class="form-group">
    <label>ID Coverage <span class="text-danger">*</span></label>
    <input type="text" id="id_coverage" class="form-control" placeholder="Contoh: 1.2.3.4.5">
    <div class="invalid-feedback" id="error_id_coverage"></div>
    <small class="text-muted" id="idCoverageHelp">Kode coverage bisa dipakai ulang. Sistem hanya menjaga 1 baris aktif
        untuk setiap ID.</small>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Ket. ID Coverage</label>
            <input type="text" id="cov_desc" class="form-control">
            <div class="invalid-feedback" id="error_cov_desc"></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Ket. CBG</label>
            <input type="text" id="cov_ket_cbg" class="form-control">
            <div class="invalid-feedback" id="error_cov_ket_cbg"></div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Divisi</label>
            <select id="cov_divisi" class="form-control selectpicker">
                <option value="">-- Pilih Divisi --</option>
                <option value="Marketing">Marketing</option>
                <option value="Reguler">Reguler</option>
                <option value="Non Reguler">Non Reguler</option>
            </select>
            <div class="invalid-feedback" id="error_cov_divisi"></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Pilar <span class="text-danger">*</span></label>
            <select id="cov_pilar" class="form-control selectpicker">
                <option value="">-- Pilih Pilar --</option>
                <option value="ETHICAL">ETHICAL</option>
                <option value="OGB">OGB</option>
                <option value="OTC">OTC</option>
            </select>
            <div class="invalid-feedback" id="error_cov_pilar"></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggung Jawab Pilar <span class="text-danger">*</span></label>
            <select id="tanggung_jwb_pilar" class="form-control selectpicker">
                <option value="Single">Single</option>
                <option value="Combo">Combo</option>
            </select>
            <div class="invalid-feedback" id="error_tanggung_jwb_pilar"></div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>NIK / Anggota <span class="text-danger">*</span></label>
            <select id="cov_nik" class="form-control select2-anggota" style="width:100%"></select>
            <div class="invalid-feedback d-block" id="error_cov_nik"></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Level <span class="text-danger">*</span></label>
            <select id="cov_level" class="form-control selectpicker">
                <option value="">-- Pilih Level --</option>
                <option value="GM">GM</option>
                <option value="RM">RM</option>
                <option value="SAM">SAM</option>
                <option value="AM">AM</option>
                <option value="MR/SR">MR/SR</option>
            </select>
            <div class="invalid-feedback" id="error_cov_level_desc"></div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Valid From <span class="text-danger">*</span></label>
            <div class="input-group date mb-2">
                <input type="text" id="valid_from" class="form-control mapping-monthpicker"
                    placeholder="Pilih bulan">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="far fa-calendar-alt"></i>
                    </span>
                </div>
            </div>
            <div class="invalid-feedback" id="error_valid_from"></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Valid To</label>
            <div class="input-group date mb-2">
                <input type="text" id="valid_to" class="form-control mapping-monthpicker"
                    placeholder="Pilih bulan">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="far fa-calendar-alt"></i>
                    </span>
                </div>
            </div>
            <div class="invalid-feedback" id="error_valid_to"></div>
        </div>
    </div>
</div>
