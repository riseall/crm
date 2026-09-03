{{-- ===== MODAL DETAIL INFORMASI KUNJUNGAN ===== --}}
<div class="modal fade" id="modalVisitDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header modal-header-gradient text-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-bubble">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bolder text-white mb-0" style="font-size: 1.15rem;">
                            Detail Informasi Kunjungan
                        </h5>
                        <span class="text-white-50 font-size-xs">Sinkronisasi Aktivitas Live SFA KlikPeta & CRM</span>
                    </div>
                </div>
                <button type="button" class="btn btn-xs btn-icon text-white" data-dismiss="modal"
                    aria-label="Close"
                    style="background: rgba(255,255,255,0.18); border-radius: 50%; width: 30px; height: 30px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body p-6 bg-modal-neutral">
                <!-- Top Section: Sales Force & Outlet Info Cards -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="info-card-panel">
                            <div class="panel-label">
                                <i class="fas fa-user-tie text-primary mr-1"></i> Sales Force / MR
                            </div>
                            <div id="modal_sales_name" class="panel-title text-truncate" title="Nama Sales">-</div>
                            <div class="d-flex flex-wrap align-items-center">
                                <span id="modal_nik" class="badge badge-light-primary font-weight-bolder mr-2 mb-1">
                                    <i class="far fa-id-badge mr-1"></i><span>-</span>
                                </span>
                                <span id="modal_branch" class="badge badge-light-info font-weight-bolder mb-1">
                                    <i class="fas fa-building mr-1"></i><span>-</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card-panel">
                            <div class="panel-label">
                                <i class="fas fa-store-alt text-success mr-1"></i> Outlet / Lokasi Kunjungan
                            </div>
                            <div id="modal_outlet_name" class="panel-title text-truncate" title="Nama Outlet">-</div>
                            <div class="d-flex flex-wrap align-items-center">
                                <span id="modal_customer_id"
                                    class="badge badge-light-success font-weight-bolder mr-2 mb-1"
                                    title="ID Konversi (Location Code)">
                                    <i class="fas fa-barcode mr-1"></i><span>-</span>
                                </span>
                                <span id="modal_outlet_code"
                                    class="badge badge-light-secondary font-weight-bolder mb-1"
                                    title="Kode Outlet SFA">
                                    <i class="fas fa-tag mr-1"></i><span>-</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meta Strip: Waktu, Pilar, Status, Koordinat -->
                <div class="meta-strip-box mb-4">
                    <div class="row align-items-center">
                        <div class="col-6 col-md-3 mb-3 mb-md-0 border-right-md">
                            <div class="meta-strip-item">
                                <span class="meta-strip-label"><i class="far fa-calendar-alt mr-1 text-primary"></i>
                                    Waktu</span>
                                <span id="modal_visit_datetime" class="meta-strip-value font-size-sm">-</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3 mb-md-0 border-right-md">
                            <div class="meta-strip-item">
                                <span class="meta-strip-label"><i class="fas fa-layer-group mr-1 text-info"></i>
                                    Pilar</span>
                                <div>
                                    <span id="modal_pilar" class="badge badge-info font-weight-bolder">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 border-right-md">
                            <div class="meta-strip-item">
                                <span class="meta-strip-label"><i class="fas fa-check-circle mr-1 text-success"></i>
                                    Status</span>
                                <div>
                                    <span id="modal_status" class="badge badge-success font-weight-bolder">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="meta-strip-item">
                                <span class="meta-strip-label"><i class="fas fa-map-marker-alt mr-1 text-danger"></i>
                                    Koordinat</span>
                                <div id="modal_coordinates" class="meta-strip-value font-size-sm">
                                    -
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Visit Resume & Discussion -->
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="text-dark font-weight-bolder small mb-1 d-flex align-items-center">
                            <i class="fas fa-file-signature text-primary mr-1"></i> RINGKASAN KUNJUNGAN (VISIT RESUME)
                        </label>
                        <div id="modal_visit_resume" class="detail-note-card detail-note-resume">
                            -
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="text-dark font-weight-bolder small mb-1 d-flex align-items-center">
                            <i class="fas fa-comments text-warning mr-1"></i> CATATAN DISKUSI (CUSTOMER DISCUSSION)
                        </label>
                        <div id="modal_customer_discussion" class="detail-note-card detail-note-discussion">
                            -
                        </div>
                    </div>
                    <div class="col-12" id="modal_product_detailing_container">
                        <label class="text-dark font-weight-bolder small mb-1 d-flex align-items-center">
                            <i class="fas fa-pills text-success mr-1"></i> PRODUK YANG DIDETAIL (PRODUCT DETAILING)
                        </label>
                        <div id="modal_product_detailing" class="p-3 bg-white rounded border"
                            style="min-height: 48px;">
                            -
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white d-flex justify-content-between py-3 px-6 border-top">
                <a id="btn_modal_open_gmaps" href="#" target="_blank"
                    class="btn btn-light-primary btn-sm font-weight-bolder">
                    <i class="fas fa-external-link-alt mr-1"></i> Buka di Google Maps
                </a>
                <button type="button" class="btn btn-secondary btn-sm font-weight-bolder px-5" data-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
