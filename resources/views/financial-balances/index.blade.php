<x-app-layout>
    <x-slot name="header">

        <div class="flex justify-between">

            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Financial Balances') }}
            </h2>
            <a type="button" href="{{ route('financial-balances.create') }}"
                class="block rounded-md bg-indigo-600 px-3 py-2 text-right text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                <i class="fa fa-plus mr-2"></i> {{ __('Add new') }}
            </a>
        </div>
    </x-slot>

    @include('financial-balances.modal-summary')

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="w-full">
                    @include('layouts.alert')

                    <div class="space-y-4">
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
