@props([
    'title',
    'value',
    'subtitle' => '',
    'subtitleColor' => 'text-success',
    'icon' => 'fas fa-chart-bar',
    'color' => 'primary',
])

<div class="card card-custom gutter-b">
    <div class="card-body d-flex align-items-center justify-content-between p-6">
        <div class="d-flex flex-column">
            <span class="font-weight-bold text-muted text-uppercase font-size-sm mb-2">{{ $title }}</span>
            <span class="font-weight-bolder font-size-h2">
                {{ $value }}
                @if ($subtitle)
                    <span class="{{ $subtitleColor }} font-size-sm font-weight-bold ml-1">{{ $subtitle }}</span>
                @endif
            </span>
        </div>
        <div class="symbol symbol-50 symbol-light-{{ $color }}">
            <span class="symbol-label">
                <i class="{{ $icon }} text-{{ $color }} icon-lg"></i>
            </span>
        </div>
    </div>
</div>
