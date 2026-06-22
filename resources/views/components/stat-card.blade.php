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

<div class="card card-custom gutter-b">
    <div class="card-body d-flex justify-content-between p-6">
        <div class="d-flex flex-column">
            <div class="d-flex justify-content-between mb-2">
                <span class="font-weight-bold text-muted text-uppercase font-size-sm mb-2">{{ $title }}</span>
                @if ($subtitle)
                    <span class="{{ $subtitleColor }} font-size-xs font-weight-bold ml-1"
                        @if ($subtitleId) id="{{ $subtitleId }}" @endif>{{ $subtitle }}</span>
                @endif
            </div>
            <span class="font-weight-bolder font-size-h2">
                <span @if ($valueId) id="{{ $valueId }}" @endif>{{ $value }}</span>
            </span>
        </div>
        <div class="symbol symbol-50 symbol-light-{{ $color }}">
            <span class="symbol-label">
                <i class="{{ $icon }} text-{{ $color }} icon-lg"></i>
            </span>
        </div>
    </div>
</div>
