{{-- Round icon by kind of movement (also conveys direction without relying on color). --}}
@php
    [$icon, $classes] = match (true) {
        $financial->type === \App\Enums\FinancialMovementType::TRANSFER => ['arrow-right-arrow-left', 'bg-slate-100 text-slate-500'],
        $financial->type === \App\Enums\FinancialMovementType::LOAN => ['handshake', 'bg-amber-50 text-amber-600'],
        $financial->type === \App\Enums\FinancialMovementType::ADJUSTMENT => ['sliders', 'bg-slate-100 text-slate-500'],
        $financial->isDebit => ['arrow-down', 'bg-rose-50 text-rose-600'],
        default => ['arrow-up', 'bg-emerald-50 text-emerald-600'],
    };
@endphp
<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $classes }}" title="{{ $financial->type_name }}">
    <i class="fa-solid fa-{{ $icon }} text-sm" aria-hidden="true"></i>
    <span class="sr-only">{{ $financial->type_name }}</span>
</span>
