{{-- KPI tile: label, headline amount and an optional comparison line. --}}
@props(['label', 'value', 'icon' => null, 'tone' => 'slate', 'colored' => false, 'sign' => false])

@php
    $iconTone = [
        'slate' => 'bg-slate-100 text-slate-500',
        'brand' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'red' => 'bg-rose-50 text-rose-600',
    ][$tone] ?? 'bg-slate-100 text-slate-500';
@endphp

<div {{ $attributes->merge(['class' => 'card p-4 sm:p-5']) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $iconTone }}" aria-hidden="true">
                <i class="fa-solid fa-{{ $icon }} text-sm"></i>
            </span>
        @endif
    </div>
    <p class="mt-2 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
        <x-money :value="$value" :colored="$colored" :sign="$sign" />
    </p>
    @isset($footer)
        <div class="mt-1 text-xs text-slate-500">{{ $footer }}</div>
    @endisset
</div>
