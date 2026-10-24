<div class="space-y-6"
    x-data="{
        description: '{{ old('description', $financialMovement?->description) }}',
        type: '{{ old('type', $financialMovement?->type) }}',
        category_id: '{{ old('category_id', $financialMovement?->category_id) }}',
        baseUrl: '{{ url('/api/financial-movements/latest-type-category') }}',

        async fetchLatestByDescription() {
            if (!this.description) {
                return;
            }

            const params = new URLSearchParams({ search: this.description });
            const response = await fetch(`${this.baseUrl}?${params.toString()}`);
            const data = await response.json();

            this.type = data.type;
            this.category_id = data.category_id;
        }
    }"
>



    <div class="grid grid-cols-2 gap-4 sm:grid-cols-6">
        <div class="col-span-2 sm:col-span-2">
            <x-input-label for="date" :value="__('Date')" />
            <x-text-input id="date" name="date" type="date" class="mt-1 block w-full"
                :value="old('date', $financialMovement?->date)" autocomplete="off" />
            <x-input-error class="mt-2" :messages="$errors->get('date')" />
        </div>

        <div class="col-span-2 sm:col-span-4">
            <x-input-label for="description" :value="__('Description')" />
            <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
                x-model="description" x-on:change="fetchLatestByDescription()"
                autocomplete="off" :placeholder="__('E.g. Supermarket')" />
            <x-input-error class="mt-2" :messages="$errors->get('description')" />
        </div>

        <div class="col-span-1 sm:col-span-2">
            <x-input-label for="amount" :value="__('Amount')" />
            <div class="relative mt-1">
                <x-text-input id="amount" name="amount" type="number" step="0.01" inputmode="decimal" class="block w-full pr-8 tabular-nums"
                    :value="old('amount', $financialMovement->type === 'loan' ? $financialMovement?->amount : abs($financialMovement?->amount))"
                    autocomplete="off" placeholder="0,00" />
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">€</span>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('amount')" />
        </div>

        <div class="col-span-1 sm:col-span-2">
            <x-input-label for="type" :value="__('Type')" />
            <x-dropdown-select :options="$types" x-model="type" name="type" id="type" />
            <x-input-error class="mt-2" :messages="$errors->get('type')" />
        </div>

        <div x-show="type !== 'transfer' && type !== 'loan' && type !== 'adjustment'" class="col-span-2 sm:col-span-2">
            <x-input-label for="category_id" :value="__('Category')" />
            <x-dropdown-select :options="$categories" x-model="category_id" name="category_id" id="category_id" />
            <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
        </div>

        @if (count($wallets) > 1)
            <div class="col-span-2 sm:col-span-3">
                <x-input-label for="wallet_id" :value="__('From Wallet')" />
                <x-dropdown-select :options="$wallets" id="wallet_id"
                    selected="{{ old('wallet_id', $financialMovement?->wallet_id) ?? $walletSection?->id }}"
                    name="wallet_id" />
                <x-input-error class="mt-2" :messages="$errors->get('wallet_id')" />
            </div>
        @else
            <input type="hidden" name="wallet_id" value="{{ key($wallets) }}">
        @endif

        <div x-show="type === 'transfer'" class="col-span-2 sm:col-span-3">
            <x-input-label for="transfer_to_wallet_id" :value="__('To Wallet')" />
            <x-dropdown-select :options="$wallets" id="transfer_to_wallet_id"
                selected="{{ old('transfer_to_wallet_id', $financialMovement?->destinationMovement?->wallet_id) }}"
                name="transfer_to_wallet_id" />
            <x-input-error class="mt-2" :messages="$errors->get('transfer_to_wallet_id')" />
        </div>
    </div>

    <div class="flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-between">
        <label for="include_alert" class="flex cursor-pointer items-center gap-3 text-sm text-slate-700">
            <input type="checkbox" id="include_alert" name="include_alert" value="1"
                @if(old('include_alert', $financialMovement?->include_alert)) checked @endif
                class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
            <span>
                {{ __('Include Alert') }}
                <span class="block text-xs text-slate-500">{{ __('Shows this movement in the dashboard alerts.') }}</span>
            </span>
        </label>
        <x-primary-button class="w-full sm:w-auto">{{ $buttonText ?? __('Save') }}</x-primary-button>
    </div>
</div>
