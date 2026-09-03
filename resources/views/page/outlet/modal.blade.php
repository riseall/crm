<x-modal id="modalMapping" title="Tambah Mapping" submitBtnId="btnSimpan">
    <input type="hidden" id="mappingId">
    <div class="form-group">
        <label>Outlet <span class="text-danger">*</span></label>
        <select id="id_outlet" class="form-control select2-outlet" style="width:100%"></select>
    </div>
    <div class="form-group">
        <label>Coverage <span class="text-danger">*</span></label>
        <select id="id_coverage" class="form-control select2-coverage-modal" style="width:100%"></select>
    </div>
</x-modal>

<x-modal id="modalBulkUpdate" title="Bulk Update Coverage" size="modal-lg" submitBtnId="btnBulkSimpan"
    submitBtnText="Pindahkan">
    <div class="form-group">
        <label>Coverage Lama <span class="text-danger">*</span></label>
        <select id="old_coverage_id" class="form-control select2-coverage-modal" style="width:100%"></select>
    </div>
    <div class="form-group">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="mb-0 font-weight-bold">Pilih Outlet yang Dipindah <span class="text-danger">*</span></label>
            <span class="badge badge-light-primary text-primary font-weight-bolder" id="outletCountBadge" style="display: none;">0 dipilih</span>
        </div>
        <div id="outletList" class="border p-3 rounded bg-light">
            <small class="text-muted">Pilih coverage lama terlebih dahulu.</small>
        </div>
    </div>
    <div class="form-group">
        <label>Coverage Baru <span class="text-danger">*</span></label>
        <select id="new_coverage_id" class="form-control select2-coverage-modal" style="width:100%"></select>
    </div>
</x-modal>

<x-modal id="modalMapUnmapped" title="Petakan Outlet Sellout ke Master Customer" size="modal-lg" submitBtnId="btnSubmitMapUnmapped"
    submitBtnText="Simpan Pemetaan">
    <!-- Info Ringkasan Outlet Dist -->
    <div class="card card-custom bg-light border mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between pb-2 mb-2 border-bottom">
                <div class="d-flex align-items-center mb-1 mb-md-0">
                    <span class="badge badge-primary font-weight-bolder px-2 py-1 mr-2" id="unmapped_info_dist">-</span>
                    <span class="text-dark font-weight-bold" id="unmapped_info_cbg">-</span>
                </div>
                <div>
                    <span class="badge badge-light-dark font-weight-bold" id="unmapped_info_segment">-</span>
                </div>
            </div>
            <div class="row font-size-sm">
                <div class="col-md-7 mb-2 mb-md-0">
                    <span class="text-muted font-size-xs d-block">Nama Outlet di Distributor:</span>
                    <strong id="unmapped_info_nama" class="text-dark font-size-base">-</strong>
                </div>
                <div class="col-md-5">
                    <span class="text-muted font-size-xs d-block">Kode Outlet Dist:</span>
                    <strong id="unmapped_info_outlet" class="text-dark">-</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Fields -->
    <input type="hidden" id="map_dist">
    <input type="hidden" id="map_id_cbg">
    <input type="hidden" id="map_id_outlet">
    <input type="hidden" name="map_mode" id="map_mode_val" value="existing">

    <!-- Pilih Mode Pemetaan (Cards) -->
    <div class="form-group mb-4">
        <label class="font-weight-bolder text-dark font-size-sm text-uppercase d-block mb-2">Metode Pemetaan <span class="text-danger">*</span></label>
        <div class="row">
            <div class="col-sm-6 mb-2 mb-sm-0">
                <div class="custom-mode-card active-primary p-3" id="cardModeExisting" data-mode="existing">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40 symbol-light-primary mr-3 flex-shrink-0">
                            <span class="symbol-label font-size-h5">
                                <i class="fas fa-link text-primary"></i>
                            </span>
                        </div>
                        <div>
                            <div class="font-weight-bolder text-dark">Master Eksisting</div>
                            <div class="text-muted font-size-xs">Hubungkan ke ID Konversi yang sudah ada</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="custom-mode-card p-3" id="cardModeNew" data-mode="new">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40 symbol-light-success mr-3 flex-shrink-0">
                            <span class="symbol-label font-size-h5">
                                <i class="fas fa-plus-circle text-success"></i>
                            </span>
                        </div>
                        <div>
                            <div class="font-weight-bolder text-dark">Master Baru</div>
                            <div class="text-muted font-size-xs">Buat data Master Customer baru</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 1: Hubungkan ke Master Eksisting -->
    <div id="sectionExistingMaster" class="p-3 border rounded bg-white mb-3">
        <div class="form-group mb-0">
            <label class="font-weight-bold text-dark mb-1">Pilih Master Customer (ID Konversi) <span class="text-danger">*</span></label>
            <select id="map_existing_id_konversi" class="form-control select2-unmapped-master" style="width: 100%"></select>
            <span class="form-text text-muted font-size-xs mt-1">
                <i class="fas fa-info-circle mr-1 text-primary"></i> Ketik min. 2 karakter untuk mencari ID Konversi atau Nama Master Outlet di database CRM
            </span>
        </div>
    </div>

    <!-- SECTION 2: Buat Master Outlet Baru -->
    <div id="sectionNewMaster" class="p-3 border rounded bg-white mb-3" style="display: none;">
        <div class="row">
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">ID Konversi Baru <span class="text-danger">*</span></label>
                <input type="text" id="map_new_id_konversi" class="form-control" placeholder="Contoh: KFTD_100123 / KODE">
                <span class="form-text text-muted font-size-xs">ID unik master customer di CRM</span>
            </div>
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">Nama Master Outlet <span class="text-danger">*</span></label>
                <input type="text" id="map_new_nama_outlet" class="form-control" placeholder="Nama Master Outlet">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">Segment</label>
                <select id="map_new_segment" class="form-control">
                    <option value="">-- Pilih Segment --</option>
                    <option value="APOTEK">APOTEK</option>
                    <option value="RUMAH SAKIT">RUMAH SAKIT</option>
                    <option value="KLINIK">KLINIK</option>
                    <option value="TOKO OBAT">TOKO OBAT</option>
                    <option value="PBF">PBF</option>
                    <option value="DOKTER">DOKTER</option>
                    <option value="INSTITUSI">INSTITUSI</option>
                    <option value="LAIN-LAIN">LAIN-LAIN</option>
                </select>
            </div>
            <div class="col-md-6 form-group mb-3">
                <label class="font-weight-bold text-dark">Kota / Kabupaten</label>
                <input type="text" id="map_new_kota" class="form-control" placeholder="Kota atau Kabupaten">
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="font-weight-bold text-dark">Alamat Lengkap (Opsional)</label>
            <textarea id="map_new_alamat" class="form-control" rows="2" placeholder="Alamat lengkap outlet..."></textarea>
        </div>
    </div>

    <!-- SECTION 3: Coverage Assignment (Opsional/Direkomendasikan) -->
    <div class="p-3 border rounded bg-light mb-1">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-user-check text-primary mr-2"></i>
            <h6 class="font-weight-bolder text-dark mb-0 font-size-sm">
                Penugasan Coverage & Sales Rep <span class="text-muted font-weight-normal font-size-xs">(Opsional)</span>
            </h6>
        </div>
        <div class="row">
            <div class="col-md-4 form-group mb-2 mb-md-0">
                <label class="font-weight-bold text-dark font-size-xs text-uppercase">Pilar</label>
                <select id="map_pilar" class="form-control">
                    <option value="ETHICAL">ETHICAL</option>
                    <option value="OGB">OGB</option>
                    <option value="CH">CH</option>
                </select>
            </div>
            <div class="col-md-8 form-group mb-0">
                <label class="font-weight-bold text-dark font-size-xs text-uppercase">Coverage (Sales Rep)</label>
                <select id="map_id_coverage" class="form-control select2-unmapped-coverage" style="width: 100%"></select>
                <span class="form-text text-muted font-size-xs mt-1">Pilih sales rep yang meng-cover outlet ini</span>
            </div>
        </div>
    </div>
</x-modal>

<x-modal id="modalAssignPilarCoverage" title="Tetapkan Coverage Sales Rep (Pilar)" submitBtnId="btnSubmitAssignPilar"
    submitBtnText="Simpan Coverage">
    <div class="card card-custom bg-light border mb-4">
        <div class="card-body p-3">
            <div class="row font-size-sm">
                <div class="col-md-7 mb-2 mb-md-0">
                    <span class="text-muted font-size-xs d-block">ID Konversi & Nama Master:</span>
                    <strong id="assign_info_id_konversi" class="text-primary font-weight-bolder font-size-base">-</strong>
                    <div id="assign_info_nama_outlet" class="text-dark font-weight-bold">-</div>
                </div>
                <div class="col-md-5">
                    <span class="text-muted font-size-xs d-block">Pilar Transaksi:</span>
                    <span class="badge badge-primary font-weight-bolder font-size-sm" id="assign_info_pilar">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Fields -->
    <input type="hidden" id="assign_id_konversi">
    <input type="hidden" id="assign_pilar">

    <div class="form-group mb-0">
        <label class="font-weight-bold text-dark">Pilih Coverage / Sales Rep <span class="text-danger">*</span></label>
        <select id="assign_id_coverage" class="form-control select2-assign-coverage" style="width: 100%"></select>
        <span class="form-text text-muted font-size-xs mt-1">Pilih sales rep yang akan meng-cover outlet ini pada pilar tersebut</span>
    </div>
</x-modal>
