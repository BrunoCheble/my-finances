<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                <span class="w-4 h-4 rounded-full inline-block" style="background-color: {{ $walletSection?->color ?? '#555' }}"></span>
                {{ $walletSection?->name ?? __('All Wallets') }}
            </h1>
            @if ($totalBalance !== null)
            <div class="flex flex-col items-end">
                <div class="text-xs text-gray-600">{{ __('Total Balance') }}</div>
                <div class="text-md font-semibold" >
                    {{ number_format($totalBalance, 2, ',', '.') }}
                </div>
            </div>
            @endif
        </div>
    </x-slot>

    @include('financial-movements.modal-create')

    @include('financial-movements.filter')

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="w-full">

                    @include('layouts.alert')

                    <div class="flex flex-justify-start gap-4" >
                        @if (count($wallets) > 1)
                        <div style="width: 200px">
                            <ul>
                                <li>
                                    <a class="px-3 py-3 mb-2 text-left text-xs font-semibold uppercase tracking-wide bg-gray-200 rounded block" href="{{ route('financial-movements.index') }}" class="">All</a>
                                </li>
                                @foreach ($walletsSidebar as $wallet)
                                    <li>
                                        <a class="px-3 py-3 mb-2 text-left text-xs font-semibold uppercase tracking-wide rounded block text-white" style="background-color: {{ $wallet->color }}" href="{{ route('financial-movements.index', ['wallet_id' => $wallet->id]) }}">{{ $wallet->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                        <div class="flow-root flex-1">
                            <div class="overflow-x-auto">
                                <div class="inline-block min-w-full align-middle">
                                    @include('financial-movements.table.index')
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
