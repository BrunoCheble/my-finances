<div class="space-y-6">


    <div class="flex gap-4">

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
    </div>

    <div class="flex justify-between gap-4">
        <div class="flex-1">
            <x-input-label for="initial_balance" :value="__('Initial Balance')" />
            <x-text-input style="width: 100%" type="text" name="initial_balance" :value="old('initial_balance', $balance?->initial_balance)" />
            <x-input-error class="mt-2" :messages="$errors->get('initial_balance')" />
        </div>

        <div class="flex-1">
            <x-input-label for="total_expense" :value="__('Total Expense')" />
            <x-text-input style="width: 100%" type="text" name="total_expense" :value="old('total_expense', $balance?->total_expense)" />
            <x-input-error class="mt-2" :messages="$errors->get('total_expense')" />
        </div>

        <div class="flex-1">
            <x-input-label for="total_income" :value="__('Total Income')" />
            <x-text-input style="width: 100%" type="text" name="total_income" :value="old('total_income', $balance?->total_income)" />
            <x-input-error class="mt-2" :messages="$errors->get('total_income')" />
        </div>
    </div>

    <div class="flex justify-between gap-4">

        <div class="flex-1">
            <x-input-label for="total_unidentified" :value="__('Total Unidentified')" />
            <x-text-input style="width: 100%" type="text" name="total_unidentified" :value="old('total_unidentified', $balance?->total_unidentified)" />
            <x-input-error class="mt-2" :messages="$errors->get('total_unidentified')" />
        </div>

        <div class="flex-1">
            <x-input-label for="calculated_balance" :value="__('Calculated Balance')" />
            <x-text-input style="width: 100%" type="text" name="calculated_balance" :value="old('calculated_balance', $balance?->calculated_balance)" />
            <x-input-error class="mt-2" :messages="$errors->get('calculated_balance')" />
        </div>

        <div class="flex-1">
            <x-input-label for="real_balance" :value="__('Real Balance')" />
            <x-text-input style="width: 100%" type="text" name="real_balance" :value="old('real_balance', $balance?->real_balance)" />
            <x-input-error class="mt-2" :messages="$errors->get('real_balance')" />
        </div>
    </div>




    <div class="flex items-center gap-4">
        <x-primary-button>Submit</x-primary-button>
    </div>
</div>
