<div x-data="{ open: false }" class="bg-white border border-gray-300 rounded-xl shadow-sm overflow-hidden transition-all">
    <button @click="open = !open" class="w-full flex justify-between items-center p-5 text-gray-800 font-medium text-lg hover:bg-gray-50 transition">
        <span>{{ $group->title }}</span>
        <svg :class="open ? 'rotate-180 text-gray-900' : 'rotate-0 text-gray-500'" class="w-6 h-6 transition-transform transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div class="p-5 text-gray-700 leading-relaxed">
        <table class="w-full divide-y divide-gray-300">
            <thead>
                <tr>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ __('Wallet') }}</th>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        {{ __('Initial Balance') }}</th>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        {{ __('Total Expense') }}</th>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        {{ __('Total Income') }}</th>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        {{ __('Total Unidentified') }}</th>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        {{ __('Calculated Balance') }}</th>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        {{ __('Real Balance') }}</th>
                    <th scope="col"
                        class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        {{ __('Evolution') }}</th>
                    <th scope="col"
                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    </th>
                </tr>
            </thead>
            <tbody x-show="open" x-collapse  class="divide-y divide-gray-200 bg-white">
                @foreach ($group->balances as $balance)
                    <tr class="even:bg-gray-50">

                        <td class="whitespace-nowrap px-3 py-4 text-sm text-white font-bold" style="background-color: {{ $balance->wallet->color }}">
                            {{ $balance->wallet_name }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-right">
                            {!! $balance->initial_balance != 0 ? colored_format_currency($balance->initial_balance) : '' !!}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-red-500 text-right">
                            {{{ $balance->total_expense != 0 ? format_currency($balance->total_expense) : '' }}}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-green-500 text-right">
                            {{ $balance->total_income != 0 ? format_currency($balance->total_income) : '' }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-right">
                            {!! $balance->total_unidentified != 0 ? colored_format_currency($balance->total_unidentified) : '' !!}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold {{ $balance->calculated_balance != $balance->real_balance ? 'text-blue-500' : ($balance->calculated_balance < 0 ? 'text-red-500' : 'text-green-500') }}">
                            {{ $balance->calculated_balance != 0 ? format_currency($balance->calculated_balance) : '' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold">
                            {!! $balance->real_balance != 0 ? colored_format_currency($balance->real_balance) : '' !!}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold">
                            {!! $balance->calculated_balance != 0 ? colored_format_currency($balance->calculated_balance-$balance->initial_balance) : '' !!}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                            @include('financial-balances.menu.index')
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-center font-bold text-gray-500">
                        {{ __('All') }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold text-gray-500">
                        {{ format_currency($group->balances->sum('initial_balance')) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold text-gray-500">
                        {{ format_currency($group->balances->sum('total_expense')) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold text-gray-500">
                        {{ format_currency($group->balances->sum('total_income')) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold text-gray-500">
                        {{ format_currency($group->balances->sum('total_unidentified')) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold {{ $group->balances->sum('calculated_balance') != $group->balances->sum('real_balance') ? 'text-blue-500' : ($group->balances->sum('calculated_balance') < 0 ? 'text-red-500' : 'text-green-500') }}">
                        <span class="mr-2">{{ format_currency($group->balances->sum('calculated_balance')) }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold text-gray-500">
                        <span class="mr-2">{{ format_currency($group->balances->sum('real_balance')) }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-bold text-gray-500">
                        <span class="mr-2">{!! colored_format_currency($group->balances->sum('calculated_balance')-$group->balances->sum('initial_balance')) !!}</span>
                        {!! diff_percentage($group->balances->sum('calculated_balance'), $group->balances->sum('initial_balance')) !!}
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                        <form action="{{ route('financial-balances.recalculateAll', ['start_date' => $group->startDate, 'end_date' => $group->endDate]) }}" class="flex justify-end" method="POST">
                            {{ method_field('PATCH') }}
                            @csrf
                            <x-primary-button class="bg-green-500 hover:bg-green-600">
                                <i class="fa fa-refresh mr-2"></i> {{ __('Recalculate All') }}
                            </x-primary-button>
                        </form>
                    </td>
                </tr>
            </tfoot>
        </table>

    </div>
</div>
