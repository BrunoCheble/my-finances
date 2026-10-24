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
                    <span aria-hidden="true">·</span>
                    <span class="truncate">{{ $statementImport->file_name }}</span>
                </span>
            </x-slot>
            <x-slot name="actions">
                <x-button :href="route('statement-imports.index')" variant="secondary" icon="arrow-left">{{ __('Back') }}</x-button>
                @if ($pending)
                    <x-button variant="ghost" icon="trash" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700" x-data x-on:click="$dispatch('open-modal', 'discard-import')">{{ __('Discard import') }}</x-button>
                @else
                    <x-badge color="green"><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>{{ __('Confirmed') }}</x-badge>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8" x-data="statementImport()">
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Balances: today, what is selected, the result, and what the bank says --}}
        <section class="card p-5 sm:p-6" aria-labelledby="balances-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="balances-title" class="text-base font-semibold text-slate-900">{{ __('Balance check') }}</h2>
                @if ($reconciles === true && $mismatches === 0)
                    <x-badge color="green"><i class="fa-solid fa-shield-halved text-[10px]" aria-hidden="true"></i>{{ __('Statement reconciled: no line lost or misread') }}</x-badge>
                @elseif ($reconciles === null)
                    <x-badge>{{ __('Statement has no balances to reconcile') }}</x-badge>
                @else
                    <x-badge color="red"><i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                        {{ __('Statement does not reconcile') }}@if ($mismatches) — {{ $mismatches }} {{ __('line(s) with balance mismatch') }}@endif</x-badge>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-medium text-slate-500">{{ $pending ? __('Current balance') : __('Balance before import') }}</dt>
                    <dd class="amount mt-1 text-lg font-semibold text-slate-900" x-text="money(systemBalance)"></dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-medium text-slate-500">{{ __('Selected') }} · <span x-text="selectedCount"></span></dt>
                    <dd class="amount mt-1 text-lg font-semibold" :class="selectedTotal < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="(selectedTotal > 0 ? '+' : '') + money(selectedTotal)"></dd>
                </div>
                <div class="rounded-xl bg-brand-50 p-4 ring-1 ring-inset ring-brand-100">
                    <dt class="text-xs font-medium text-brand-700">{{ __('Projected balance') }}</dt>
                    <dd class="amount mt-1 text-lg font-semibold text-brand-900" x-text="money(projected)"></dd>
                </div>
                <div class="rounded-xl p-4 ring-1 ring-inset"
                    :class="bankClosing === null ? 'bg-slate-50 ring-slate-100' : (Math.abs(projected - bankClosing) < 0.005 ? 'bg-emerald-50 ring-emerald-100' : 'bg-amber-50 ring-amber-100')">
                    <dt class="text-xs font-medium text-slate-500">{{ __('Bank closing balance') }}</dt>
                    <dd class="amount mt-1 text-lg font-semibold text-slate-900" x-text="bankClosing === null ? '—' : money(bankClosing)"></dd>
                    <dd class="mt-1 flex items-center gap-1 text-xs font-medium" x-show="bankClosing !== null"
                        :class="Math.abs(projected - bankClosing) < 0.005 ? 'text-emerald-700' : 'text-amber-800'">
                        <i class="fa-solid text-[10px]" :class="Math.abs(projected - bankClosing) < 0.005 ? 'fa-check' : 'fa-not-equal'" aria-hidden="true"></i>
                        <span x-text="Math.abs(projected - bankClosing) < 0.005 ? @js(__('Matches the projection')) : @js(__('Difference')) + ': ' + money(projected - bankClosing)"></span>
                    </dd>
                </div>
            </dl>

            <template x-if="otherWallets.length">
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <i class="fa-solid fa-arrow-right-arrow-left text-xs text-slate-400" aria-hidden="true"></i>{{ __('Other wallets (transfers)') }}:
                    <template x-for="item in otherWallets" :key="item.id">
                        <span class="amount font-medium" :class="item.total < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="item.name + ' ' + money(item.total)"></span>
                    </template>
                </p>
            </template>

            @if ($suggestedReserved !== null && ($suggestedReserved != 0 || $currentReserved != 0))
                {{-- held amount (valor cativo) read from the statement --}}
                <div class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600" aria-hidden="true"><i class="fa-solid fa-lock text-sm"></i></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="font-semibold text-slate-900">{{ __('Held amount (valor cativo)') }}</p>
                        <p class="text-slate-600">
                            @if ($suggestedReserved != 0)
                                {{ __('On :date the bank was holding :amount (accounting balance minus available balance).', ['date' => date_format_custom($statementImport->period_end), 'amount' => format_currency(-$suggestedReserved)]) }}
                            @else
                                {{ __('On :date the bank had no held amount.', ['date' => date_format_custom($statementImport->period_end)]) }}
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">{{ __('Reserved amount of :wallet now: :amount', ['wallet' => $statementImport->wallet->name, 'amount' => format_currency($currentReserved)]) }}</p>
                    </div>
                    @if (round($currentReserved, 2) == round($suggestedReserved, 2))
                        <x-badge color="green"><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>{{ __('Reserved amount is up to date') }}</x-badge>
                    @elseif (!$statementImport->isLatestForWallet())
                        <x-badge>{{ __('There is a more recent statement for this wallet') }}</x-badge>
                    @else
                        <form method="POST" action="{{ route('statement-imports.reserved-amount', $statementImport) }}">
                            @csrf
                            @method('PATCH')
                            <x-button type="submit" variant="secondary" size="sm">
                                {{ $suggestedReserved == 0 ? __('Clear reserved amount') : __('Update reserved amount to :amount', ['amount' => format_currency($suggestedReserved)]) }}
                            </x-button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

        {{-- Lines --}}
        <form id="import-form" method="POST" action="{{ route('statement-imports.update', $statementImport) }}" class="card">
            @csrf
            @method('PATCH')

            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" role="tablist" aria-label="{{ __('Filter') }}">
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

            {{-- column titles (the rows are a grid, stacked on phones) --}}
            <div class="hidden grid-cols-[1.5rem_6.5rem_minmax(0,1fr)_7rem_9rem_14rem] gap-3 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid" aria-hidden="true">
                <span></span><span>{{ __('Date') }}</span><span>{{ __('Description') }}</span><span class="text-right">{{ __('Amount') }}</span><span>{{ __('Type') }}</span><span>{{ __('Category') }} / {{ __('Wallet') }}</span>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach ($statementImport->lines as $index => $line)
                    <li x-show="visible(lines[{{ $index }}])"
                        class="relative grid grid-cols-[1.5rem_minmax(0,1fr)_auto] gap-x-3 gap-y-2 px-4 py-3 transition sm:px-5 lg:grid-cols-[1.5rem_6.5rem_minmax(0,1fr)_7rem_9rem_14rem] lg:items-start"
                        :class="{ 'opacity-55': !lines[{{ $index }}].selected }">
                        @if ($line->is_new)
                            <span class="absolute inset-y-0 left-0 w-1 bg-amber-400" aria-hidden="true"></span>
                        @endif
                        <input type="checkbox" name="lines[{{ $line->id }}][selected]" value="1" x-model="lines[{{ $index }}].selected" @disabled(!$pending)
                            aria-label="{{ __('Import') }} {{ $line->description }}"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">

                        <div class="hidden text-sm text-slate-600 lg:block">
                            {{ date_format_custom($line->date) }}
                            @if ($line->value_date && $line->value_date !== $line->date)
                                <div class="text-xs text-slate-400" title="{{ __('Value date') }}">{{ __('value') }} {{ date_format_custom($line->value_date) }}</div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="break-words text-sm font-medium text-slate-900">{{ $line->description }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1 text-xs">
                                <span class="text-slate-500 lg:hidden">{{ date_format_custom($line->date) }}</span>
                                @if ($line->is_new)
                                    <x-badge color="yellow"><i class="fa-solid fa-star text-[9px]" aria-hidden="true"></i>{{ __('New') }}</x-badge>
                                    <span x-show="lines[{{ $index }}].suggested_from" x-cloak class="badge bg-purple-50 text-purple-700"
                                        x-text="@js(__('same as line')) + ' #' + lines[{{ $index }}].suggested_from"></span>
                                @elseif ($line->history_match === 'exact')
                                    <x-badge color="green"><i class="fa-solid fa-clock-rotate-left text-[9px]" aria-hidden="true"></i>{{ __('used :count× · last on :date', ['count' => $line->history_count, 'date' => date_format_custom($line->history_last_date)]) }}</x-badge>
                                @else
                                    <x-badge color="brand">{{ __('similar to') }} "{{ $line->history_description }}" · {{ $line->history_count }}×</x-badge>
                                @endif
                                @if ($line->duplicateMovement)
                                    <span class="badge bg-orange-50 text-orange-700"><i class="fa-solid fa-clone text-[9px]" aria-hidden="true"></i>{{ __('Already registered') }}: {{ $line->duplicateMovement->description }} ({{ date_format_custom($line->duplicateMovement->date) }})</span>
                                @endif
                                @if ($line->balance_mismatch)
                                    <x-badge color="red">{{ __('Bank balance mismatch') }}</x-badge>
                                @endif
                                @if ($line->financial_movement_id)
                                    <x-badge color="green"><i class="fa-solid fa-check text-[9px]" aria-hidden="true"></i>{{ __('Imported') }}</x-badge>
                                @endif
                            </div>
                        </div>

                        <p class="amount text-right text-sm font-semibold {{ $line->amount < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ ($line->amount > 0 ? '+' : '') . format_currency($line->amount) }}
                        </p>

                        {{-- type and category: their own full-width row on phones --}}
                        <div class="col-span-2 col-start-2 lg:col-span-1 lg:col-start-auto">
                            <label for="type-{{ $line->id }}" class="sr-only">{{ __('Type') }}</label>
                            <select id="type-{{ $line->id }}" name="lines[{{ $line->id }}][type]" x-model="lines[{{ $index }}].type" @change="choose({{ $index }})" class="{{ $selectClass }}" @disabled(!$pending)>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2 col-start-2 lg:col-span-1 lg:col-start-auto">
                            @include('statement-imports.category-picker', ['pending' => $pending])
                            <input type="text" name="lines[{{ $line->id }}][loan_person]" x-model="lines[{{ $index }}].loan_person"
                                x-show="lines[{{ $index }}].type === 'loan'" list="loan-people" autocomplete="off"
                                aria-label="{{ __('Person') }}"
                                placeholder="{{ $line->amount < 0 ? __('Lent to whom?') : __('Paid back by whom?') }}"
                                :class="lines[{{ $index }}].selected && !lines[{{ $index }}].loan_person ? '!border-rose-400' : ''"
                                class="{{ $selectClass }}" @disabled(!$pending)>
                            <select name="lines[{{ $line->id }}][transfer_wallet_id]" x-model="lines[{{ $index }}].transfer_wallet_id" @change="choose({{ $index }})"
                                x-show="lines[{{ $index }}].type === 'transfer'" aria-label="{{ __('Wallet') }}"
                                :class="lines[{{ $index }}].selected && !lines[{{ $index }}].transfer_wallet_id ? '!border-rose-400' : ''"
                                class="{{ $selectClass }}" @disabled(!$pending)>
                                <option value="">{{ $line->amount < 0 ? __('To wallet') : __('From wallet') }}</option>
                                @foreach ($wallets as $walletId => $walletName)
                                    @continue($walletId == $statementImport->wallet_id)
                                    <option value="{{ $walletId }}">{{ $walletName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p x-show="lines.every((line) => !visible(line))" x-cloak class="px-5 py-10 text-center text-sm text-slate-500">{{ __('Nothing here.') }}</p>

            @if ($pending)
                {{-- always at hand: what will be imported and the button --}}
                <div class="sticky bottom-0 z-20 flex flex-col gap-2 rounded-b-xl border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <p class="text-sm text-slate-600">
                        <span class="font-semibold text-slate-900" x-text="selectedCount"></span> {{ __('selected') }} ·
                        <span class="amount font-semibold" :class="selectedTotal < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="(selectedTotal > 0 ? '+' : '') + money(selectedTotal)"></span>
                        <span x-show="missing > 0" x-cloak class="mt-0.5 block text-xs font-medium text-rose-600 sm:ml-2 sm:mt-0 sm:inline">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            <span x-text="missing + ' ' + @js(__('selected line(s) still need a category or wallet'))"></span>
                        </span>
                    </p>
                    <x-button type="submit" icon="file-import" x-bind:disabled="missing > 0 || selectedCount === 0" class="w-full sm:w-auto">{{ __('Import selected') }}</x-button>
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
