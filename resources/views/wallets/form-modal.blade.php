{{-- Create / edit a wallet. $wallet is null for a new one. --}}
@php
    $isNew = !$wallet;
    $modal = $isNew ? 'wallet-new' : 'wallet-edit-' . $wallet->id;
    $useOld = old('_modal') === $modal;
    $value = fn ($field, $default) => $useOld ? old($field, $default) : $default;
    $palette = ['#2a78d6', '#1baf7a', '#eb6834', '#6250d6', '#e87ba4', '#eda100', '#e34948', '#008300', '#475569', '#9333ea'];
    $types = [
        \App\Models\Wallet::TYPE_DEBIT => ['icon' => 'building-columns', 'label' => __('Debit'), 'hint' => __('Current or savings account. A negative balance raises an alert.')],
        \App\Models\Wallet::TYPE_CREDIT => ['icon' => 'credit-card', 'label' => __('Credit'), 'hint' => __('Credit card: a negative balance is normal.')],
        \App\Models\Wallet::TYPE_THIRD_PARTY => ['icon' => 'user-shield', 'label' => __('Third-party money'), 'hint' => __('Money of someone else kept in your banks; it is not part of your net worth.')],
    ];
    $reserved = $wallet && (float) $wallet->reserved_amount != 0 ? number_format(abs($wallet->reserved_amount), 2, '.', '') : '';
@endphp

<x-modal :name="$modal" maxWidth="md">
    <form method="POST" action="{{ $isNew ? route('wallets.store') : route('wallets.update', $wallet) }}" class="space-y-5 p-5 sm:p-6"
        x-data="{ color: @js($value('color', $wallet?->color ?? $palette[0])), type: @js($value('type', $wallet?->type ?? 'debit')), active: @js((bool) $value('active', $wallet?->active ?? true)) }">
        @csrf
        @unless ($isNew) @method('PATCH') @endunless
        <input type="hidden" name="_modal" value="{{ $modal }}">
        <input type="hidden" name="color" :value="color">
        <input type="hidden" name="type" :value="type">
        <input type="hidden" name="active" :value="active ? 1 : 0">

        <div class="flex items-start justify-between gap-4">
            <h3 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                <span class="h-3 w-3 rounded-full" :style="`background-color: ${color}`" aria-hidden="true"></span>
                {{ $isNew ? __('New wallet') : __('Edit wallet') }}
            </h3>
            <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click="$dispatch('close')" />
        </div>

        <div>
            <x-input-label for="{{ $modal }}-name" :value="__('Name')" />
            <x-text-input id="{{ $modal }}-name" name="name" class="mt-1 block w-full" required maxlength="255"
                :value="$value('name', $wallet?->name ?? '')" :placeholder="__('E.g. Main bank account')" />
            @if ($useOld)<x-input-error class="mt-2" :messages="$errors->get('name')" />@endif
        </div>

        <fieldset>
            <legend class="label">{{ __('Color') }}</legend>
            <div class="mt-2 flex flex-wrap items-center gap-2" role="radiogroup">
                @foreach ($palette as $swatch)
                    <button type="button" role="radio" :aria-checked="color === '{{ $swatch }}'" @click="color = '{{ $swatch }}'"
                        class="h-8 w-8 rounded-full ring-offset-2 transition" style="background-color: {{ $swatch }}"
                        :class="color === '{{ $swatch }}' ? 'ring-2 ring-slate-900' : 'hover:scale-110'">
                        <span class="sr-only">{{ $swatch }}</span>
                    </button>
                @endforeach
                <label class="relative h-8 w-8 cursor-pointer overflow-hidden rounded-full border border-dashed border-slate-300" title="{{ __('Other color') }}">
                    <input type="color" x-model="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" aria-label="{{ __('Other color') }}">
                    <i class="fa-solid fa-plus absolute inset-0 m-auto flex items-center justify-center text-xs text-slate-400" aria-hidden="true"></i>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend class="label">{{ __('Type') }}</legend>
            <div class="mt-2 space-y-2" role="radiogroup">
                @foreach ($types as $key => $option)
                    <button type="button" role="radio" :aria-checked="type === '{{ $key }}'" @click="type = '{{ $key }}'"
                        :class="type === '{{ $key }}' ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-slate-200 hover:border-slate-300'"
                        class="flex w-full items-start gap-3 rounded-xl border px-3 py-2.5 text-left">
                        <i class="fa-solid fa-{{ $option['icon'] }} mt-0.5 w-4 text-slate-500" aria-hidden="true"></i>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">{{ $option['label'] }}</span>
                            <span class="block text-xs text-slate-500">{{ $option['hint'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </fieldset>

        <div x-show="type !== 'third_party'">
            <x-input-label for="{{ $modal }}-reserved" :value="__('Held amount (valor cativo)')" />
            <div class="relative mt-1">
                <x-text-input id="{{ $modal }}-reserved" name="reserved_amount" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full pr-8 tabular-nums" :value="$value('reserved_amount', $reserved)" placeholder="0,00" />
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">€</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Pre-authorized amounts the bank is holding. Usually updated by the statement import.') }}</p>
        </div>

        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3">
            <span>
                <span class="block text-sm font-medium text-slate-900">{{ __('Is active') }}</span>
                <span class="block text-xs text-slate-500">{{ __('Inactive wallets keep their history but leave the forms.') }}</span>
            </span>
            <button type="button" role="switch" :aria-checked="active" @click="active = !active"
                :class="active ? 'bg-brand-600' : 'bg-slate-200'" class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition">
                <span :class="active ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition"></span>
                <span class="sr-only">{{ __('Is active') }}</span>
            </button>
        </label>

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-primary-button>{{ $isNew ? __('Create wallet') : __('Save') }}</x-primary-button>
        </div>
    </form>
</x-modal>
