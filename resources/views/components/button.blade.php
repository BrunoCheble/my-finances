@props(['variant' => 'primary', 'size' => null, 'icon' => null, 'href' => null])

@php
    $classes = trim("btn btn-{$variant}" . ($size ? " btn-{$size}" : ''));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        @if ($icon)<i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </button>
@endif
