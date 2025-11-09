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



    <div class="flex flex-row gap-4">
        <!-- Date Field -->
        <div>
            <x-input-label for="date" :value="__('Date')" />
            <x-text-input id="date" name="date" type="date" class="mt-1 block w-full"
                :value="old('date', $financialMovement?->date)"
                autocomplete="date" />
            <x-input-error class="mt-2" :messages="$errors->get('date')" />
        </div>

        <!-- Description Field -->
        <div class="flex-1">
            <x-input-label for="description" :value="__('Description')" />
            <x-text-input
                id="description"
                name="description"
                type="text"
                class="mt-1 block w-full"
                x-model="description"
                x-on:change="fetchLatestByDescription()"
                autocomplete="description"
                placeholder="Description"
            />
            <x-input-error class="mt-2" :messages="$errors->get('description')" />
        </div>

        <!-- Amount Field -->
        <div>
            <x-input-label for="amount" :value="__('Amount')" />
            <x-text-input id="amount" name="amount" type="number" step="0.01" class="mt-1 block w-full"
                :value="old('amount', $financialMovement->type === 'transfer' ? $financialMovement?->amount : abs($financialMovement?->amount))" autocomplete="amount" placeholder="Amount" />
            <x-input-error class="mt-2" :messages="$errors->get('amount')" />
        </div>
    </div>

    <div class="flex flex-row gap-4">
        <!-- Type Field -->
        <div class="flex-1">
            <x-input-label for="type" :value="__('Type')" />
            <x-dropdown-select :options="$types" x-model="type" name="type" />
            <x-input-error class="mt-2" :messages="$errors->get('type')" />
        </div>

        <!-- Category Field -->
        <div class="flex-1">
            <x-input-label for="category_id" :value="__('Category')" />
            <x-dropdown-select :options="$categories" x-model="category_id" name="category_id" />
            <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
        </div>

        @if (count($wallets) > 1)
            <div>
                <x-input-label for="wallet_id" :value="__('Wallet')" />
                <x-dropdown-select :options="$wallets"
                    selected="{{ old('wallet_id', $financialMovement?->wallet_id) ?? $walletSection?->id }}"
                    name="wallet_id" />
                <x-input-error class="mt-2" :messages="$errors->get('wallet_id')" />
            </div>
        @else
            <input type="hidden" name="wallet_id" value="{{ key($wallets) }}">
        @endif
    </div>

    <div x-show="type === 'transfer'" class="transition-all duration-200">
        <x-input-label for="transfer_to_wallet_id" :value="__('Transfer To Wallet')" />
        <x-dropdown-select :options="$wallets"
            selected="{{ old('transfer_to_wallet_id', $financialMovement?->destinationMovement?->wallet_id) }}"
            name="transfer_to_wallet_id" />
        <x-input-error class="mt-2" :messages="$errors->get('transfer_to_wallet_id')" />
    </div>

    <!-- Include Alert using toggle checkbox -->
    <div class="flex items-center gap-4">
        <x-input-label for="include_alert" :value="__('Include Alert')" />
        <input
            type="checkbox"
            id="include_alert"
            name="include_alert"
            value="1"
            @if(old('include_alert', $financialMovement?->include_alert)) checked @endif
            class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
        />
        <x-input-error class="mt-2" :messages="$errors->get('include_alert')" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $buttonText ?? 'Submit' }}</x-primary-button>
    </div>
</div>
