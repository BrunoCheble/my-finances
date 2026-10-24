{{-- Page title with optional subtitle and action buttons (stack on small screens). --}}
@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <h1 class="truncate text-xl font-semibold text-slate-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
