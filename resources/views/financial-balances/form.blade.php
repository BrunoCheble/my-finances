<div class="space-y-6">

    <div>
        @if (count($wallets) > 1)
            <div class="flex-1">
                <x-input-label for="wallet_id" :value="__('Wallets')" />
                <x-dropdown-select
                    style="width: 100%"
                    :options="$wallets"
                    :selected="old('wallet_id', $balance?->wallet_id ?? [])"
                    name="wallets[]"
                    multiple />
                <x-input-error class="mt-2" :messages="$errors->get('wallet_id')" />
            </div>
        @else
            <input type="hidden" name="wallets[]" value="{{ old('wallet_id', key($wallets)) }}">
        @endif
    </div>

    <div class="flex justify-between items-center gap-4">

        <div class="flex-1">
            <x-input-label for="start_date" :value="__('Start Date')" />
            <x-text-input style="width: 100%" id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', $balance?->start_date)"
                autocomplete="start_date" />
            <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
        </div>

        <div class="flex-1">
            <x-input-label for="end_date" :value="__('End Date')" />
            <x-text-input style="width: 100%" id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date', $balance?->end_date)"
                autocomplete="end_date" />
            <x-input-error class="mt-2" :messages="$errors->get('end_date')" />
        </div>

        <div class="flex-1">
            <x-input-label for="initial_balance" :value="__('Initial Balance')" />
            <x-text-input style="width: 100%" type="text" name="initial_balance" :value="old('initial_balance', $balance?->initial_balance ?? 0)" />
            <x-input-error class="mt-2" :messages="$errors->get('initial_balance')" />
        </div>
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>Submit</x-primary-button>
    </div>
</div>
