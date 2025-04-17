<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Financial Categories') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="w-full">
                    <div class="sm:flex justify-between items-center">
                        <form action="{{ route('financial-categories.index') }}" class="flex gap-2 items-center"
                            method="GET">
                            @csrf
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                :value="$startDate" autocomplete="start_date" />
                            <i class="fa fa-arrow-right"></i>
                            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                                :value="$endDate" autocomplete="end_date" />
                            <x-primary-button>Filter</x-primary-button>
                        </form>
                        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                            <a type="button" href="{{ route('financial-categories.create') }}"
                                class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ __('Add new') }}</a>
                        </div>
                    </div>

                    <div class="flow-root">
                        <div class="mt-8 overflow-x-auto">
                            <div class="inline-block min-w-full py-2 align-middle">
                                <table class="w-full divide-y divide-gray-300">
                                    <thead>
                                        <tr>
                                            <th scope="col"
                                                class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                No</th>

                                            <th scope="col"
                                                class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                Name</th>
                                            <th scope="col"
                                                class="py-3 pl-4 pr-3 text-left text-xs text-right font-semibold uppercase tracking-wide text-gray-500">
                                                Total income</th>
                                            <th scope="col"
                                                class="py-3 pl-4 pr-3 text-left text-xs text-right font-semibold uppercase tracking-wide text-gray-500">
                                                Total expense</th>
                                            <th scope="col"
                                                class="py-3 pl-4 pr-3 text-left text-xs text-right font-semibold uppercase tracking-wide text-gray-500">
                                                Difference</th>
                                            <th scope="col"
                                                class="py-3 pl-4 pr-3 text-left text-xs text-right font-semibold uppercase tracking-wide text-gray-500">
                                                Expected Total</th>

                                            <th scope="col"
                                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach ($categories as $i => $category)
                                            <tr class="even:bg-gray-50">
                                                <td
                                                    class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900">
                                                    {{ ++$i }}</td>

                                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {{ $category->name }}</td>
                                                <td
                                                    class="whitespace-nowrap px-3 py-4 text-sm text-right text-green-500">
                                                    {{ $category->total_income ? format_currency($category->total_income) : '-' }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-4 text-sm text-right text-red-500">
                                                    {{ $category->total_expense ? format_currency($category->total_expense) : '-' }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-4 text-sm text-right">
                                                    {!! $category->total_income - $category->total_expense != 0
                                                        ? colored_format_currency($category->total_income - $category->total_expense)
                                                        : '-' !!}</td>
                                                <td
                                                    class="whitespace-nowrap px-3 py-4 text-sm text-right text-gray-500">
                                                    {{ $category->expected_total != 0 ? format_currency($category->expected_total) : '-' }}
                                                </td>

                                                <td
                                                    class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                                                    <form
                                                        action="{{ route('financial-categories.destroy', $category->id) }}"
                                                        method="POST">
                                                        <a href="{{ route('financial-categories.edit', $category->id) }}"
                                                            class="text-indigo-600 font-bold hover:text-indigo-900  mr-2">{{ __('Edit') }}</a>
                                                        @csrf
                                                        @method('DELETE')
                                                        <a href="{{ route('financial-categories.destroy', $category->id) }}"
                                                            class="text-red-600 font-bold hover:text-red-900"
                                                            onclick="event.preventDefault(); confirm('Are you sure to delete?') ? this.closest('form').submit() : false;">{{ __('Delete') }}</a>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
