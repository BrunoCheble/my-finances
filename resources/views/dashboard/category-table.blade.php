{{--
    Categories x months: expenses and income in separate groups, last N months up to the
    selected one, average of the complete months and editable forecasts (always positive here).
--}}
@php
    $months = $categoryTable['months'];
    $selectedKey = $categoryTable['selected'];
    $groupLabels = ['expense' => __('Total expenses'), 'income' => __('Total income')];
    $hidden = collect($categoryTable['groups'])->flatten(1)->where('empty', true)->count();
@endphp

<section class="card" aria-labelledby="categories-title" x-data="categoryMonths()">
    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 id="categories-title" class="text-base font-semibold text-slate-900">{{ __('Categories by month') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Click a value to see its movements. Values above the forecast are marked in red.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <nav class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" aria-label="{{ __('Period') }}">
                @foreach (\App\Services\CategoryMonthlyService::RANGES as $option)
                    <a href="{{ route('dashboard', ['month' => $selectedKey, 'range' => $option]) }}#categories-title"
                        @class(['rounded-md px-3 py-1.5', 'bg-white text-slate-900 shadow-sm' => $range === $option, 'text-slate-600 hover:text-slate-900' => $range !== $option])
                        @if ($range === $option) aria-current="page" @endif>{{ __(':count months', ['count' => $option]) }}</a>
                @endforeach
            </nav>
            <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" role="tablist">
                <button type="button" role="tab" :aria-selected="view === 'amount'" @click="view = 'amount'"
                    :class="view === 'amount' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-md px-3 py-1.5">€</button>
                <button type="button" role="tab" :aria-selected="view === 'share'" @click="view = 'share'"
                    :class="view === 'share' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-md px-3 py-1.5">{{ __('% of total') }}</button>
            </div>
        </div>
    </div>

    {{-- Desktop / tablet: table --}}
    <div class="relative hidden overflow-x-auto md:block" x-ref="scroller" x-init="$nextTick(() => $refs.scroller.scrollLeft = $refs.scroller.scrollWidth)">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="th sticky left-0 z-10 min-w-[12rem] bg-slate-50">{{ __('Category') }}</th>
                    @foreach ($months as $m)
                        <th scope="col" @class(['th whitespace-nowrap text-right', 'bg-brand-50 text-brand-700' => $m['key'] === $selectedKey])>{{ $m['label'] }}</th>
                    @endforeach
                    <th scope="col" class="th whitespace-nowrap border-l border-slate-200 text-right">{{ __('Average') }}</th>
                    <th scope="col" class="th whitespace-nowrap text-right">{{ __('Forecast') }}</th>
                </tr>
            </thead>
            @foreach ($categoryTable['groups'] as $type => $rows)
                <tbody class="divide-y divide-slate-100 border-t border-slate-200">
                    <tr class="bg-white">
                        <th scope="colgroup" colspan="{{ count($months) + 3 }}" class="sticky left-0 bg-white px-3 pb-1 pt-4 text-left text-xs font-semibold uppercase tracking-wide {{ $type === 'expense' ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $type === 'expense' ? __('Total expenses') : __('Total income') }}
                        </th>
                    </tr>
                    @foreach ($rows as $row)
                        <tr class="group hover:bg-slate-50" @if ($row->empty) x-show="showEmpty" x-cloak @endif>
                            <th scope="row" class="td sticky left-0 bg-white text-left font-medium text-slate-800 group-hover:bg-slate-50">{{ $row->name }}</th>
                            @foreach ($months as $m)
                                @php
                                    $value = $row->values[$m['key']];
                                    $over = $type === 'expense' && $row->forecast && $value > $row->forecast;
                                    $share = $categoryTable['totals'][$type][$m['key']] > 0 ? round($value / $categoryTable['totals'][$type][$m['key']] * 100) : 0;
                                @endphp
                                <td @class(['td whitespace-nowrap text-right tabular-nums', 'bg-brand-50/40' => $m['key'] === $selectedKey])>
                                    @if ($value != 0)
                                        <button type="button" class="rounded px-1 hover:bg-white {{ $over ? 'font-semibold text-rose-600' : 'text-slate-700' }}"
                                            @click="openMonth('{{ $m['filter'] }}', '{{ $row->id }}', @js($row->name))"
                                            @if ($over) title="{{ __('Above forecast') }}" @endif>
                                            @if ($over)<i class="fa-solid fa-triangle-exclamation mr-1 text-[10px]" aria-hidden="true"></i><span class="sr-only">{{ __('Above forecast') }}:</span>@endif
                                            <span x-show="view === 'amount'">{{ format_currency($value) }}</span>
                                            <span x-show="view === 'share'" x-cloak>{{ $share }}%</span>
                                        </button>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="td whitespace-nowrap border-l border-slate-200 text-right tabular-nums text-slate-500">
                                {{ $row->average !== null && $row->average != 0 ? format_currency($row->average) : '—' }}
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($row->average)
                                        <button type="button" class="btn-icon h-7 w-7 text-xs" title="{{ __('Use average') }}"
                                            @click="useAverage({{ $row->id }}, {{ $row->average }})">
                                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                            <span class="sr-only">{{ __('Use average') }} {{ $row->name }}</span>
                                        </button>
                                    @endif
                                    <input type="number" step="0.01" min="0" inputmode="decimal" placeholder="—"
                                        id="forecast-{{ $row->id }}" data-forecast data-category="{{ $row->id }}" data-type="{{ $type }}"
                                        aria-label="{{ __('Forecast') }} {{ $row->name }}" value="{{ $row->forecast !== null ? number_format($row->forecast, 2, '.', '') : '' }}"
                                        @input="dirty = true" class="input w-28 py-1 text-right tabular-nums">
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50 font-semibold">
                        <th scope="row" class="td sticky left-0 bg-slate-50 text-left">{{ $type === 'expense' ? __('Total expenses') : __('Total income') }}</th>
                        @foreach ($months as $m)
                            <td @class(['td whitespace-nowrap text-right tabular-nums', 'bg-brand-50' => $m['key'] === $selectedKey])>
                                @if ($m['key'] < $categoryTable['firstMonth'])<span class="font-normal text-slate-300">—</span>@else{{ format_currency($categoryTable['totals'][$type][$m['key']]) }}@endif
                            </td>
                        @endforeach
                        <td class="td border-l border-slate-200" colspan="2"></td>
                    </tr>
                </tbody>
            @endforeach
            <tfoot class="border-t-2 border-slate-200">
                <tr class="font-semibold">
                    <th scope="row" class="td sticky left-0 bg-white text-left">{{ __('Result') }}</th>
                    @foreach ($months as $m)
                        <td @class(['td whitespace-nowrap text-right', 'bg-brand-50/40' => $m['key'] === $selectedKey])>
                            @if ($m['key'] < $categoryTable['firstMonth'])<span class="font-normal text-slate-300">—</span>@else<x-money :value="$categoryTable['result'][$m['key']]" />@endif
                        </td>
                    @endforeach
                    <td class="td border-l border-slate-200" colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Phones: one card per category with a mini bar chart of the months --}}
    <div class="divide-y divide-slate-100 md:hidden">
        @foreach ($categoryTable['groups'] as $type => $rows)
            <h3 class="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide {{ $type === 'expense' ? 'text-rose-600' : 'text-emerald-600' }}">
                {{ $type === 'expense' ? __('Total expenses') : __('Total income') }}
                · {{ format_currency($categoryTable['totals'][$type][$selectedKey]) }}
            </h3>
            @foreach ($rows as $row)
                @php
                    $current = $row->values[$selectedKey];
                    $max = max(max($row->values), $row->forecast ?? 0) ?: 1;
                    $over = $type === 'expense' && $row->forecast && $current > $row->forecast;
                @endphp
                <div class="px-4 py-3" @if ($row->empty) x-show="showEmpty" x-cloak @endif>
                    <div class="flex items-baseline justify-between gap-3">
                        <button type="button" class="min-w-0 truncate text-left text-sm font-medium text-slate-800"
                            @click="openMonth('{{ \Carbon\Carbon::createFromFormat('Y-m', $selectedKey)->format('m/Y') }}', '{{ $row->id }}', @js($row->name))">{{ $row->name }}</button>
                        <span class="amount text-sm font-semibold {{ $over ? 'text-rose-600' : 'text-slate-900' }}">
                            @if ($over)<i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>@endif
                            {{ format_currency($current) }}
                        </span>
                    </div>
                    <div class="mt-2 flex h-8 items-end gap-1" aria-hidden="true">
                        @foreach ($months as $m)
                            <div @class(['flex-1 rounded-sm', 'bg-brand-500' => $m['key'] === $selectedKey, 'bg-slate-200' => $m['key'] !== $selectedKey])
                                style="height: {{ max(2, round($row->values[$m['key']] / $max * 100)) }}%"></div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-2 text-xs text-slate-500">
                        <span>{{ __('Average') }} {{ $row->average ? format_currency($row->average) : '—' }}</span>
                        <label class="flex items-center gap-1.5">
                            {{ __('Forecast') }}
                            <input type="number" step="0.01" min="0" inputmode="decimal" placeholder="—"
                                data-forecast data-category="{{ $row->id }}" data-type="{{ $type }}" data-mobile
                                value="{{ $row->forecast !== null ? number_format($row->forecast, 2, '.', '') : '' }}"
                                @input="dirty = true; syncForecast({{ $row->id }}, $event.target.value)" class="input w-24 py-1 text-right text-xs tabular-nums">
                        </label>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        @if ($hidden)
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" x-model="showEmpty" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ trans_choice('Show :count category without movements|Show :count categories without movements', $hidden, ['count' => $hidden]) }}
            </label>
        @else
            <span></span>
        @endif
        <div class="flex items-center justify-end gap-3">
            <span x-show="dirty" x-cloak class="text-sm text-amber-700">{{ __('Unsaved forecast changes') }}</span>
            <x-button icon="floppy-disk" x-on:click="saveForecasts" x-bind:disabled="saving || !dirty">{{ __('Save forecasts') }}</x-button>
        </div>
    </div>

    {{-- movements of a category in a month --}}
    <x-modal name="modal-category-month" maxWidth="xl">
        <div class="p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900" x-text="detail.category"></h3>
                    <p class="text-sm text-slate-500" x-text="detail.month"></p>
                </div>
                <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click="$dispatch('close')" />
            </div>
            <p x-show="detail.loading" class="py-8 text-center text-sm text-slate-500">{{ __('Loading...') }}</p>
            <ul x-show="!detail.loading" class="mt-4 max-h-96 divide-y divide-slate-100 overflow-y-auto">
                <template x-for="row in detail.rows" :key="row.id">
                    <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800" x-text="row.description"></p>
                            <p class="text-xs text-slate-500" x-text="row.date"></p>
                        </div>
                        <span class="amount font-semibold" :class="row.amount < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="row.formatted"></span>
                    </li>
                </template>
                <li x-show="!detail.rows.length" class="py-6 text-center text-sm text-slate-500">{{ __('No movements found') }}</li>
            </ul>
            <div x-show="!detail.loading && detail.rows.length" class="mt-3 flex justify-between border-t border-slate-200 pt-3 text-sm font-semibold">
                <span>{{ __('Total') }}</span>
                <span class="amount" :class="detail.total < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="money(detail.total)"></span>
            </div>
        </div>
    </x-modal>
</section>

<script>
    function categoryMonths() {
        return {
            view: 'amount',
            showEmpty: false,
            dirty: false,
            saving: false,
            detail: { category: '', month: '', rows: [], total: 0, loading: false },
            money(value) {
                return Number(value).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
            },
            useAverage(categoryId, average) {
                this.syncForecast(categoryId, Number(average).toFixed(2));
                this.dirty = true;
            },
            // the same forecast exists in the table and in the phone cards
            syncForecast(categoryId, value) {
                document.querySelectorAll(`input[data-forecast][data-category="${categoryId}"]`).forEach((input) => input.value = value);
            },
            async openMonth(monthYear, categoryId, categoryName) {
                const [month, year] = monthYear.split('/');
                const last = new Date(year, month, 0).getDate();
                this.detail = { category: categoryName, month: monthYear, rows: [], total: 0, loading: true };
                this.$dispatch('open-modal', 'modal-category-month');

                const params = new URLSearchParams({
                    attribute: 'category_id', search: categoryId,
                    date_from: `${year}-${month}-01`, date_to: `${year}-${month}-${String(last).padStart(2, '0')}`,
                    sort: 'date', order: 'asc',
                });
                const data = await (await fetch(@json(route('financial-movements.filter')) + '?' + params)).json();
                this.detail.rows = data.map((item) => ({
                    id: item.id,
                    description: item.description,
                    date: new Date(item.date).toLocaleDateString('pt-PT'),
                    amount: parseFloat(item.amount),
                    formatted: this.money(item.amount),
                }));
                this.detail.total = this.detail.rows.reduce((sum, row) => sum + row.amount, 0);
                this.detail.loading = false;
            },
            async saveForecasts() {
                // forecasts are typed as positive amounts; expenses are stored as negative
                const payload = {};
                document.querySelectorAll('input[data-forecast]:not([data-mobile])').forEach((input) => {
                    const value = Math.abs(Number(input.value || 0));
                    payload[input.dataset.category] = input.dataset.type === 'expense' ? -value : value;
                });
                this.saving = true;
                try {
                    const response = await fetch(@json(route('financial-categories.expected-values.bulk')), {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ expected_values: payload }),
                    });
                    if (!response.ok) throw await response.json();
                    window.location.reload();
                } catch (error) {
                    alert(error.message || @json(__('Something went wrong')));
                    this.saving = false;
                }
            },
        };
    }
</script>
