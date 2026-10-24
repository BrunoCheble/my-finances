@php
    $monthLabel = ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $month . '-01')->translatedFormat('F Y'));
    $reopen = old('_modal') ?? $openModal;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Financial Categories')" :subtitle="__('Organize your movements and set a monthly forecast for each category.')">
            <x-slot name="actions">
                <x-button variant="secondary" icon="file-import" x-data x-on:click="$dispatch('open-modal', 'category-import')">{{ __('Import') }}</x-button>
                <x-button icon="plus" x-data x-on:click="$dispatch('open-modal', 'category-new')">{{ __('New category') }}</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4 px-4 py-6 sm:px-6 lg:px-8"
        x-data="{ tab: 'expense', search: '' }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-modal', @js($reopen))) @endif">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" role="tablist">
                @foreach (['expense' => __('Total expenses'), 'income' => __('Total income')] as $type => $label)
                    <button type="button" role="tab" :aria-selected="tab === '{{ $type }}'" @click="tab = '{{ $type }}'"
                        :class="tab === '{{ $type }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-md px-3 py-1.5">
                        {{ $label }} <span class="ml-1 text-xs text-slate-400">{{ $rows[$type]->count() }}</span>
                    </button>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <div class="relative flex-1 sm:w-56 sm:flex-none">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                    <input type="search" x-model="search" placeholder="{{ __('Search') }}" aria-label="{{ __('Search') }}" class="input block w-full pl-8">
                </div>
                <label for="month" class="sr-only">{{ __('Month') }}</label>
                <input id="month" type="month" value="{{ $month }}" class="input w-40"
                    onchange="window.location.href='{{ route('financial-categories.index') }}?month=' + this.value">
            </div>
        </div>

        @foreach ($rows as $type => $list)
            <section x-show="tab === '{{ $type }}'" @if ($type !== 'expense') x-cloak @endif class="card overflow-hidden">
                @if ($list->isEmpty())
                    <x-empty-state icon="tags" :title="__('No categories yet')" :description="__('Create categories to organize your movements.')" />
                @else
                    <div class="hidden grid-cols-12 gap-4 border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid">
                        <span class="col-span-4">{{ __('Category') }}</span>
                        <span class="col-span-4">{{ $monthLabel }} · {{ __('vs forecast') }}</span>
                        <span class="col-span-2 text-right">{{ __('Average') }}</span>
                        <span class="col-span-2"><span class="sr-only">{{ __('Actions') }}</span></span>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($list as $row)
                            @php
                                $ratio = $row->forecast ? $row->month / $row->forecast : null;
                                $over = $type === 'expense' && $ratio !== null && $ratio > 1;
                            @endphp
                            <li x-show="!search || @js(mb_strtolower($row->name)).includes(search.toLowerCase())"
                                @class(['grid grid-cols-12 items-center gap-x-4 gap-y-2 px-4 py-3', 'opacity-60' => !$row->active])>
                                <div class="col-span-9 min-w-0 md:col-span-4">
                                    <p class="flex items-center gap-2 truncate font-medium text-slate-900">
                                        {{ $row->name }}
                                        @unless ($row->active)<x-badge>{{ __('Inactive category') }}</x-badge>@endunless
                                    </p>
                                    <p class="text-xs text-slate-500">{{ trans_choice(':count movement|:count movements', $row->movements, ['count' => $row->movements]) }}</p>
                                </div>

                                <div class="col-span-12 order-last md:order-none md:col-span-4">
                                    <div class="flex items-baseline justify-between gap-2 text-sm">
                                        <span @class(['amount font-semibold', 'text-rose-600' => $over, 'text-slate-900' => !$over])>
                                            @if ($over)<i class="fa-solid fa-triangle-exclamation text-xs" aria-hidden="true"></i>@endif
                                            {{ format_currency($row->month) }}
                                        </span>
                                        <span class="text-xs text-slate-500">
                                            @if ($row->forecast) {{ __('of :amount', ['amount' => format_currency($row->forecast)]) }} @else {{ __('no forecast') }} @endif
                                        </span>
                                    </div>
                                    @if ($row->forecast)
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ round(($ratio ?? 0) * 100) }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Forecast used') }}">
                                            <div @class(['h-full rounded-full', 'bg-rose-500' => $over, 'bg-amber-400' => !$over && $ratio >= 0.8 && $type === 'expense', 'bg-brand-500' => !$over && ($ratio < 0.8 || $type === 'income')])
                                                style="width: {{ min(100, round(($ratio ?? 0) * 100)) }}%"></div>
                                        </div>
                                    @endif
                                </div>

                                <div class="hidden text-right text-sm text-slate-500 md:col-span-2 md:block">
                                    {{ $row->average ? format_currency($row->average) : '—' }}
                                </div>

                                <div class="col-span-3 flex justify-end gap-1 md:col-span-2">
                                    <x-icon-button icon="pen" :label="__('Edit')" x-on:click="$dispatch('open-modal', 'category-edit-{{ $row->id }}')" />
                                    <x-icon-button icon="trash" tone="danger" :label="__('Delete')" x-on:click="$dispatch('open-modal', 'category-delete-{{ $row->id }}')" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>

    {{-- modals --}}
    @include('financial-categories.form-modal', ['category' => null])
    @foreach ($categories as $category)
        @include('financial-categories.form-modal', ['category' => $category])

        @php
            $movementCount = collect($rows)->flatten(1)->firstWhere('id', $category->id)?->movements ?? 0;
        @endphp
        <x-modal name="category-delete-{{ $category->id }}" maxWidth="md">
            <form method="POST" action="{{ route('financial-categories.destroy', $category) }}" class="space-y-4 p-5 sm:p-6">
                @csrf
                @method('DELETE')
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Delete :category?', ['category' => $category->name]) }}</h3>
                @if ($movementCount > 0)
                    <p class="text-sm text-slate-600">{{ trans_choice('It has :count movement. Choose a category to move it to:|It has :count movements. Choose a category to move them to:', $movementCount, ['count' => $movementCount]) }}</p>
                    <select name="move_to" required class="input block w-full" aria-label="{{ __('Move movements to') }}">
                        <option value="">{{ __('Select category') }}</option>
                        @foreach ($categories->where('id', '!=', $category->id)->sortBy('name') as $other)
                            <option value="{{ $other->id }}">{{ $other->name }}{{ $other->type !== $category->type ? ' (' . __($other->type === 'income' ? 'Income' : 'Expense') . ')' : '' }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">{{ __('Useful to merge duplicated categories. Or just deactivate it to keep the history.') }}</p>
                @else
                    <p class="text-sm text-slate-600">{{ __('This category has no movements.') }}</p>
                @endif
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                    <x-danger-button>{{ $movementCount > 0 ? __('Move and delete') : __('Delete') }}</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endforeach

    <x-modal name="category-import" maxWidth="md">
        <form method="POST" action="{{ route('financial-categories.import') }}" enctype="multipart/form-data" class="space-y-4 p-5 sm:p-6">
            @csrf
            <div class="flex items-start justify-between gap-4">
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Import categories') }}</h3>
                <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click="$dispatch('close')" />
            </div>
            <p class="text-sm text-slate-600">{{ __('A CSV file with one category per line: type (expense or income), name. Existing names are skipped.') }}</p>
            <pre class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">expense,Supermercado
expense,Transporte
income,Salário</pre>
            <input type="file" name="file" accept=".csv,text/csv,text/plain" required
                class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
            <x-input-error :messages="$errors->get('file')" />
            <div class="flex justify-end gap-2">
                <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                <x-primary-button>{{ __('Import') }}</x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
