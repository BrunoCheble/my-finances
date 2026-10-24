@php
    $activeFilters = collect([
        'date' => request('date_from') && request('date_to')
            ? __('From :from to :to', ['from' => date_format_custom(request('date_from')), 'to' => date_format_custom(request('date_to'))])
            : null,
        'search' => request('attribute') && request('search') !== null && request('search') !== ''
            ? ([
                'description' => __('Description'),
                'type' => __('Type'),
                'category_id' => __('Category'),
                'wallet_id' => __('Wallet'),
            ][request('attribute')] ?? request('attribute')) . ': ' . (match (request('attribute')) {
                    'category_id' => $categories[request('search')] ?? request('search'),
                    'wallet_id' => $wallets[request('search')] ?? request('search'),
                    'type' => $types[request('search')] ?? request('search'),
                    default => request('search'),
                })
            : null,
    ])->filter();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$walletSection?->name ?? __('Financial Movements')">
            <x-slot name="meta">
                @if ($walletSection && $totalBalance !== null)
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Current balance') }}:
                        <x-money :value="$totalBalance" :sign="false" class="font-semibold" />
                    </p>
                @else
                    <p class="mt-1 text-sm text-slate-500">{{ __(':count movements', ['count' => $financials->total()]) }}</p>
                @endif
            </x-slot>
            <x-slot name="actions">
                <x-button variant="secondary" icon="sliders" x-data x-on:click="$dispatch('open-modal', 'modal-filter')">
                    {{ __('Filter') }}
                    @if ($activeFilters->isNotEmpty())
                        <span class="badge bg-brand-600 text-white">{{ $activeFilters->count() }}</span>
                    @endif
                </x-button>
                <x-button icon="plus" class="hidden md:inline-flex" x-data x-on:click="$dispatch('open-modal', 'modal-create')">
                    {{ __('New movement') }}
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('financial-movements.form-script')
    @include('financial-movements.modal-create')
    @include('financial-movements.filter')

    <div class="space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        {{-- Wallet switcher: horizontal scroll on small screens --}}
        @if (count($wallets) > 1)
            <nav class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('Wallets') }}">
                <ul class="flex gap-2 pb-1">
                    <li>
                        <a href="{{ route('financial-movements.index', request()->except(['wallet_id', 'page'])) }}"
                            @class(['flex items-center gap-2 whitespace-nowrap rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                                'border-slate-900 bg-slate-900 text-white' => !$walletSection,
                                'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' => $walletSection])
                            @if (!$walletSection) aria-current="page" @endif>
                            {{ __('All') }}
                        </a>
                    </li>
                    @foreach ($walletsSidebar as $wallet)
                        @php
                            $current = $walletSection?->id === $wallet->id;
                        @endphp
                        <li>
                            <a href="{{ route('financial-movements.index', array_merge(request()->except(['wallet_id', 'page']), ['wallet_id' => $wallet->id])) }}"
                                @class(['flex items-center gap-2 whitespace-nowrap rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                                    'border-slate-900 bg-slate-900 text-white' => $current,
                                    'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' => !$current])
                                @if ($current) aria-current="page" @endif>
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $wallet->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                                {{ $wallet->name }}
                                @unless ($wallet->active)<span class="text-xs font-normal opacity-70">({{ __('inactive wallet') }})</span>@endunless
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($activeFilters->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @foreach ($activeFilters as $filter)
                    <x-badge color="brand" class="py-1">{{ $filter }}</x-badge>
                @endforeach
                <a href="{{ route('financial-movements.index', array_filter(['wallet_id' => request('wallet_id')])) }}"
                    class="font-medium text-brand-700 hover:text-brand-800">{{ __('Clear filters') }}</a>
            </div>
        @endif

        @include('financial-movements.table.index')
    </div>
</x-app-layout>
