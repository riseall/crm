<div class="row mb-5">
    <div class="col-md-12">
        @php
            $dashboardAccess = $dashboardAccess ?? [];
            $drilldownLevels = $dashboardAccess['drilldown_levels'] ?? ($drilldownLevels ?? ['GM']);
            $drilldownDefaultLevel = $dashboardAccess['default_drilldown_level'] ?? ($drilldownDefaultLevel ?? ($drilldownLevels[0] ?? 'GM'));
        @endphp
        <div class="card card-custom">
            <div class="card-header flex-wrap py-3">
                <div class="card-title">
                    <div class="d-flex">
                        <h3 class="card-label">Hierarki Performance</h3>
                        <span class="text-muted font-size-sm ml-3">
                            Klik level untuk filter, klik <i class="fas fa-chevron-right fa-xs"></i> untuk masuk ke
                            bawahan
                        </span>
                    </div>
                </div>
                <div class="card-toolbar">
                    {{-- Breadcrumb navigasi drill-down --}}
                    <div class="drill-path" id="drillPath">
                        <span class="crumb active" data-level="{{ $drilldownDefaultLevel }}" data-parent="">
                            {{ $drilldownDefaultLevel }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">

                {{-- Tombol pilih level --}}
                <div class="mb-4 d-flex align-items-center">
                    <span class="text-muted font-size-sm mr-3">Tampilkan level:</span>
                    @foreach ($drilldownLevels as $lv)
                        <button
                            class="btn btn-sm btn-outline-primary mr-2 btn-drill-level {{ $lv === $drilldownDefaultLevel ? 'active' : '' }}"
                            data-level="{{ $lv }}">
                            {{ $lv }}
                        </button>
                    @endforeach
                </div>

                {{-- Konten tabel drill-down --}}
                <div id="drilldownTable">
                    <div class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="text-muted mt-2">Memuat data...</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
