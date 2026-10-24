@php
    $groupInitial = $group->balances->sum('initial_balance');
    $groupFinal = $group->balances->sum('calculated_balance');
    $groupChange = $groupFinal - $groupInitial;
    $monthTitle = ucfirst(\Carbon\Carbon::parse($group->startDate)->translatedFormat('F Y'));
@endphp

<div x-data="{ open: {{ $key == 0 ? 'true' : 'false' }} }" class="card overflow-hidden">
    <button type="button" @click="open = !open" :aria-expanded="open"
        class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left hover:bg-slate-50 sm:px-6">
        <span>
            <span class="block font-semibold text-slate-900">{{ $monthTitle }}</span>
            <span class="block text-xs text-slate-500">{{ $group->title }}</span>
        </span>
        <span class="flex items-center gap-4">
            <span class="hidden text-right sm:block">
                <span class="amount block text-sm font-semibold text-slate-900">{{ format_currency($groupFinal) }}</span>
                <x-money :value="$groupChange" class="block text-xs" />
            </span>
            <i class="fa-solid fa-chevron-down text-sm text-slate-400 transition" :class="open && 'rotate-180'" aria-hidden="true"></i>
        </span>
    </button>

    <div x-show="open" x-transition.opacity class="border-t border-slate-200">
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="th">{{ __('Wallet') }}</th>
                        <th scope="col" class="th text-right">{{ __('Initial balance') }}</th>
                        <th scope="col" class="th text-right">{{ __('Total expenses') }}</th>
                        <th scope="col" class="th text-right">{{ __('Total income') }}</th>
                        <th scope="col" class="th text-right">{{ __('Transfers and loans') }}</th>
                        <th scope="col" class="th text-right">{{ __('Final balance') }}</th>
                        <th scope="col" class="th text-right">{{ __('Change') }}</th>
                        <th scope="col" class="th"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($group->balances as $balance)
                        @php
                            $third = $balance->wallet->is_third_party;
                            $rowChange = $balance->calculated_balance - $balance->initial_balance;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="td whitespace-nowrap">
                                <span class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $balance->wallet->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                                    <span class="font-medium text-slate-800">{{ $balance->wallet_name }}</span>
                                    @if ($third)
                                        <x-badge color="purple">{{ __('Third-party money') }}</x-badge>
                                    @endif
                                </span>
                            </td>
                            <td class="td whitespace-nowrap text-right text-slate-500">{{ $balance->initial_balance != 0 ? format_currency($balance->initial_balance) : '—' }}</td>
                            <td class="td whitespace-nowrap text-right">
                                @if ($balance->total_expense != 0)
                                    <button type="button" class="rounded px-1 hover:bg-rose-50" x-on:click="window.modalSummary.openModal('{{ $balance->start_date }}', '{{ $balance->end_date }}', 'expense', {{ $balance->wallet_id }})">
                                        <x-money :value="$balance->total_expense" :sign="false" />
                                    </button>
                                @else <span class="text-slate-300">—</span> @endif
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                @if ($balance->total_income != 0)
                                    <button type="button" class="rounded px-1 hover:bg-emerald-50" x-on:click="window.modalSummary.openModal('{{ $balance->start_date }}', '{{ $balance->end_date }}', 'income', {{ $balance->wallet_id }})">
                                        <x-money :value="$balance->total_income" />
                                    </button>
                                @else <span class="text-slate-300">—</span> @endif
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                @if ($balance->total_unidentified != 0)
                                    <button type="button" class="rounded px-1 hover:bg-slate-100" x-on:click="window.modalSummary.openModal('{{ $balance->start_date }}', '{{ $balance->end_date }}', 'transfer', {{ $balance->wallet_id }})">
                                        <x-money :value="$balance->total_unidentified" />
                                    </button>
                                @else <span class="text-slate-300">—</span> @endif
                            </td>
                            <td class="td whitespace-nowrap text-right font-semibold">
                                @if ($third)
                                    <span class="amount text-purple-700" title="{{ __('Balance kept in your banks for this person') }}">{{ __('kept') }} {{ format_currency(-$balance->calculated_balance) }}</span>
                                @else
                                    <span class="amount text-slate-900">{{ format_currency($balance->calculated_balance) }}</span>
                                @endif
                                @if ($balance->reserved_amount)
                                    <i class="fa-solid fa-circle-info ml-1 text-slate-400" title="{{ __('Reserved amount: :amount', ['amount' => format_currency($balance->reserved_amount)]) }}"
                                        aria-label="{{ __('Reserved amount: :amount', ['amount' => format_currency($balance->reserved_amount)]) }}"></i>
                                @endif
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                @if ($third)
                                    <span class="amount text-purple-700">{{ $rowChange != 0 ? ((-$rowChange > 0 ? '+' : '') . format_currency(-$rowChange)) : '—' }}</span>
                                @elseif ($rowChange != 0)
                                    <x-money :value="$rowChange" />
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="td whitespace-nowrap py-1.5">
                                @include('financial-balances.menu.index', ['modals' => false])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold">
                    <tr>
                        <th scope="row" class="td text-left">{{ __('Total') }}</th>
                        <td class="td whitespace-nowrap text-right"><span class="amount">{{ format_currency($groupInitial) }}</span></td>
                        <td class="td whitespace-nowrap text-right">
                            <button type="button" class="rounded px-1 hover:bg-rose-50" x-on:click="window.modalSummary.openModal('{{ $group->startDate }}', '{{ $group->endDate }}', 'expense')">
                                <x-money :value="$group->balances->sum('total_expense')" :sign="false" />
                            </button>
                        </td>
                        <td class="td whitespace-nowrap text-right">
                            <button type="button" class="rounded px-1 hover:bg-emerald-50" x-on:click="window.modalSummary.openModal('{{ $group->startDate }}', '{{ $group->endDate }}', 'income')">
                                <x-money :value="$group->balances->sum('total_income')" />
                            </button>
                        </td>
                        <td class="td whitespace-nowrap text-right">
                            <button type="button" class="rounded px-1 hover:bg-slate-100" x-on:click="window.modalSummary.openModal('{{ $group->startDate }}', '{{ $group->endDate }}', 'transfer')">
                                <x-money :value="$group->balances->sum('total_unidentified')" />
                            </button>
                        </td>
                        <td class="td whitespace-nowrap text-right"><span class="amount">{{ format_currency($groupFinal) }}</span></td>
                        <td class="td whitespace-nowrap text-right"><x-money :value="$groupChange" /></td>
                        <td class="td"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        {{-- edit/delete modals rendered once, outside the table (hidden on phones) and the cards --}}
        @foreach ($group->balances as $balance)
            @include('financial-balances.menu.modal-edit')
        @endforeach

        {{-- phones: one card per wallet --}}
        <ul class="divide-y divide-slate-100 md:hidden">
            @foreach ($group->balances as $balance)
                @php
                    $third = $balance->wallet->is_third_party;
                    $rowChange = $balance->calculated_balance - $balance->initial_balance;
                @endphp
                <li class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $balance->wallet->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800">{{ $balance->wallet_name }}</span>
                        @if ($third)
                            <span class="amount text-sm font-semibold text-purple-700">{{ __('kept') }} {{ format_currency(-$balance->calculated_balance) }}</span>
                        @else
                            <span class="amount text-sm font-semibold text-slate-900">{{ format_currency($balance->calculated_balance) }}</span>
                        @endif
                    </div>
                    <div class="mt-1 flex items-center justify-between gap-2 pl-4 text-xs text-slate-500">
                        <span>
                            {{ __('Initial balance') }} {{ format_currency($balance->initial_balance) }} ·
                            @if ($third)
                                <span class="text-purple-700">{{ $rowChange != 0 ? ((-$rowChange > 0 ? '+' : '') . format_currency(-$rowChange)) : '—' }}</span>
                            @else
                                <x-money :value="$rowChange" />
                            @endif
                        </span>
                        <span class="-mr-2">@include('financial-balances.menu.index', ['modals' => false])</span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="flex justify-end border-t border-slate-200 px-4 py-3 sm:px-6">
            <form action="{{ route('financial-balances.recalculateAll', ['start_date' => $group->startDate, 'end_date' => $group->endDate]) }}" method="POST">
                @method('PATCH')
                @csrf
                <x-button type="submit" variant="secondary" size="sm" icon="rotate">{{ __('Recalculate month') }}</x-button>
            </form>
        </div>
    </div>
</div>
