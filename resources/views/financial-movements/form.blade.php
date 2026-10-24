{{--
    Movement form, used to create (modal-create, submitted via fetch) and to edit (modal-edit).
    The Alpine component "movementForm" is defined once in financial-movements/form-script.
--}}
@php
    $fm = $financialMovement;
    $isLoanLike = in_array($fm?->type, [\App\Enums\FinancialMovementType::LOAN, \App\Enums\FinancialMovementType::ADJUSTMENT]);
    $amount = $fm?->exists ? ($isLoanLike ? (float) $fm->amount : abs((float) $fm->amount)) : null;
    $config = [
        'mode' => $fm?->exists ? 'edit' : 'create',
        'type' => old('type', $fm?->type ?: \App\Enums\FinancialMovementType::EXPENSE),
        'amount' => old('amount', $amount !== null ? number_format($amount, 2, ',', '') : ''),
        'description' => old('description', $fm?->description ?? ''),
        'date' => old('date', $fm?->date ?: now()->format('Y-m-d')),
        'categoryId' => (string) old('category_id', $fm?->category_id ?? ''),
        'walletId' => (string) old('wallet_id', $fm?->wallet_id ?? $walletSection?->id ?? $walletsSidebar->firstWhere('type', '!=', 'third_party')?->id ?? ''),
        'transferToWalletId' => (string) old('transfer_to_wallet_id', $fm?->destinationMovement?->wallet_id ?? ''),
        'includeAlert' => (bool) old('include_alert', $fm?->include_alert),
        'loanPerson' => old('loan_person', $fm?->loan?->person ?? ''),
        'loanDirection' => old('loan_direction', $fm?->exists && $fm->type === \App\Enums\FinancialMovementType::LOAN && $fm->amount > 0 ? 'receive' : 'lend'),
        'openLoans' => $openLoans ?? [],
        'categories' => $categoryOptions,
        // inactive wallets leave the form, unless the edited movement already uses one
        'wallets' => $walletsSidebar
            ->filter(fn ($wallet) => $wallet->active || in_array($wallet->id, [$fm?->wallet_id, $fm?->destinationMovement?->wallet_id]))
            ->map(fn ($wallet) => ['id' => (string) $wallet->id, 'name' => $wallet->name, 'color' => $wallet->color ?? '#94a3b8'])->values(),
        'types' => $types,
        // last confirmed month per wallet: saving in or before it changes a confirmed balance
        'reconciled' => (object) ($reconciledMonths ?? \App\Services\ReconciliationService::lastReconciledMonths()),
        'originalPositions' => $fm?->exists
            ? collect([$fm, $fm->destinationMovement])->filter()->map(fn ($m) => ['walletId' => (string) $m->wallet_id, 'date' => $m->date])->values()
            : [],
        'allWallets' => $walletsSidebar->mapWithKeys(fn ($wallet) => [(string) $wallet->id => $wallet->name]),
        'confirmReconciled' => (bool) old('confirm_reconciled'),
    ];
    $uid = $fm?->exists ? 'edit-' . $fm->id : 'create';
@endphp

<div x-data="movementForm(@js($config))" class="flex flex-1 flex-col">
    <input type="hidden" name="type" :value="type">
    <input type="hidden" name="amount" :value="normalizedAmount">
    <input type="hidden" name="category_id" :value="needsCategory ? categoryId : ''">
    <input type="hidden" name="wallet_id" :value="walletId">
    <input type="hidden" name="transfer_to_wallet_id" :value="transferToWalletId" :disabled="type !== 'transfer'">
    <input type="hidden" name="include_alert" value="1" :disabled="!includeAlert">
    <input type="hidden" name="loan_person" :value="loanPerson" :disabled="type !== 'loan'">
    <input type="hidden" name="loan_direction" :value="loanDirection" :disabled="type !== 'loan'">
    <input type="hidden" name="confirm_reconciled" value="1" :disabled="!reconciledWarning || !confirmReconciled">

    <div class="flex-1 space-y-6 px-5 pb-6 sm:px-6">
        {{-- Type --}}
        <div role="radiogroup" aria-label="{{ __('Type') }}" class="grid grid-cols-4 gap-1 rounded-xl bg-slate-100 p-1">
            <template x-for="option in mainTypes" :key="option.value">
                <button type="button" role="radio" :aria-checked="type === option.value" @click="setType(option.value)"
                    :class="type === option.value ? 'bg-white shadow-sm ' + option.active : 'text-slate-600 hover:text-slate-900'"
                    class="flex flex-col items-center gap-1 rounded-lg px-2 py-2 text-xs font-semibold transition sm:flex-row sm:justify-center sm:text-sm">
                    <i class="fa-solid" :class="'fa-' + option.icon" aria-hidden="true"></i>
                    <span x-text="option.label"></span>
                </button>
            </template>
            <div class="relative" @click.outside="moreTypes = false">
                <button type="button" @click="moreTypes = !moreTypes" :aria-expanded="moreTypes"
                    :class="isOtherType ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    class="flex w-full flex-col items-center gap-1 rounded-lg px-2 py-2 text-xs font-semibold transition sm:flex-row sm:justify-center sm:text-sm">
                    <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                    <span x-text="isOtherType ? types[type] : @js(__('More'))" class="truncate"></span>
                </button>
                <div x-show="moreTypes" x-cloak x-transition
                    class="absolute right-0 z-30 mt-2 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    <template x-for="option in otherTypes" :key="option.value">
                        <button type="button" @click="setType(option.value); moreTypes = false"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid w-4 text-slate-400" :class="'fa-' + option.icon" aria-hidden="true"></i>
                            <span x-text="option.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Amount --}}
        <div class="text-center">
            <label for="amount-{{ $uid }}" class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Amount') }}</label>
            <div class="mt-1 flex items-baseline justify-center gap-1" :class="amountColor">
                <span class="text-3xl font-semibold sm:text-4xl" x-text="amountSign" aria-hidden="true"></span>
                <input id="amount-{{ $uid }}" x-ref="amount" type="text" inputmode="decimal" autocomplete="off" placeholder="0,00"
                    x-model="amount" :style="`width: ${(amount.length || 4) + 0.2}ch`"
                    class="max-w-[12ch] border-0 bg-transparent p-0 text-left text-4xl font-semibold tabular-nums placeholder:text-slate-300 focus:ring-0 sm:text-5xl">
                <span class="text-2xl font-semibold sm:text-3xl" aria-hidden="true">€</span>
            </div>
            <x-input-error class="mt-1" :messages="$errors->get('amount')" />
        </div>

        {{-- Description: autocomplete from the history + hint of the last use --}}
        <div class="relative" @click.outside="closeDescriptions()">
            <x-input-label for="description-{{ $uid }}" :value="__('Description')" />
            <x-text-input id="description-{{ $uid }}" name="description" type="text" class="mt-1 block w-full" x-ref="description"
                x-model="description" x-on:change="lookupHistory()" autocomplete="off" :placeholder="__('E.g. Supermarket')"
                role="combobox" aria-autocomplete="list" aria-controls="description-list-{{ $uid }}"
                x-bind:aria-expanded="descriptionsOpen.toString()"
                x-bind:aria-activedescendant="descriptionsOpen ? 'description-{{ $uid }}-' + descriptionHighlight : null"
                x-on:input="searchDescriptions()" x-on:focus="searchDescriptions()"
                x-on:keydown.arrow-down.prevent="moveDescription(1)" x-on:keydown.arrow-up.prevent="moveDescription(-1)"
                x-on:keydown.enter="if (descriptionsOpen && descriptionHighlight >= 0) { $event.preventDefault(); pickDescription(descriptionHighlight) }"
                x-on:keydown.escape="if (descriptionsOpen) { $event.stopPropagation(); closeDescriptions() }"
                x-on:keydown.tab="closeDescriptions()" />
            <ul id="description-list-{{ $uid }}" x-show="descriptionsOpen" x-cloak role="listbox" aria-label="{{ __('Previous descriptions') }}"
                class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                <template x-for="(item, i) in descriptionSuggestions" :key="item.description">
                    <li :id="'description-{{ $uid }}-' + i" role="option" :aria-selected="(descriptionHighlight === i).toString()"
                        @mousedown.prevent="pickDescription(i)" @mouseenter="descriptionHighlight = i"
                        :class="descriptionHighlight === i ? 'bg-brand-50' : ''"
                        class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-slate-900" x-text="item.description"></span>
                            <span class="block truncate text-xs text-slate-500"
                                x-text="[types[item.type], item.category, item.uses > 1 ? item.uses + '×' : null].filter(Boolean).join(' · ')"></span>
                        </span>
                        <span class="amount shrink-0 text-xs font-semibold" :class="item.amount < 0 ? 'text-rose-600' : 'text-emerald-600'"
                            x-text="(item.amount > 0 ? '+' : '') + money(item.amount)"></span>
                    </li>
                </template>
            </ul>
            <p x-show="suggestion" x-cloak class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-500">
                <i class="fa-solid fa-clock-rotate-left text-brand-500" aria-hidden="true"></i>
                <span x-text="suggestion"></span>
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('description')" />
        </div>

        {{-- Loan: who and which direction --}}
        <div x-show="type === 'loan'" x-cloak class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
            <div role="radiogroup" aria-label="{{ __('Loan') }}" class="grid grid-cols-2 gap-1 rounded-lg bg-white p-1 ring-1 ring-amber-200">
                <button type="button" role="radio" :aria-checked="loanDirection === 'lend'" @click="loanDirection = 'lend'"
                    :class="loanDirection === 'lend' ? 'bg-amber-100 text-amber-900' : 'text-slate-600'" class="rounded-md px-3 py-1.5 text-sm font-semibold">{{ __('I lent') }}</button>
                <button type="button" role="radio" :aria-checked="loanDirection === 'receive'" @click="loanDirection = 'receive'"
                    :class="loanDirection === 'receive' ? 'bg-amber-100 text-amber-900' : 'text-slate-600'" class="rounded-md px-3 py-1.5 text-sm font-semibold">{{ __('I received back') }}</button>
            </div>
            <div>
                <x-input-label for="loan-person-{{ $uid }}" :value="__('Person')" />
                <x-text-input id="loan-person-{{ $uid }}" x-model="loanPerson" list="loan-people-{{ $uid }}" class="mt-1 block w-full" autocomplete="off" :placeholder="__('Who?')" />
                <datalist id="loan-people-{{ $uid }}">
                    <template x-for="loan in openLoans" :key="loan.person"><option :value="loan.person"></option></template>
                </datalist>
                <p x-show="openLoan" class="mt-1.5 text-xs text-amber-800" x-text="openLoan ? @js(__('Still owes you')) + ' ' + money(openLoan.outstanding) : ''"></p>
                <x-input-error class="mt-2" :messages="$errors->get('loan_person')" />
            </div>
            <p class="text-xs text-slate-600">{{ __('A loan does not change your net worth: the money stays yours as "to receive". Anything paid back above it counts as interest.') }}</p>
        </div>

        {{-- Category (searchable, can create) --}}
        <div x-show="needsCategory" class="relative" @click.outside="closeCategories()">
            <x-input-label for="category-{{ $uid }}" :value="__('Category')" />
            <div class="relative mt-1">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                <input id="category-{{ $uid }}" type="text" autocomplete="off" role="combobox" :aria-expanded="categoriesOpen"
                    :value="categoriesOpen ? categorySearch : categoryName()"
                    @input="categorySearch = $event.target.value; highlight = firstOption()"
                    @focus="openCategories()" @click="openCategories()"
                    @keydown.arrow-down.prevent="moveHighlight(1)" @keydown.arrow-up.prevent="moveHighlight(-1)"
                    @keydown.enter.prevent="pickCategory(highlight)" @keydown.escape.stop.prevent="closeCategories()" @keydown.tab="closeCategories()"
                    placeholder="{{ __('Select category') }}" class="input block w-full pl-8">
            </div>
            <ul x-show="categoriesOpen" x-cloak role="listbox"
                class="absolute left-0 right-0 z-30 mt-1 max-h-60 overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                <template x-for="(option, i) in categoryOptions" :key="option.key">
                    <li>
                        <div x-show="option.header" class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400" x-text="option.header"></div>
                        <button type="button" x-show="!option.header" role="option" :aria-selected="option.id === categoryId"
                            @mousedown.prevent="pickCategory(i)" @mouseenter="highlight = i"
                            :class="highlight === i ? 'bg-brand-50 text-brand-900' : 'text-slate-700'"
                            class="flex w-full items-center justify-between px-3 py-2 text-left">
                            <span x-text="option.label" :class="option.create && 'font-semibold text-brand-700'"></span>
                            <span x-show="option.uses" class="text-xs text-slate-400" x-text="option.uses + '×'"></span>
                        </button>
                    </li>
                </template>
                <li x-show="categorySaving" class="px-3 py-2 text-slate-500">{{ __('Creating category...') }}</li>
                <li x-show="categoryError" class="px-3 py-2 text-rose-600" x-text="categoryError"></li>
            </ul>
            <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
        </div>

        {{-- Date --}}
        <div>
            <x-input-label for="date-{{ $uid }}" :value="__('Date')" />
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <button type="button" @click="date = today()" :aria-pressed="date === today()"
                    :class="date === today() ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                    class="rounded-full border px-3 py-1.5 text-sm font-medium">{{ __('Today') }}</button>
                <button type="button" @click="date = yesterday()" :aria-pressed="date === yesterday()"
                    :class="date === yesterday() ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                    class="rounded-full border px-3 py-1.5 text-sm font-medium">{{ __('Yesterday') }}</button>
                <x-text-input id="date-{{ $uid }}" name="date" type="date" x-model="date" class="min-w-0 flex-1 sm:flex-none" />
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('date')" />
        </div>

        {{-- Wallets --}}
        <div class="space-y-4">
            <fieldset>
                <legend class="label" x-text="type === 'transfer' ? @js(__('From wallet')) : @js(__('Wallet'))"></legend>
                <div class="mt-1 flex flex-wrap gap-2" role="radiogroup">
                    <template x-for="wallet in wallets" :key="wallet.id">
                        <button type="button" role="radio" :aria-checked="walletId === wallet.id" @click="walletId = wallet.id"
                            :class="walletId === wallet.id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:border-slate-300'"
                            class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition">
                            <span class="h-2.5 w-2.5 rounded-full" :style="`background-color: ${wallet.color}`" aria-hidden="true"></span>
                            <span x-text="wallet.name"></span>
                        </button>
                    </template>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('wallet_id')" />
            </fieldset>

            <fieldset x-show="type === 'transfer'" x-cloak>
                <legend class="label flex items-center gap-2"><i class="fa-solid fa-arrow-down text-slate-400" aria-hidden="true"></i>{{ __('To wallet') }}</legend>
                <div class="mt-1 flex flex-wrap gap-2" role="radiogroup">
                    <template x-for="wallet in wallets.filter(w => w.id !== walletId)" :key="wallet.id">
                        <button type="button" role="radio" :aria-checked="transferToWalletId === wallet.id" @click="transferToWalletId = wallet.id"
                            :class="transferToWalletId === wallet.id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:border-slate-300'"
                            class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition">
                            <span class="h-2.5 w-2.5 rounded-full" :style="`background-color: ${wallet.color}`" aria-hidden="true"></span>
                            <span x-text="wallet.name"></span>
                        </button>
                    </template>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('transfer_to_wallet_id')" />
            </fieldset>
        </div>

        {{-- Confirmed month: warn and ask before changing it --}}
        <div x-show="reconciledWarning" x-cloak role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm">
            <p class="flex items-start gap-2 font-semibold text-amber-900">
                <i class="fa-solid fa-lock mt-0.5" aria-hidden="true"></i>
                <span x-text="reconciledWarning"></span>
            </p>
            <p class="mt-1 pl-6 text-amber-800">{{ __('The balance you confirmed will no longer match. The change is recorded so you can review it in Net worth.') }}</p>
            <label class="mt-3 flex cursor-pointer items-center gap-2 pl-6 font-medium text-amber-900">
                <input type="checkbox" x-model="confirmReconciled" class="h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                {{ __('Yes, change a confirmed month') }}
            </label>
            <x-input-error class="mt-2 pl-6" :messages="$errors->get('confirm_reconciled')" />
        </div>

        {{-- Alert --}}
        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3">
            <span>
                <span class="block text-sm font-medium text-slate-900">{{ __('Include Alert') }}</span>
                <span class="block text-xs text-slate-500">{{ __('Shows this movement in the dashboard alerts.') }}</span>
            </span>
            <button type="button" role="switch" :aria-checked="includeAlert" @click="includeAlert = !includeAlert"
                :class="includeAlert ? 'bg-brand-600' : 'bg-slate-200'"
                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition">
                <span :class="includeAlert ? 'translate-x-5' : 'translate-x-0.5'"
                    class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition"></span>
                <span class="sr-only">{{ __('Include Alert') }}</span>
            </button>
        </label>
    </div>

    {{-- Footer: sticky on phones (full-screen sheet) --}}
    <div class="sticky bottom-0 flex gap-2 border-t border-slate-200 bg-white/95 px-5 py-3 backdrop-blur sm:static sm:justify-end sm:bg-transparent sm:px-6 sm:py-4">
        <x-button variant="secondary" class="flex-1 sm:flex-none" x-on:click="{{ $cancel ?? '$dispatch(\'close\')' }}">{{ __('Cancel') }}</x-button>
        <x-primary-button class="flex-1 sm:flex-none" x-bind:disabled="{{ $busy ?? 'false' }} || (reconciledWarning && !confirmReconciled)">
            <i class="fa-solid fa-circle-notch fa-spin" x-show="{{ $busy ?? 'false' }}" x-cloak aria-hidden="true"></i>
            {{ $buttonText ?? __('Save') }}
        </x-primary-button>
    </div>
</div>
