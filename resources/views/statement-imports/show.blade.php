@php
    $reconciles = $statementImport->opening_balance !== null && $statementImport->closing_balance !== null
        ? round($statementImport->opening_balance + $statementImport->lines->sum('amount'), 2) == round($statementImport->closing_balance, 2)
        : null;
    $mismatches = $statementImport->lines->where('balance_mismatch', true)->count();
    $linesData = $statementImport->lines->map(fn ($line) => [
        'id' => $line->id,
        'amount' => (float) $line->amount,
        'selected' => $line->selected,
        'type' => $line->type,
        'category_id' => $line->category_id ? (string) $line->category_id : '',
        'transfer_wallet_id' => $line->transfer_wallet_id ? (string) $line->transfer_wallet_id : '',
        'loan_person' => $line->loan_person ?? '',
        'is_new' => $line->is_new,
        'duplicate' => $line->duplicate_movement_id !== null,
        'keywords' => \App\Services\Statements\MovementHistory::keywords($line->description),
        'normalized' => $line->normalized_description,
        'transfer_text' => \App\Services\Statements\MovementHistory::isTransferText($line->description),
        'position' => $line->position + 1,
        'touched' => false,
        'suggested_from' => null,
    ])->values();
    $selectClass = 'input block w-full py-1.5 text-sm disabled:opacity-70';
    $pending = $statementImport->is_pending;
    $bankName = app(\App\Statements\StatementParserRegistry::class)->name($statementImport->bank);
    $suggestedReserved = $statementImport->suggestedReservedAmount();
    $currentReserved = (float) $statementImport->wallet->reserved_amount;
    // lines by day (keys kept: they index the Alpine lines array)
    $days = $statementImport->lines->groupBy('date', true);
    // the merchant's initial in a soft color, always the same for the same merchant
    $avatarColors = ['bg-brand-50 text-brand-700', 'bg-emerald-50 text-emerald-700', 'bg-amber-50 text-amber-700', 'bg-rose-50 text-rose-700', 'bg-purple-50 text-purple-700', 'bg-orange-50 text-orange-700', 'bg-slate-100 text-slate-700'];
    $avatar = function ($line) use ($avatarColors) {
        $words = \App\Services\Statements\MovementHistory::keywords($line->description);
        $key = $words[0] ?? $line->description;
        return [mb_strtoupper(mb_substr($key, 0, 1)), $avatarColors[crc32($key) % count($avatarColors)]];
    };
    $typeIcons = ['expense' => 'arrow-down', 'income' => 'arrow-up', 'refund' => 'rotate-left', 'discount' => 'percent', 'transfer' => 'arrow-right-arrow-left', 'loan' => 'handshake', 'adjustment' => 'sliders'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$statementImport->wallet->name">
            <x-slot name="meta">
                <span class="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span><i class="fa-solid fa-building-columns mr-1 text-slate-400" aria-hidden="true"></i>{{ $bankName }}@if ($statementImport->account_last4) · ••••{{ $statementImport->account_last4 }}@endif</span>
                    @if ($statementImport->period_start)
                        <span aria-hidden="true">·</span>
                        <span>{{ date_format_custom($statementImport->period_start) }} – {{ date_format_custom($statementImport->period_end) }}</span>
                    @endif
                </span>
            </x-slot>
            <x-slot name="actions">
                <x-button :href="route('statement-imports.index')" variant="secondary" icon="arrow-left">{{ __('Back') }}</x-button>
                @if ($pending)
                    <x-icon-button icon="trash" tone="danger" :label="__('Discard import')" x-data x-on:click="$dispatch('open-modal', 'discard-import')" />
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8" x-data="statementImport()">
        @include('statement-imports.steps', ['current' => $pending ? 2 : 3])

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- the balance as an equation: today + selected = projected, against the bank --}}
        <section class="card overflow-hidden" aria-label="{{ __('Balance check') }}">
            <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:flex sm:flex-wrap sm:items-end sm:gap-x-5">
                    <div>
                        <dt class="text-xs font-medium text-slate-500">{{ $pending ? __('Current balance') : __('Balance before import') }}</dt>
                        <dd class="amount mt-0.5 text-xl font-semibold text-slate-900 sm:text-2xl" x-text="money(systemBalance)"></dd>
                    </div>
                    <span class="hidden pb-1 text-xl text-slate-300 sm:block" aria-hidden="true">+</span>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">{{ __('Selected') }} · <span x-text="selectedCount"></span></dt>
                        <dd class="amount mt-0.5 text-xl font-semibold sm:text-2xl" :class="selectedTotal < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="(selectedTotal > 0 ? '+' : '') + money(selectedTotal)"></dd>
                    </div>
                    <span class="hidden pb-1 text-xl text-slate-300 sm:block" aria-hidden="true">=</span>
                    <div class="col-span-2 sm:col-span-1">
                        <dt class="text-xs font-medium text-brand-700">{{ __('Projected balance') }}</dt>
                        <dd class="amount mt-0.5 text-2xl font-bold text-brand-700 sm:text-3xl" x-text="money(projected)"></dd>
                    </div>
                </dl>
                <div class="flex items-center gap-3 rounded-xl px-4 py-3"
                    :class="bankClosing === null ? 'bg-slate-50' : (matchesBank ? 'bg-emerald-50' : 'bg-amber-50')">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                        :class="bankClosing === null ? 'bg-slate-200 text-slate-500' : (matchesBank ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-white')" aria-hidden="true">
                        <i class="fa-solid text-sm" :class="bankClosing === null ? 'fa-building-columns' : (matchesBank ? 'fa-check' : 'fa-not-equal')"></i>
                    </span>
                    <div>
                        <p class="text-xs font-medium text-slate-500">{{ __('Bank closing balance') }}</p>
                        <p class="amount font-semibold text-slate-900" x-text="bankClosing === null ? '—' : money(bankClosing)"></p>
                        <p x-show="bankClosing !== null" class="text-xs font-medium" :class="matchesBank ? 'text-emerald-700' : 'text-amber-800'"
                            x-text="matchesBank ? @js(__('Matches the projection')) : @js(__('Difference')) + ' ' + money(projected - bankClosing)"></p>
                    </div>
                </div>
            </div>

            {{-- details: the PDF read well, other wallets of the transfers, the held amount --}}
            <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 text-xs text-slate-600 sm:px-6">
                <p class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    @if ($reconciles === true && $mismatches === 0)
                        <span class="text-emerald-700"><i class="fa-solid fa-shield-halved mr-1" aria-hidden="true"></i>{{ __('Statement reconciled: no line lost or misread') }}</span>
                    @elseif ($reconciles === null)
                        <span><i class="fa-solid fa-circle-info mr-1 text-slate-400" aria-hidden="true"></i>{{ __('Statement has no balances to reconcile') }}</span>
                    @else
                        <span class="font-medium text-rose-700"><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ __('Statement does not reconcile') }}@if ($mismatches) — {{ $mismatches }} {{ __('line(s) with balance mismatch') }}@endif</span>
                    @endif
                    <span class="truncate"><i class="fa-regular fa-file-pdf mr-1 text-slate-400" aria-hidden="true"></i>{{ $statementImport->file_name }}</span>
                    <template x-for="item in otherWallets" :key="item.id">
                        <span><i class="fa-solid fa-arrow-right-arrow-left mr-1 text-slate-400" aria-hidden="true"></i><span x-text="item.name"></span>
                            <span class="amount font-semibold" :class="item.total < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="(item.total > 0 ? '+' : '') + money(item.total)"></span></span>
                    </template>
                </p>
                @if ($suggestedReserved !== null && ($suggestedReserved != 0 || $currentReserved != 0))
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <span><i class="fa-solid fa-lock mr-1 text-amber-500" aria-hidden="true"></i>
                            @if ($suggestedReserved != 0)
                                {{ __('Held by the bank on :date: :amount', ['date' => date_format_custom($statementImport->period_end), 'amount' => format_currency(-$suggestedReserved)]) }}
                            @else
                                {{ __('On :date the bank had no held amount.', ['date' => date_format_custom($statementImport->period_end)]) }}
                            @endif
                        </span>
                        @if (round($currentReserved, 2) == round($suggestedReserved, 2))
                            <span class="text-emerald-700"><i class="fa-solid fa-check mr-1" aria-hidden="true"></i>{{ __('Reserved amount is up to date') }}</span>
                        @elseif (!$statementImport->isLatestForWallet())
                            <span class="text-slate-500">{{ __('There is a more recent statement for this wallet') }}</span>
                        @else
                            <form method="POST" action="{{ route('statement-imports.reserved-amount', $statementImport) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="font-semibold text-brand-700 underline-offset-2 hover:underline">
                                    {{ $suggestedReserved == 0 ? __('Clear reserved amount') : __('Update reserved amount to :amount', ['amount' => format_currency($suggestedReserved)]) }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        {{-- the movements, by day --}}
        <form id="import-form" method="POST" action="{{ route('statement-imports.update', $statementImport) }}">
            @csrf
            @method('PATCH')

            <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="inline-flex self-start rounded-lg bg-slate-100 p-1 text-sm font-medium" role="tablist" aria-label="{{ __('Filter') }}">
                    @foreach (['all' => __('All'), 'new' => __('New only'), 'duplicate' => __('Duplicates')] as $key => $label)
                        <button type="button" role="tab" :aria-selected="(filter === '{{ $key }}').toString()" @click="filter = '{{ $key }}'"
                            :class="filter === '{{ $key }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="rounded-md px-3 py-1.5">
                            {{ $label }} <span class="ml-0.5 text-xs text-slate-400" x-text="count('{{ $key }}')"></span>
                        </button>
                    @endforeach
                </div>
                @if ($pending)
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            :checked="allVisibleSelected" @change="selectVisible($event.target.checked)">
                        {{ __('Select all shown') }}
                    </label>
                @endif
            </div>

            <div class="card divide-y divide-slate-100 overflow-visible">
                @foreach ($days as $date => $dayLines)
                    <section x-show="[{{ $dayLines->keys()->implode(',') }}].some((i) => visible(lines[i]))">
                        <h3 class="bg-slate-50/80 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 first:rounded-t-xl sm:px-5">
                            {{ \Carbon\Carbon::parse($date)->translatedFormat('D, d M Y') }}
                        </h3>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($dayLines as $index => $line)
                                @php
                                    [$initial, $avatarClass] = $avatar($line);
                                @endphp
                                <li x-show="visible(lines[{{ $index }}])" class="flex gap-3 px-4 py-3.5 transition sm:px-5"
                                    :class="{ 'opacity-50': !lines[{{ $index }}].selected }">
                                    <input type="checkbox" name="lines[{{ $line->id }}][selected]" value="1" x-model="lines[{{ $index }}].selected" @disabled(!$pending)
                                        aria-label="{{ __('Import') }} {{ $line->description }}"
                                        class="mt-2.5 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500">

                                    <span class="relative mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $avatarClass }}" aria-hidden="true">
                                        {{ $initial }}
                                        @if ($line->is_new)
                                            <span class="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full bg-amber-400 ring-2 ring-white"></span>
                                        @endif
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="break-words text-sm font-semibold text-slate-900">{{ $line->description }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    @if ($line->is_new)
                                                        <span class="font-medium text-amber-700">{{ __('New: never registered before') }}</span>
                                                    @elseif ($line->history_match === 'exact')
                                                        <i class="fa-solid fa-clock-rotate-left mr-0.5" aria-hidden="true"></i>{{ __('used :count× · last on :date', ['count' => $line->history_count, 'date' => date_format_custom($line->history_last_date)]) }}
                                                    @else
                                                        <i class="fa-solid fa-wand-magic-sparkles mr-0.5" aria-hidden="true"></i>{{ __('similar to') }} "{{ $line->history_description }}" · {{ $line->history_count }}×
                                                    @endif
                                                    @if ($line->value_date && $line->value_date !== $line->date)
                                                        · {{ __('value') }} {{ date_format_custom($line->value_date) }}
                                                    @endif
                                                </p>
                                            </div>
                                            <p class="amount shrink-0 text-base font-semibold {{ $line->amount < 0 ? 'text-slate-900' : 'text-emerald-600' }}">
                                                {{ ($line->amount > 0 ? '+' : '') . format_currency($line->amount) }}
                                            </p>
                                        </div>

                                        @if ($line->duplicateMovement)
                                            <p class="mt-2 flex items-start gap-1.5 rounded-lg bg-orange-50 px-2.5 py-1.5 text-xs text-orange-800">
                                                <i class="fa-solid fa-clone mt-0.5" aria-hidden="true"></i>
                                                <span>{{ __('Already registered') }}: {{ $line->duplicateMovement->description }} ({{ date_format_custom($line->duplicateMovement->date) }})</span>
                                            </p>
                                        @endif
                                        @if ($line->balance_mismatch)
                                            <p class="mt-2 text-xs font-medium text-rose-700"><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ __('Bank balance mismatch') }}</p>
                                        @endif

                                        {{-- what it becomes: type, then category / other wallet / person --}}
                                        <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                            <input type="hidden" name="lines[{{ $line->id }}][type]" :value="lines[{{ $index }}].type">
                                            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false">
                                                @if ($pending)
                                                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu"
                                                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                                                        <i class="fa-solid text-[10px]" :class="'fa-' + (typeIcons[lines[{{ $index }}].type] || 'circle')" aria-hidden="true"></i>
                                                        <span x-text="types[lines[{{ $index }}].type]"></span>
                                                        <i class="fa-solid fa-chevron-down text-[9px] opacity-60" aria-hidden="true"></i>
                                                    </button>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                                        <i class="fa-solid fa-{{ $typeIcons[$line->type] ?? 'circle' }} text-[10px]" aria-hidden="true"></i>{{ $types[$line->type] ?? $line->type }}
                                                    </span>
                                                @endif
                                                <div x-show="open" x-cloak x-transition.opacity.duration.100ms role="menu"
                                                    class="absolute left-0 z-30 mt-1.5 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                                    @foreach ($types as $value => $label)
                                                        <button type="button" role="menuitemradio" :aria-checked="(lines[{{ $index }}].type === '{{ $value }}').toString()"
                                                            @click="lines[{{ $index }}].type = '{{ $value }}'; choose({{ $index }}); open = false"
                                                            :class="lines[{{ $index }}].type === '{{ $value }}' ? 'bg-brand-50 text-brand-900' : 'text-slate-700 hover:bg-slate-50'"
                                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm">
                                                            <i class="fa-solid fa-{{ $typeIcons[$value] ?? 'circle' }} w-4 text-center text-xs text-slate-400" aria-hidden="true"></i>{{ $label }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            @include('statement-imports.category-picker', ['pending' => $pending])

                                            {{-- transfer: the other wallet --}}
                                            <input type="hidden" name="lines[{{ $line->id }}][transfer_wallet_id]" :value="lines[{{ $index }}].transfer_wallet_id">
                                            <div class="relative" x-show="lines[{{ $index }}].type === 'transfer'" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false">
                                                <button type="button" @click="open = !open" @disabled(!$pending) :aria-expanded="open.toString()"
                                                    :class="lines[{{ $index }}].transfer_wallet_id ? 'border-transparent bg-brand-50 text-brand-800' : (lines[{{ $index }}].selected ? 'border-dashed border-rose-300 bg-rose-50/50 text-rose-700' : 'border-dashed border-slate-300 text-slate-500')"
                                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                                    <i class="fa-solid {{ $line->amount < 0 ? 'fa-arrow-right' : 'fa-arrow-left' }} text-[10px]" aria-hidden="true"></i>
                                                    <span x-text="wallets[lines[{{ $index }}].transfer_wallet_id] || @js($line->amount < 0 ? __('To wallet') : __('From wallet'))"></span>
                                                    @if ($pending)<i class="fa-solid fa-chevron-down text-[9px] opacity-60" aria-hidden="true"></i>@endif
                                                </button>
                                                <div x-show="open" x-cloak role="menu" class="absolute left-0 z-30 mt-1.5 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                                    @foreach ($wallets as $walletId => $walletName)
                                                        @continue($walletId == $statementImport->wallet_id)
                                                        <button type="button" role="menuitemradio" @click="lines[{{ $index }}].transfer_wallet_id = '{{ $walletId }}'; choose({{ $index }}); open = false"
                                                            :class="lines[{{ $index }}].transfer_wallet_id === '{{ $walletId }}' ? 'bg-brand-50 text-brand-900' : 'text-slate-700 hover:bg-slate-50'"
                                                            class="flex w-full items-center px-3 py-2 text-left text-sm">{{ $walletName }}</button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- loan: the person --}}
                                            <input type="text" name="lines[{{ $line->id }}][loan_person]" x-model="lines[{{ $index }}].loan_person"
                                                x-show="lines[{{ $index }}].type === 'loan'" list="loan-people" autocomplete="off" aria-label="{{ __('Person') }}"
                                                placeholder="{{ $line->amount < 0 ? __('Lent to whom?') : __('Paid back by whom?') }}"
                                                :class="lines[{{ $index }}].selected && !String(lines[{{ $index }}].loan_person).trim() ? 'border-rose-300' : 'border-slate-300'"
                                                class="w-44 rounded-full border bg-white px-3 py-1 text-xs focus:border-brand-500 focus:ring-brand-500" @disabled(!$pending)>

                                            <span x-show="lines[{{ $index }}].suggested_from" x-cloak class="text-xs text-purple-700"
                                                x-text="@js(__('same as line')) + ' #' + lines[{{ $index }}].suggested_from"></span>
                                            @if ($line->financial_movement_id)
                                                <span class="text-xs font-medium text-emerald-700"><i class="fa-solid fa-check mr-0.5" aria-hidden="true"></i>{{ __('Imported') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
                <p x-show="lines.every((line) => !visible(line))" x-cloak class="px-5 py-10 text-center text-sm text-slate-500">{{ __('Nothing here.') }}</p>
            </div>

            @if ($pending)
                {{-- floating: what will be imported and the button --}}
                <div class="sticky bottom-3 z-20 mt-4 sm:bottom-4">
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-900 px-4 py-2.5 text-white shadow-2xl ring-1 ring-black/5 sm:px-5 sm:py-3">
                        <div class="min-w-0 text-sm">
                            <p><span class="font-semibold" x-text="selectedCount"></span> {{ __('selected') }} ·
                                <span class="amount font-semibold" x-text="(selectedTotal > 0 ? '+' : '') + money(selectedTotal)"></span></p>
                            <p x-show="missing > 0" x-cloak class="truncate text-xs text-amber-300">
                                <i class="fa-solid fa-circle-exclamation mr-0.5" aria-hidden="true"></i>
                                <span class="sm:hidden" x-text="missing + ' ' + @js(__('without category'))"></span>
                                <span class="hidden sm:inline" x-text="missing + ' ' + @js(__('selected line(s) still need a category or wallet'))"></span>
                            </p>
                            <p x-show="missing === 0 && selectedCount > 0" x-cloak class="text-xs text-slate-300">{{ __('Ready to import.') }}</p>
                        </div>
                        <button type="submit" :disabled="missing > 0 || selectedCount === 0"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40">
                            <span class="sm:hidden">{{ __('Import') }}</span>
                            <span class="hidden sm:inline" x-text="selectedCount === 1 ? @js(__('Import 1 movement')) : @js(__('Import :count movements')).replace(':count', selectedCount)"></span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            @endif
        </form>
    </div>

    @if ($pending)
        <x-modal name="discard-import" maxWidth="md">
            <form method="POST" action="{{ route('statement-imports.destroy', $statementImport) }}" class="space-y-4 p-5 sm:p-6">
                @csrf
                @method('DELETE')
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Discard this import?') }}</h3>
                <p class="text-sm text-slate-600">{{ __('Nothing was imported yet. The preview is deleted; you can read the statement again later.') }}</p>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                    <x-danger-button>{{ __('Discard import') }}</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endif

    <datalist id="loan-people">
        @foreach (\App\Services\LoanService::openPeople() as $openLoan)
            <option value="{{ $openLoan['person'] }}"></option>
        @endforeach
    </datalist>

    <script>
        function categoryPicker(index) {
            return {
                index,
                search: '',
                open: false,
                highlight: 0,
                saving: false,
                error: null,
                get line() {
                    return this.lines[this.index];
                },
                // expense/refund use expense categories, income/discount income ones
                get kind() {
                    return ['income', 'discount'].includes(this.line.type) ? 'income' : 'expense';
                },
                get options() {
                    const term = this.search.trim().toLowerCase();
                    const matches = this.categories.filter(c => c.name.toLowerCase().includes(term));
                    const byUse = (a, b) => b.uses - a.uses || a.name.localeCompare(b.name);
                    const toOption = c => ({ key: 'c' + c.id, id: c.id, label: c.name, uses: c.uses });

                    const options = matches.filter(c => c.type === this.kind).sort(byUse).map(toOption);
                    const others = matches.filter(c => c.type !== this.kind).sort(byUse).map(toOption);
                    if (others.length) {
                        options.push({ key: 'h-others', header: @json(__('Other categories')) }, ...others);
                    }

                    const exists = this.categories.some(c => c.name.toLowerCase() === term);
                    if (term && !exists) {
                        const label = this.kind === 'income' ? @json(__('as income')) : @json(__('as expense'));
                        options.push({ key: 'create', create: true, label: '+ ' + @json(__('Create')) + ' "' + this.search.trim() + '" ' + label });
                    }
                    return options;
                },
                selectedName() {
                    return this.categories.find(c => c.id === this.line.category_id)?.name || '';
                },
                openList() {
                    this.search = '';
                    this.error = null;
                    this.open = true;
                    this.highlight = this.options.findIndex(o => !o.header);
                    this.$nextTick(() => this.$refs.search.focus());
                },
                close() {
                    this.open = false;
                    this.search = '';
                },
                move(step) {
                    if (!this.open) this.openList();
                    const options = this.options;
                    let next = this.highlight;
                    do {
                        next = (next + step + options.length) % options.length;
                    } while (options[next]?.header && next !== this.highlight);
                    this.highlight = next;
                },
                async pick(i) {
                    const option = this.options[i];
                    if (!option || option.header) return;
                    if (option.create) {
                        await this.create(this.search.trim());
                        return;
                    }
                    this.line.category_id = option.id;
                    this.choose(this.index);
                    this.close();
                },
                async create(name) {
                    this.saving = true;
                    this.error = null;
                    try {
                        const response = await fetch(@json(route('financial-categories.store')), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ name, type: this.kind, active: 1 }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            this.error = Object.values(data.errors || {}).flat()[0] || data.message;
                            return;
                        }
                        this.categories.push({ id: String(data.id), name: data.name, type: data.type, uses: 0 });
                        this.line.category_id = String(data.id);
                        this.choose(this.index);
                        this.close();
                    } catch (e) {
                        this.error = @json(__('Something went wrong'));
                    } finally {
                        this.saving = false;
                    }
                },
            };
        }

        function statementImport() {
            const wallets = @json($wallets);
            const categorizedTypes = @json($categorizedTypes);
            return {
                wallets,
                types: @json($types),
                typeIcons: @json($typeIcons),
                lines: @json($linesData),
                categories: @json($categoriesData),
                systemBalance: {{ $statementImport->is_pending ? $systemBalance : $systemBalance - $statementImport->lines->whereNotNull('financial_movement_id')->sum('amount') }},
                bankClosing: @json($statementImport->closing_balance !== null ? (float) $statementImport->closing_balance : null),
                filter: 'all',
                money(value) {
                    return Number(value).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                },
                count(filter) {
                    if (filter === 'new') return this.lines.filter((l) => l.is_new).length;
                    if (filter === 'duplicate') return this.lines.filter((l) => l.duplicate).length;
                    return this.lines.length;
                },
                get allVisibleSelected() {
                    const shown = this.lines.filter((l) => this.visible(l));
                    return shown.length > 0 && shown.every((l) => l.selected);
                },
                selectVisible(checked) {
                    this.lines.filter((l) => this.visible(l)).forEach((l) => { l.selected = checked; });
                },
                visible(line) {
                    if (this.filter === 'new') return line.is_new;
                    if (this.filter === 'duplicate') return line.duplicate;
                    return true;
                },
                /**
                 * A choice made on one line fills the untouched, still empty lines
                 * of the same merchant (purchase codes ignored).
                 */
                choose(index) {
                    const source = this.lines[index];
                    source.touched = true;
                    source.suggested_from = null;

                    this.lines.forEach((line, i) => {
                        if (i === index || line.touched || !this.sameOrigin(line, source)) return;
                        // lines filled by this same source follow its changes; others only if still empty
                        if (line.suggested_from !== source.position) {
                            if (line.suggested_from || !line.is_new || line.category_id || line.transfer_wallet_id) return;
                        }

                        line.type = this.typeForSign(source.type, line.amount);
                        line.category_id = this.needsCategory(line) ? source.category_id : '';
                        line.transfer_wallet_id = line.type === 'transfer' ? source.transfer_wallet_id : '';
                        line.suggested_from = (line.category_id || line.transfer_wallet_id) ? source.position : null;
                    });
                },
                // transfers may go to third parties: only exactly equal descriptions
                sameOrigin(line, source) {
                    if (source.type === 'transfer' || source.transfer_text || line.transfer_text) {
                        return line.normalized === source.normalized;
                    }
                    return this.sameMerchant(line.keywords, source.keywords);
                },
                // same rule as MovementHistory::sameMerchant
                sameMerchant(a, b) {
                    const needed = Math.min(2, a.length, b.length);
                    if (needed === 0) return false;
                    for (let i = 0; i < needed; i++) {
                        if (a[i] !== b[i]) return false;
                    }
                    return needed === 2 || a[0].length >= 4;
                },
                // same rule as CreateStatementImportService::typeForSign
                typeForSign(type, amount) {
                    const credit = amount > 0;
                    if (type === 'expense' || type === 'refund') return credit ? 'refund' : 'expense';
                    if (type === 'income' || type === 'discount') return credit ? 'income' : 'discount';
                    return type;
                },
                needsCategory(line) {
                    return categorizedTypes.includes(line.type);
                },
                get selected() {
                    return this.lines.filter(l => l.selected);
                },
                get selectedCount() {
                    return this.selected.length;
                },
                get selectedTotal() {
                    return this.selected.reduce((sum, l) => sum + l.amount, 0);
                },
                get matchesBank() {
                    return this.bankClosing !== null && Math.abs(this.projected - this.bankClosing) < 0.005;
                },
                get projected() {
                    return this.systemBalance + this.selectedTotal;
                },
                get missing() {
                    return this.selected.filter(l =>
                        (this.needsCategory(l) && !l.category_id) ||
                        (l.type === 'transfer' && !l.transfer_wallet_id) ||
                        (l.type === 'loan' && !String(l.loan_person).trim())
                    ).length;
                },
                get otherWallets() {
                    const totals = {};
                    this.selected.filter(l => l.type === 'transfer' && l.transfer_wallet_id).forEach(l => {
                        totals[l.transfer_wallet_id] = (totals[l.transfer_wallet_id] || 0) - l.amount;
                    });
                    return Object.entries(totals).map(([id, total]) => ({ id, name: wallets[id], total }));
                },
            };
        }
    </script>
</x-app-layout>
