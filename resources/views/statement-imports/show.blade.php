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
    $selectClass = 'block w-full rounded-md border border-gray-300 bg-white py-1 pl-2 pr-8 text-xs focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-gray-100';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Statement') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="statementImport()">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                @include('layouts.alert')
                @if ($errors->any())
                    <div class="p-4 mb-4 text-sm text-red-800 border border-red-300 rounded-lg bg-red-50">
                        <ul class="list-disc pl-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex flex-col lg:flex-row lg:items-start gap-6">
                    <div class="flex-1 space-y-1">
                        <h1 class="text-lg font-semibold text-gray-900">{{ $statementImport->wallet->name }}
                            <span class="ml-2 text-xs uppercase text-gray-400">{{ $statementImport->bank }}</span>
                        </h1>
                        <p class="text-sm text-gray-600">{{ $statementImport->file_name }}</p>
                        @if ($statementImport->period_start)
                            <p class="text-sm text-gray-600">{{ __('Period') }}: {{ date_format_custom($statementImport->period_start) }} - {{ date_format_custom($statementImport->period_end) }}</p>
                        @endif
                        <div class="pt-2 text-sm">
                            @if ($reconciles === true && $mismatches === 0)
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Statement reconciled: no line lost or misread') }}</span>
                            @elseif ($reconciles === null)
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ __('Statement has no balances to reconcile') }}</span>
                            @else
                                <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800">
                                    {{ __('Statement does not reconcile') }}@if ($mismatches) — {{ $mismatches }} {{ __('line(s) with balance mismatch') }}@endif
                                </span>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 lg:w-2/3">
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs uppercase text-gray-500">{{ $statementImport->is_pending ? __('Current balance') : __('Balance before import') }}</dt>
                            <dd class="mt-1 text-base font-semibold text-gray-900" x-text="money(systemBalance)"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs uppercase text-gray-500">{{ __('Selected') }} (<span x-text="selectedCount"></span>)</dt>
                            <dd class="mt-1 text-base font-semibold" :class="selectedTotal < 0 ? 'text-red-600' : 'text-green-600'" x-text="money(selectedTotal)"></dd>
                        </div>
                        <div class="rounded-lg bg-indigo-50 p-3">
                            <dt class="text-xs uppercase text-indigo-700">{{ __('Projected balance') }}</dt>
                            <dd class="mt-1 text-base font-semibold text-indigo-900" x-text="money(projected)"></dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs uppercase text-gray-500">{{ __('Bank closing balance') }}</dt>
                            <dd class="mt-1 text-base font-semibold text-gray-900" x-text="bankClosing === null ? '—' : money(bankClosing)"></dd>
                            <dd class="mt-1 text-xs" x-show="bankClosing !== null"
                                :class="Math.abs(projected - bankClosing) < 0.005 ? 'text-green-700' : 'text-red-600'"
                                x-text="Math.abs(projected - bankClosing) < 0.005 ? '{{ __('Matches the projection') }}' : '{{ __('Difference') }}: ' + money(projected - bankClosing)"></dd>
                        </div>
                    </dl>
                </div>

                <template x-if="otherWallets.length">
                    <p class="mt-4 text-sm text-gray-600">
                        {{ __('Other wallets (transfers)') }}:
                        <template x-for="item in otherWallets" :key="item.id">
                            <span class="ml-2 font-medium" :class="item.total < 0 ? 'text-red-600' : 'text-green-600'" x-text="item.name + ' ' + money(item.total)"></span>
                        </template>
                    </p>
                </template>
            </div>

            @php
                $suggestedReserved = $statementImport->suggestedReservedAmount();
                $currentReserved = (float) $statementImport->wallet->reserved_amount;
            @endphp
            @if ($suggestedReserved !== null && ($suggestedReserved != 0 || $currentReserved != 0))
                <div class="p-4 sm:px-8 bg-white shadow sm:rounded-lg flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">{{ __('Held amount (valor cativo)') }}</p>
                        @if ($suggestedReserved != 0)
                            <p>{{ __('On :date the bank was holding :amount (accounting balance minus available balance).', ['date' => date_format_custom($statementImport->period_end), 'amount' => format_currency(-$suggestedReserved)]) }}</p>
                        @else
                            <p>{{ __('On :date the bank had no held amount.', ['date' => date_format_custom($statementImport->period_end)]) }}</p>
                        @endif
                        <p class="text-gray-500">{{ __('Reserved amount of :wallet now: :amount', ['wallet' => $statementImport->wallet->name, 'amount' => format_currency($currentReserved)]) }}</p>
                    </div>
                    @if (round($currentReserved, 2) == round($suggestedReserved, 2))
                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">{{ __('Reserved amount is up to date') }}</span>
                    @elseif (!$statementImport->isLatestForWallet())
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ __('There is a more recent statement for this wallet') }}</span>
                    @else
                        <form method="POST" action="{{ route('statement-imports.reserved-amount', $statementImport) }}">
                            @csrf
                            @method('PATCH')
                            <x-secondary-button type="submit">
                                {{ $suggestedReserved == 0 ? __('Clear reserved amount') : __('Update reserved amount to :amount', ['amount' => format_currency($suggestedReserved)]) }}
                            </x-secondary-button>
                        </form>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('statement-imports.update', $statementImport) }}" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                @csrf
                @method('PATCH')

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex gap-2 text-sm">
                        <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-md px-3 py-1">{{ __('All') }} (<span x-text="lines.length"></span>)</button>
                        <button type="button" @click="filter = 'new'" :class="filter === 'new' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-md px-3 py-1">{{ __('New only') }} (<span x-text="lines.filter(l => l.is_new).length"></span>)</button>
                        <button type="button" @click="filter = 'duplicate'" :class="filter === 'duplicate' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-md px-3 py-1">{{ __('Duplicates') }} (<span x-text="lines.filter(l => l.duplicate).length"></span>)</button>
                    </div>
                    @if ($statementImport->is_pending)
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-red-600" x-show="missing > 0" x-text="missing + ' {{ __('selected line(s) still need a category or wallet') }}'"></span>
                            <x-primary-button x-bind:disabled="missing > 0" class="disabled:opacity-50">{{ __('Import selected') }}</x-primary-button>
                        </div>
                    @endif
                </div>

                <div class="mt-6 overflow-x-auto lg:overflow-visible">
                    <table class="w-full divide-y divide-gray-300 text-sm">
                        <thead>
                            <tr>
                                <th class="py-2 pl-2 pr-2 text-left"></th>
                                <th class="py-2 px-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Date') }}</th>
                                <th class="py-2 px-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Description') }}</th>
                                <th class="py-2 px-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Amount') }}</th>
                                <th class="py-2 px-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">{{ __('Type') }}</th>
                                <th class="py-2 px-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-48">{{ __('Category') }} / {{ __('Wallet') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($statementImport->lines as $index => $line)
                                <tr x-show="visible(lines[{{ $index }}])"
                                    :class="{ 'bg-gray-50 text-gray-400': !lines[{{ $index }}].selected, 'bg-yellow-50': lines[{{ $index }}].selected && lines[{{ $index }}].is_new }">
                                    <td class="py-2 pl-2 pr-2 align-top">
                                        <input type="checkbox" name="lines[{{ $line->id }}][selected]" value="1" x-model="lines[{{ $index }}].selected"
                                            @disabled(!$statementImport->is_pending)
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2 px-2 align-top whitespace-nowrap">
                                        {{ date_format_custom($line->date) }}
                                        @if ($line->value_date && $line->value_date !== $line->date)
                                            <div class="text-xs text-gray-400" title="{{ __('Value date') }}">{{ __('value') }} {{ date_format_custom($line->value_date) }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 align-top">
                                        <div class="font-medium">{{ $line->description }}</div>
                                        <div class="mt-1 flex flex-wrap gap-1 text-xs">
                                            @if ($line->is_new)
                                                <span class="rounded-full bg-yellow-200 px-2 py-0.5 font-semibold text-yellow-900">{{ __('New') }}</span>
                                                <span x-show="lines[{{ $index }}].suggested_from" x-cloak class="rounded-full bg-purple-100 px-2 py-0.5 text-purple-800"
                                                    x-text="'{{ __('same as line') }} #' + lines[{{ $index }}].suggested_from"></span>
                                            @elseif ($line->history_match === 'exact')
                                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-green-800">{{ __('used :count× · last on :date', ['count' => $line->history_count, 'date' => date_format_custom($line->history_last_date)]) }}</span>
                                            @else
                                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-blue-800">{{ __('similar to') }} "{{ $line->history_description }}" · {{ $line->history_count }}×</span>
                                            @endif
                                            @if ($line->duplicateMovement)
                                                <span class="rounded-full bg-orange-100 px-2 py-0.5 text-orange-800">{{ __('Already registered') }}: {{ $line->duplicateMovement->description }} ({{ date_format_custom($line->duplicateMovement->date) }})</span>
                                            @endif
                                            @if ($line->balance_mismatch)
                                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-red-800">{{ __('Bank balance mismatch') }}</span>
                                            @endif
                                            @if ($line->financial_movement_id)
                                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-green-800">{{ __('Imported') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-2 px-2 align-top text-right whitespace-nowrap font-medium {{ $line->amount < 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ format_currency($line->amount) }}
                                    </td>
                                    <td class="py-2 px-2 align-top">
                                        <select name="lines[{{ $line->id }}][type]" x-model="lines[{{ $index }}].type" @change="choose({{ $index }})" class="{{ $selectClass }}" @disabled(!$statementImport->is_pending)>
                                            @foreach ($types as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-2 px-2 align-top">
                                        @include('statement-imports.category-picker', ['pending' => $statementImport->is_pending])
                                        <input type="text" name="lines[{{ $line->id }}][loan_person]" x-model="lines[{{ $index }}].loan_person"
                                            x-show="lines[{{ $index }}].type === 'loan'" list="loan-people" autocomplete="off"
                                            placeholder="{{ $line->amount < 0 ? __('Lent to whom?') : __('Paid back by whom?') }}"
                                            :class="lines[{{ $index }}].selected && !lines[{{ $index }}].loan_person ? 'border-red-400' : ''"
                                            class="{{ $selectClass }}" @disabled(!$statementImport->is_pending)>
                                        <select name="lines[{{ $line->id }}][transfer_wallet_id]" x-model="lines[{{ $index }}].transfer_wallet_id" @change="choose({{ $index }})"
                                            x-show="lines[{{ $index }}].type === 'transfer'"
                                            :class="lines[{{ $index }}].selected && !lines[{{ $index }}].transfer_wallet_id ? 'border-red-400' : ''"
                                            class="{{ $selectClass }}" @disabled(!$statementImport->is_pending)>
                                            <option value="">{{ $line->amount < 0 ? __('To wallet') : __('From wallet') }}</option>
                                            @foreach ($wallets as $walletId => $walletName)
                                                @continue($walletId == $statementImport->wallet_id)
                                                <option value="{{ $walletId }}">{{ $walletName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            @if ($statementImport->is_pending)
                <form method="POST" action="{{ route('statement-imports.destroy', $statementImport) }}" class="text-right"
                    onsubmit="return confirm('{{ __('Discard this import?') }}')">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm text-red-600 hover:text-red-500">{{ __('Discard import') }}</button>
                </form>
            @endif
        </div>
    </div>

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
                    return Number(value).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
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
