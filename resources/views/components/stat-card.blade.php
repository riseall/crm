@props([
    'title',
    'value',
    'subtitle' => '',
    'subtitleColor' => 'text-success',
    'icon' => 'fas fa-chart-bar',
    'color' => 'primary',
    'valueId' => null,
    'subtitleId' => null,
])

<div class="card card-custom gutter-b stat-card-responsive">
    <div class="card-body d-flex justify-content-between align-items-center p-3 p-xl-4">
        <div class="d-flex flex-column mr-2 min-w-0" style="min-width: 0;">
            <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap">
                <span
                    class="font-weight-bolder text-muted text-uppercase font-size-xs mb-1 text-truncate">{{ $title }}</span>
                @if ($subtitle)
                    <span class="{{ $subtitleColor }} font-size-xs font-weight-bold ml-1 text-nowrap"
                        @if ($subtitleId) id="{{ $subtitleId }}" @endif>{{ $subtitle }}</span>
                @endif
            </div>
            <span class="font-weight-bolder font-size-h4 font-size-xl-h3 text-truncate">
                <span @if ($valueId) id="{{ $valueId }}" @endif>{{ $value }}</span>
            </span>
        </div>
        <div class="symbol symbol-35 symbol-xl-40 symbol-light-{{ $color }} flex-shrink-0">
            <span class="symbol-label">
                <i class="{{ $icon }} text-{{ $color }} icon-nm icon-xl-md"></i>
            </span>
        </div>
    </div>
</div>
