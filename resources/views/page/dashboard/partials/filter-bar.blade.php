{{-- resources/views/page/dashboard/partials/filter-bar.blade.php --}}
<div class="card card-custom mb-5">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-md">
                <label class="small text-muted mb-1">Bulan</label>
                <select id="filter_bulan" class="form-control form-control-sm">
                    @foreach ($months as $num => $name)
                        <option value="{{ $num }}" {{ $num == date('m') ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md">
                <label class="small text-muted mb-1">Tahun</label>
                <select id="filter_tahun" class="form-control form-control-sm">
                    @for ($y = date('Y'); $y >= date('Y') - 3; $y--)
                        <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}
                        </option>
                    @endfor
                </select>
            </div>
            {{-- <div class="col-md">
                <label class="small text-muted mb-1">Level</label>
                <select id="filter_level" class="form-control form-control-sm">
                    <option value="">Semua Level</option>
                    @foreach ($levels as $lv)
                        <option value="{{ $lv }}">{{ $lv }}</option>
                    @endforeach
                </select>
            </div> --}}
            <div class="col-md">
                <label class="small text-muted mb-1">Pilar</label>
                <select id="filter_pilar" class="form-control form-control-sm">
                    <option value="">Semua Pilar</option>
                    @foreach ($pilars as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md">
                <label class="small text-muted mb-1">Distributor</label>
                <select id="filter_distributor" class="form-control form-control-sm">
                    <option value="">Semua</option>
                    <option value="KFTD">KFTD</option>
                    <option value="AAM">AAM</option>
                    <option value="METD">METD</option>
                </select>
            </div>
            <div class="col-md d-flex align-items-end">
                <button id="btnApplyFilter" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter"></i> Terapkan
                </button>
            </div>
        </div>
    </div>
</div>
