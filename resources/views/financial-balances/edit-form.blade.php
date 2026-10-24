<div class="space-y-6">

    <div class="flex justify-between gap-4">
        <div class="flex-1">
            <x-input-label for="real_balance" :value="__('Real Balance')" />
            <x-text-input style="width: 100%" type="text" name="real_balance" :value="old('real_balance', $balance?->calculated_balance ?? 0)" />
            <x-input-error class="mt-2" :messages="$errors->get('real_balance')" />
            <p class="mt-2 text-sm text-gray-500">
                Saldo atual calculado: {{ format_currency($balance?->calculated_balance ?? 0) }}.
                A diferença será lançada como um movimento de ajuste no dia {{ date_format_custom($balance?->start_date) }}.
            </p>
        </div>
    </div>
    <div class="flex items-center gap-4">
        <x-primary-button><?= __('Submit') ?></x-primary-button>
    </div>
</div>
