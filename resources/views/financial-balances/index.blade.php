<x-app-layout>
    <x-slot name="header">

        <div class="flex justify-between items-center">

            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Financial Balances') }}
            </h2>

            <div class="flex gap-4 items-center">
                <x-dropdown-select :options="$filter" name="year" selected="{{ request('year', date('Y')) }}"
                    onchange="window.location.href='{{ route('financial-balances.index') }}?year=' + this.value" />

                <a type="button" href="{{ route('financial-balances.create') }}"
                    class="block rounded-md bg-indigo-600 px-3 py-2 text-right text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <i class="fa fa-plus mr-2"></i> {{ __('Add new') }}
                </a>
            </div>
        </div>
    </x-slot>

    @include('financial-balances.modal-summary')

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="w-full">
                    @include('layouts.alert')

                    <div class="space-y-4">
                        <!-- create a total summary, using the variable total_unidentified, total_initial, total_calculated -->
                        <div class="flex justify-end gap-8">
                            <div class="text-right">
                                <div class="text-sm text-gray-500">{{ __('Total Evolution') }}</div>
                                <div class="text-lg font-bold">
                                    <span class="mr-2">{!! colored_format_currency($summary['total_calculated']-$summary['total_initial']) !!}</span>
                                    {!! diff_percentage($summary['total_initial'], $summary['total_calculated']) !!}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">{{ __('Total Unidentified') }}</div>
                                <div class="text-lg font-bold">
                                    {!! colored_format_currency($summary['total_unidentified']) !!}
                                </div>
                            </div>
                        </div>
                        <!-- Accordion Item -->
                        @foreach ($groups as $key => $group)
                            @include('financial-balances.accordion-item.index', ['group' => $group, 'key' => $key])
                        @endforeach
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
