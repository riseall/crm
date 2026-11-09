@props([
    'title',
    'value',
    'subtitle' => '',
    'subtitleColor' => '',
    'icon' => 'fas fa-chart-bar',
    'color' => 'primary',
    'valueColor' => null,
    'valueId' => null,
    'subtitleId' => null,
])

@php
    $valColorClass = $valueColor
        ? (\Illuminate\Support\Str::startsWith($valueColor, 'text-')
            ? $valueColor
            : 'text-' . $valueColor)
        : '';
@endphp

<div {{ $attributes->merge(['class' => 'card stat-card stat-card-responsive']) }}>
    {{-- Header: Judul (Kiri) & Icon Badge (Kanan) --}}
    <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="stat-card-title mb-0 text-truncate mr-2" title="{{ $title }}">{{ $title }}</span>
        <span class="symbol symbol-30 symbol-xl-35 symbol-light-{{ $color }} flex-shrink-0">
            <span class="symbol-label">
                <i class="{{ $icon }} text-{{ $color }} icon-md"></i>
            </span>
        </span>
    </div>

    {{-- Value: Nilai Metrik Utama --}}
    <div class="stat-card-value {{ $valColorClass }} text-truncate">
        <span @if ($valueId) id="{{ $valueId }}" @endif>{{ $value }}</span>
    </div>

    {{-- Subtitle: Keterangan / Deskripsi --}}
    <div class="stat-card-subtitle {{ $subtitleColor }} text-truncate"
        @if ($subtitleId) id="{{ $subtitleId }}" @endif>
        @if ($subtitle)
            {!! $subtitle !!}
        @elseif ($subtitleId)
            {{-- Placeholder untuk dinamis JS --}}
        @else
            &nbsp;
        @endif
    </div>
</div>

@once
    @push('styles')
        <style>
            .stat-card {
                border: none !important;
                border-radius: 12px !important;
                background: #ffffff !important;
                box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05) !important;
                padding: 0.85rem 1.15rem !important;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                height: 100%;
            }

            .stat-card:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 22px rgba(0, 0, 0, 0.08) !important;
            }

            .stat-card .stat-card-title {
                font-size: 0.8rem !important;
                font-weight: 700 !important;
                color: #5e6278 !important;
                line-height: 1.2 !important;
            }

            .stat-card .stat-card-value {
                font-size: 1.45rem !important;
                font-weight: 800 !important;
                color: #181c32;
                line-height: 1.15 !important;
                margin-top: 2px !important;
            }

            .stat-card .stat-card-subtitle {
                font-size: 0.75rem !important;
                font-weight: 600 !important;
                color: #7e8299;
                margin-top: 3px !important;
                min-height: 1.1rem;
                line-height: 1.2 !important;
            }

            .stat-card .symbol.symbol-30 .symbol-label {
                width: 30px !important;
                height: 30px !important;
            }

            @media (min-width: 1200px) and (max-width: 1536px) {
                .stat-card {
                    padding: 0.7rem 0.95rem !important;
                }

                .stat-card .stat-card-title {
                    font-size: 0.75rem !important;
                }

                .stat-card .stat-card-value {
                    font-size: 1.25rem !important;
                }

                .stat-card .stat-card-subtitle {
                    font-size: 0.7rem !important;
                    min-height: 1rem;
                }
            }
        </style>
    @endpush
@endonce
