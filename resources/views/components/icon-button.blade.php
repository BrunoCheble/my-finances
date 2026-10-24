{{-- Icon-only action: the label is announced to screen readers and shown as a tooltip. --}}
@props(['icon', 'label', 'href' => null, 'tone' => null])

@php
    $toneClass = match ($tone) {
        'danger' => 'hover:bg-rose-50 hover:text-rose-600',
        default => '',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" {{ $attributes->merge(['class' => "btn-icon $toneClass"]) }}>
        <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button title="{{ $label }}" {{ $attributes->merge(['type' => 'button', 'class' => "btn-icon $toneClass"]) }}>
        <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
