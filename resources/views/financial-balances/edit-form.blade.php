<div class="space-y-6">

    <div class="flex justify-between gap-4">
        <div class="flex-1">
            <x-input-label for="initial_balance" :value="__('Initial Balance')" />
            <x-text-input style="width: 100%" type="text" name="initial_balance" :value="old('initial_balance', $balance?->initial_balance ?? 0)" />
            <x-input-error class="mt-2" :messages="$errors->get('initial_balance')" />
        </div>
        @if ($balance?->total_unidentified === 0 && $balance->total_income === 0 && $balance->total_expense === 0)
            <div class="flex-1">
                <x-input-label for="calculated_balance" :value="__('Calculated Balance')" />
                <x-text-input style="width: 100%;" type="text" name="calculated_balance" :value="old('calculated_balance', $balance?->calculated_balance ?? 0)" />
                <x-input-error class="mt-2" :messages="$errors->get('calculated_balance')" />
            </div>
        @endif
    </div>
    <div class="flex items-center gap-4">
        <x-primary-button>Submit</x-primary-button>
    </div>
</div>
