{{-- Dashboard alerts, grouped by level. Alert texts are plain text (escaped here). --}}
@php
    $levels = [
        'critical' => ['label' => __('Attention'), 'icon' => 'bg-rose-50 text-rose-600', 'bar' => 'bg-rose-500'],
        'warning' => ['label' => __('Warnings'), 'icon' => 'bg-amber-50 text-amber-600', 'bar' => 'bg-amber-400'],
        'info' => ['label' => __('For your information'), 'icon' => 'bg-brand-50 text-brand-600', 'bar' => 'bg-brand-400'],
    ];
    $grouped = collect($alerts)->groupBy('level');
    $visible = 5;
    $index = 0;
@endphp

<section class="card flex flex-col p-4 sm:p-6" aria-labelledby="alerts-title" x-data="{ all: false }">
    <div class="flex items-center justify-between gap-2">
        <h2 id="alerts-title" class="text-base font-semibold text-slate-900">{{ __('Alerts') }}</h2>
        <div class="flex gap-1.5">
            @foreach ($levels as $level => $style)
                @if ($grouped->has($level))
                    <span class="badge {{ $style['icon'] }}" title="{{ $style['label'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $style['bar'] }}" aria-hidden="true"></span>
                        {{ $grouped[$level]->count() }}
                        <span class="sr-only">{{ $style['label'] }}</span>
                    </span>
                @endif
            @endforeach
        </div>
    </div>

    @if (empty($alerts))
        <x-empty-state icon="circle-check" :title="__('All quiet')" :description="__('Nothing needs your attention this month.')" class="flex-1 !py-8" />
    @else
        <div class="mt-3 flex-1 space-y-4">
            @foreach ($levels as $level => $style)
                @continue(!$grouped->has($level))
                <div @if ($index >= $visible) x-show="all" x-cloak @endif>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $style['label'] }}</h3>
                    <ul class="space-y-2">
                        @foreach ($grouped[$level] as $alert)
                            <li class="relative flex gap-3 overflow-hidden rounded-lg border border-slate-100 py-2.5 pl-3 pr-2" @if ($index++ >= $visible) x-show="all" x-cloak @endif>
                                <span class="absolute inset-y-0 left-0 w-1 {{ $style['bar'] }}" aria-hidden="true"></span>
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $style['icon'] }}" aria-hidden="true">
                                    <i class="fa-solid fa-{{ $alert['icon'] }} text-sm"></i>
                                </span>
                                <div class="min-w-0 flex-1 text-sm">
                                    <p class="font-semibold text-slate-900">{{ $alert['title'] }}</p>
                                    <p class="text-slate-600">{{ $alert['message'] }}</p>
                                    @if ($alert['action'])
                                        <a href="{{ $alert['action']['url'] }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                                            {{ $alert['action']['label'] }} <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        @if (count($alerts) > $visible)
            <button type="button" class="mt-3 text-left text-sm font-medium text-brand-700 hover:text-brand-800" @click="all = !all"
                x-text="all ? @js(__('Show less')) : @js(__('Show all :count', ['count' => count($alerts)]))"></button>
        @endif
    @endif
</section>
