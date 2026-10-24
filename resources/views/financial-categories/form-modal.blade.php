{{-- Create / edit a category. $category is null for a new one. --}}
@php
    $isNew = !$category;
    $modal = $isNew ? 'category-new' : 'category-edit-' . $category->id;
    $useOld = old('_modal') === $modal;
    $value = fn ($field, $default) => $useOld ? old($field, $default) : $default;
    $type = $value('type', $category?->type ?? ($defaultType ?? 'expense'));
    $forecast = $value('expected_total', $category && (float) $category->expected_total != 0 ? number_format(abs($category->expected_total), 2, '.', '') : '');
    $active = (bool) $value('active', $category?->active ?? true);
@endphp

<x-modal :name="$modal" maxWidth="md">
    <form method="POST" action="{{ $isNew ? route('financial-categories.store') : route('financial-categories.update', $category) }}"
        class="space-y-5 p-5 sm:p-6" x-data="{ type: @js($type), active: @js($active) }">
        @csrf
        @unless ($isNew) @method('PATCH') @endunless
        <input type="hidden" name="_modal" value="{{ $modal }}">
        <input type="hidden" name="type" :value="type">
        <input type="hidden" name="active" :value="active ? 1 : 0">

        <div class="flex items-start justify-between gap-4">
            <h3 class="text-lg font-semibold text-slate-900">{{ $isNew ? __('New category') : __('Edit category') }}</h3>
            <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click="$dispatch('close')" />
        </div>

        <div role="radiogroup" aria-label="{{ __('Type') }}" class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1">
            <button type="button" role="radio" :aria-checked="type === 'expense'" @click="type = 'expense'"
                :class="type === 'expense' ? 'bg-white text-rose-600 shadow-sm' : 'text-slate-600'" class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold">
                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>{{ __('Expense') }}
            </button>
            <button type="button" role="radio" :aria-checked="type === 'income'" @click="type = 'income'"
                :class="type === 'income' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-600'" class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold">
                <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>{{ __('Income') }}
            </button>
        </div>

        <div>
            <x-input-label for="{{ $modal }}-name" :value="__('Name')" />
            <x-text-input id="{{ $modal }}-name" name="name" class="mt-1 block w-full" required maxlength="255"
                :value="$value('name', $category?->name ?? '')" :placeholder="__('E.g. Supermarket')" />
            @if ($useOld)<x-input-error class="mt-2" :messages="$errors->get('name')" />@endif
        </div>

        <div>
            <x-input-label for="{{ $modal }}-forecast" :value="__('Monthly forecast')" />
            <div class="relative mt-1">
                <x-text-input id="{{ $modal }}-forecast" name="expected_total" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full pr-8 tabular-nums" :value="$forecast" placeholder="0,00" />
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">€</span>
            </div>
            <p class="mt-1 text-xs text-slate-500" x-text="type === 'expense' ? @js(__('How much you plan to spend per month. Alerts warn at 80% and 100%.')) : @js(__('How much you expect to receive per month.'))"></p>
            @if ($useOld)<x-input-error class="mt-2" :messages="$errors->get('expected_total')" />@endif
        </div>

        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3">
            <span>
                <span class="block text-sm font-medium text-slate-900">{{ __('Is active') }}</span>
                <span class="block text-xs text-slate-500">{{ __('Inactive categories stay in the history but are not suggested anymore.') }}</span>
            </span>
            <button type="button" role="switch" :aria-checked="active" @click="active = !active"
                :class="active ? 'bg-brand-600' : 'bg-slate-200'" class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition">
                <span :class="active ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition"></span>
                <span class="sr-only">{{ __('Is active') }}</span>
            </button>
        </label>

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-primary-button>{{ $isNew ? __('Create category') : __('Save') }}</x-primary-button>
        </div>
    </form>
</x-modal>
