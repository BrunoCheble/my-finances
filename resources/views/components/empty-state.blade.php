@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-16 text-center']) }}>
    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <i class="fa-solid fa-{{ $icon }} text-lg" aria-hidden="true"></i>
    </div>
    <p class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
