{{-- Logo mark + wordmark. --}}
@props(['size' => 'md'])

@php
    [$mark, $text] = match ($size) {
        'lg' => ['h-12 w-12', 'text-2xl'],
        'sm' => ['h-8 w-8', 'text-base'],
        default => ['h-9 w-9', 'text-lg'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <x-application-logo class="{{ $mark }} shrink-0" />
    <span class="{{ $text }} font-bold tracking-tight text-slate-900">Escudo</span>
</span>
