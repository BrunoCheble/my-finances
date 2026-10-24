@props(['color' => 'slate'])

@php
    $colors = [
        'slate' => 'bg-slate-100 text-slate-700',
        'brand' => 'bg-brand-50 text-brand-700',
        'green' => 'bg-emerald-50 text-emerald-700',
        'red' => 'bg-rose-50 text-rose-700',
        'yellow' => 'bg-amber-50 text-amber-800',
        'purple' => 'bg-purple-50 text-purple-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . ($colors[$color] ?? $colors['slate'])]) }}>{{ $slot }}</span>
