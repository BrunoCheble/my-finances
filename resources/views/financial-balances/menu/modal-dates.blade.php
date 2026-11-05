@php($datesErrors = $errors->getBag('dates' . $balance->id))
<x-modal name="modal-dates-{{ $balance->id }}" :show="$datesErrors->isNotEmpty()" maxWidth="md" focusable>
    <form method="POST" action="{{ route('financial-balances.dates', $balance) }}" class="space-y-4 p-5 sm:p-6">
        @method('PATCH')
        @csrf
        <h3 class="text-lg font-semibold text-slate-900">{{ __('Edit dates') }} · {{ $balance->wallet_name }}</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="start-date-{{ $balance->id }}" :value="__('Start Date')" />
                <x-text-input id="start-date-{{ $balance->id }}" name="start_date" type="date" required class="mt-1 block w-full"
                    value="{{ $datesErrors->isNotEmpty() ? old('start_date') : $balance->start_date }}" />
            </div>
            <div>
                <x-input-label for="end-date-{{ $balance->id }}" :value="__('End Date')" />
                <x-text-input id="end-date-{{ $balance->id }}" name="end_date" type="date" required class="mt-1 block w-full"
                    value="{{ $datesErrors->isNotEmpty() ? old('end_date') : $balance->end_date }}" />
            </div>
        </div>
        <x-input-error :messages="$datesErrors->all()" />
        <div class="flex justify-end gap-2">
            <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-button type="submit">{{ __('Save') }}</x-button>
        </div>
    </form>
</x-modal>
