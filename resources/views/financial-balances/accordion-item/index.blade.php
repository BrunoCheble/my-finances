@php
    $groupInitial = $group->balances->sum('initial_balance');
    $groupFinal = $group->balances->sum('calculated_balance');
    $groupChange = $groupFinal - $groupInitial;
    $monthTitle = ucfirst(\Carbon\Carbon::parse($group->startDate)->translatedFormat('F Y'));
    $monthKey = substr($group->startDate, 0, 7);
    $divergedCount = $group->balances->where('is_diverged', true)->count();
    $confirmedCount = $group->balances->where('is_reconciled', true)->count();
    $allConfirmed = $confirmedCount === $group->balances->count();
@endphp

<div id="month-{{ $monthKey }}" x-data="{ open: {{ $key == 0 || $divergedCount ? 'true' : 'false' }} }"
    x-init="if (location.hash === '#month-{{ $monthKey }}') { open = true; $nextTick(() => $el.scrollIntoView({ block: 'start' })) }"
    @class(['card scroll-mt-20 overflow-hidden', 'ring-1 ring-rose-300' => $divergedCount])>
    <button type="button" @click="open = !open" :aria-expanded="open"
        class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left hover:bg-slate-50 sm:px-6">
        <span>
            <span class="flex flex-wrap items-center gap-2 font-semibold text-slate-900">
                {{ $monthTitle }}
                @if ($divergedCount)
                    <x-badge color="red"><i class="fa-solid fa-lock-open text-[10px]" aria-hidden="true"></i>{{ trans_choice(':count diverged|:count diverged', $divergedCount, ['count' => $divergedCount]) }}</x-badge>
                @elseif ($allConfirmed)
                    <x-badge color="green"><i class="fa-solid fa-circle-check text-[10px]" aria-hidden="true"></i>{{ __('Reconciled') }}</x-badge>
                @elseif ($confirmedCount)
                    <x-badge>{{ __(':done of :total confirmed', ['done' => $confirmedCount, 'total' => $group->balances->count()]) }}</x-badge>
                @endif
            </span>
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
                                @if ($balance->is_reconciled)
                                    <span class="ml-1 inline-block">@include('financial-balances.reconciliation.status')</span>
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
            @include('financial-balances.reconciliation.modal')
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
                    @if ($balance->is_reconciled)
                        <div class="mt-1 pl-4">@include('financial-balances.reconciliation.status')</div>
                    @endif
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

        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-4 py-3 sm:px-6">
            @unless ($allConfirmed)
                <x-button size="sm" icon="circle-check" x-on:click="$dispatch('open-modal', 'reconcile-month-{{ $monthKey }}')">{{ __('Confirm month') }}</x-button>
            @endunless
            <form action="{{ route('financial-balances.recalculateAll', ['start_date' => $group->startDate, 'end_date' => $group->endDate]) }}" method="POST">
                @method('PATCH')
                @csrf
                <x-button type="submit" variant="secondary" size="sm" icon="rotate">{{ __('Recalculate month') }}</x-button>
            </form>
        </div>
    </div>
</div>

@unless ($allConfirmed)
    <x-modal name="reconcile-month-{{ $monthKey }}" maxWidth="md">
        <form method="POST" action="{{ route('financial-balances.reconcileMonth', $group->startDate) }}" class="space-y-4 p-5 sm:p-6">
            @csrf
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Confirm month') }}</h3>
                    <p class="text-sm text-slate-500">{{ $monthTitle }}</p>
                </div>
                <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click="$dispatch('close')" />
            </div>
            <p class="text-sm text-slate-600">{{ __('Do these balances match your banks at the end of the month?') }}</p>
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 text-sm">
                @foreach ($group->balances as $balance)
                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $balance->wallet->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                            <span class="truncate">{{ $balance->wallet_name }}</span>
                            @if ($balance->is_reconciled && !$balance->is_diverged)<i class="fa-solid fa-circle-check text-emerald-600" title="{{ __('Reconciled') }}"></i>@endif
                        </span>
                        <span class="amount font-semibold text-slate-900">{{ format_currency($balance->getRawOriginal('calculated_balance')) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-slate-500">{{ __('If one does not match, close this and adjust that wallet first.') }}</p>
            <div class="flex justify-end gap-2">
                <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                <x-button type="submit" icon="circle-check">{{ __('All match: confirm') }}</x-button>
            </div>
        </form>
    </x-modal>
@endunless
