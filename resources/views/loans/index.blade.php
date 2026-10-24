@php
    $toReceive = $open->sum(fn ($loan) => $loan->outstanding);
    $interest = $open->concat($closed)->flatMap->movements->where('type', \App\Enums\FinancialMovementType::INCOME)->sum('amount');
    $lost = $closed->where('status', \App\Models\Loan::WRITTEN_OFF)->sum('written_off_amount');
    $people = $open->concat($closed)->pluck('person')->unique()->values();
    $walletOptions = $wallets->pluck('name', 'id');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Loans')" :subtitle="__('Money you lent: it is still yours until it comes back.')">
            <x-slot name="actions">
                <x-button icon="plus" x-data x-on:click="$dispatch('open-modal', 'loan-new')">{{ __('New loan') }}</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section aria-label="{{ __('Summary') }}" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
            <x-stat :label="__('To receive')" :value="$toReceive" icon="hand-holding-dollar" tone="brand" class="col-span-2 lg:col-span-1">
                <x-slot name="footer">{{ trans_choice(':count open loan|:count open loans', $open->count(), ['count' => $open->count()]) }} · {{ __('counted in your net worth') }}</x-slot>
            </x-stat>
            <x-stat :label="__('Interest received')" :value="$interest" icon="arrow-up" tone="green" :sign="true" :colored="true">
                <x-slot name="footer">{{ __('Paid back above what was lent') }}</x-slot>
            </x-stat>
            <x-stat :label="__('Losses')" :value="-$lost" icon="arrow-down" tone="red" :colored="true">
                <x-slot name="footer">{{ __('Written off as not coming back') }}</x-slot>
            </x-stat>
        </section>

        {{-- Open loans --}}
        <section aria-labelledby="open-title">
            <h2 id="open-title" class="mb-3 text-base font-semibold text-slate-900">{{ __('Open loans') }}</h2>
            @if ($open->isEmpty())
                <x-card>
                    <x-empty-state icon="hand-holding-dollar" :title="__('Nobody owes you money')"
                        :description="__('When you lend money, register it here: your net worth stays the same and you see what is still to come back.')">
                        <x-button class="mt-4" icon="plus" x-data x-on:click="$dispatch('open-modal', 'loan-new')">{{ __('New loan') }}</x-button>
                    </x-empty-state>
                </x-card>
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($open as $loan)
                        @php
                            $progress = $loan->lent > 0 ? min(100, round($loan->received / $loan->lent * 100)) : 0;
                            $since = $loan->loanMovements->first()?->date;
                        @endphp
                        <article class="card flex flex-col p-4 sm:p-5" x-data="{ history: false }">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-50 text-sm font-semibold text-amber-700" aria-hidden="true">
                                    {{ mb_strtoupper(mb_substr($loan->person, 0, 1)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-semibold text-slate-900">{{ $loan->person }}</h3>
                                    <p class="text-xs text-slate-500">{{ __('since :date', ['date' => date_format_custom($since)]) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-slate-500">{{ __('Still to receive') }}</p>
                                    <p class="amount text-lg font-semibold text-slate-900">{{ format_currency($loan->outstanding) }}</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}" aria-label="{{ __('Received') }}">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
                                </div>
                                <p class="mt-1.5 flex justify-between text-xs text-slate-500">
                                    <span>{{ __('Lent') }} {{ format_currency($loan->lent) }}</span>
                                    <span>{{ __('Received') }} {{ format_currency($loan->received) }} ({{ $progress }}%)</span>
                                </p>
                            </div>

                            <button type="button" class="mt-3 self-start text-xs font-medium text-brand-700 hover:text-brand-800" @click="history = !history" :aria-expanded="history">
                                <span x-text="history ? @js(__('Hide history')) : @js(__('Show history'))"></span>
                            </button>
                            <ul x-show="history" x-cloak class="mt-2 divide-y divide-slate-100 text-sm">
                                @foreach ($loan->movements as $movement)
                                    <li class="flex items-center justify-between gap-2 py-1.5">
                                        <span class="min-w-0 truncate text-slate-600">{{ date_format_custom($movement->date) }} · {{ $movement->wallet?->name }}</span>
                                        <x-money :value="$movement->amount" class="text-sm" />
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-auto flex gap-2 pt-4">
                                <x-button size="sm" icon="arrow-down" class="flex-1" x-data x-on:click="$dispatch('open-modal', 'loan-receive-{{ $loan->id }}')">{{ __('Receive') }}</x-button>
                                <x-button size="sm" variant="secondary" class="flex-1" x-data x-on:click="$dispatch('open-modal', 'loan-writeoff-{{ $loan->id }}')">{{ __('Write off') }}</x-button>
                            </div>
                        </article>

                        {{-- receive --}}
                        <x-modal name="loan-receive-{{ $loan->id }}" maxWidth="md">
                            <form method="POST" action="{{ route('loans.receive', $loan) }}" class="space-y-4 p-5 sm:p-6"
                                x-data="{ amount: '{{ number_format($loan->outstanding, 2, '.', '') }}', owed: {{ $loan->outstanding }} }">
                                @csrf
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Payment from :person', ['person' => $loan->person]) }}</h3>
                                    <p class="text-sm text-slate-500">{{ __('Still to receive') }}: {{ format_currency($loan->outstanding) }}</p>
                                </div>
                                @include('loans.fields', ['withPerson' => false, 'uid' => 'r' . $loan->id])
                                <p x-show="Number(amount) > owed" x-cloak class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    <span x-text="@js(__('The extra :amount will be recorded as loan interest (income).')).replace(':amount', (Number(amount) - owed).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }))"></span>
                                </p>
                                <div class="flex justify-end gap-2">
                                    <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                                    <x-primary-button>{{ __('Register payment') }}</x-primary-button>
                                </div>
                            </form>
                        </x-modal>

                        {{-- write off --}}
                        <x-modal name="loan-writeoff-{{ $loan->id }}" maxWidth="md">
                            <form method="POST" action="{{ route('loans.write-off', $loan) }}" class="space-y-4 p-5 sm:p-6">
                                @csrf
                                @method('PATCH')
                                <h3 class="text-lg font-semibold text-slate-900">{{ __('Write off the loan to :person?', ['person' => $loan->person]) }}</h3>
                                <p class="text-sm text-slate-600">{{ __(':amount that was not paid back counts as a loss and leaves your net worth. The loan closes.', ['amount' => format_currency($loan->outstanding)]) }}</p>
                                <div class="flex justify-end gap-2">
                                    <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                                    <x-danger-button>{{ __('Write off') }}</x-danger-button>
                                </div>
                            </form>
                        </x-modal>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Closed loans --}}
        @if ($closed->isNotEmpty())
            <section x-data="{ show: false }" aria-labelledby="closed-title">
                <button type="button" id="closed-title" class="flex items-center gap-2 text-base font-semibold text-slate-900" @click="show = !show" :aria-expanded="show">
                    {{ __('Closed') }} <x-badge>{{ $closed->count() }}</x-badge>
                    <i class="fa-solid fa-chevron-down text-xs text-slate-400 transition" :class="show && 'rotate-180'" aria-hidden="true"></i>
                </button>
                <ul x-show="show" x-cloak class="card mt-3 divide-y divide-slate-100">
                    @foreach ($closed as $loan)
                        @php
                            $result = $loan->movements->where('type', \App\Enums\FinancialMovementType::INCOME)->sum('amount') - (float) $loan->written_off_amount;
                        @endphp
                        <li class="flex items-center gap-3 px-4 py-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-slate-800">{{ $loan->person }}</p>
                                <p class="text-xs text-slate-500">{{ __('Lent') }} {{ format_currency($loan->lent) }} · {{ __('closed on :date', ['date' => date_format_custom($loan->closed_on)]) }}</p>
                            </div>
                            @if ($loan->status === \App\Models\Loan::WRITTEN_OFF)
                                <x-badge color="red">{{ __('Written off') }}</x-badge>
                            @else
                                <x-badge color="green">{{ __('Settled') }}</x-badge>
                            @endif
                            <x-money :value="$result" class="w-24 text-right" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    {{-- new loan --}}
    <x-modal name="loan-new" maxWidth="md">
        <form method="POST" action="{{ route('loans.store') }}" class="space-y-4 p-5 sm:p-6" x-data="{ amount: '' }">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-slate-900">{{ __('New loan') }}</h3>
                <p class="text-sm text-slate-500">{{ __('The money leaves the wallet but stays in your net worth as "to receive".') }}</p>
            </div>
            @include('loans.fields', ['withPerson' => true, 'uid' => 'new'])
            <div class="flex justify-end gap-2">
                <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                <x-primary-button>{{ __('Register loan') }}</x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
