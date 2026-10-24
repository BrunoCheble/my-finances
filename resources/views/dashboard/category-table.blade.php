{{-- Categories x months: values or percentages, editable forecasts, click a value to see its movements. --}}
@php($forecastKey = 'Previsão')
<section class="card overflow-hidden" aria-labelledby="categories-title" x-data="{ view: 'currency' }">
    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div>
            <h2 id="categories-title" class="text-base font-semibold text-slate-900">{{ __('Categories by month') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Click a value to see its movements. Edit the forecast in the last column.') }}</p>
        </div>
        <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" role="tablist">
            <button type="button" role="tab" :aria-selected="view === 'currency'" @click="view = 'currency'"
                :class="view === 'currency' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-md px-3 py-1.5">{{ __('Amounts') }}</button>
            <button type="button" role="tab" :aria-selected="view === 'percentage'" @click="view = 'percentage'"
                :class="view === 'percentage' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-md px-3 py-1.5">{{ __('Percentages') }}</button>
        </div>
    </div>

    <div x-data="categoryMonths()">
        <div class="relative overflow-x-auto" x-ref="scroller" x-init="$nextTick(() => $refs.scroller.scrollLeft = $refs.scroller.scrollWidth)">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="th sticky left-0 z-10 min-w-[11rem] bg-slate-50">{{ __('Category') }}</th>
                        @foreach ($categorySummary->allMonths as $column)
                            <th scope="col" class="th whitespace-nowrap text-right">{{ $column === $forecastKey ? __('Forecast') : $column }}</th>
                        @endforeach
                    </tr>
                </thead>

                {{-- amounts --}}
                <tbody x-show="view === 'currency'" class="divide-y divide-slate-100">
                    @foreach ($categorySummary->summary as $categoryId => $item)
                        <tr class="group hover:bg-slate-50">
                            <th scope="row" class="td sticky left-0 bg-white text-left font-medium text-slate-800 group-hover:bg-slate-50">{{ $item->category }}</th>
                            @foreach ($item->months as $column => $total)
                                <td class="td whitespace-nowrap text-right">
                                    @if ($column === $forecastKey)
                                        <input id="forecast-{{ $categoryId }}" aria-label="{{ __('Forecast') }} {{ $item->category }}" type="number" step="0.01" name="categories[{{ $categoryId }}]" value="{{ $total }}"
                                            @input="dirty = true" class="input w-28 py-1 text-right tabular-nums">
                                    @elseif ($total != 0)
                                        <button type="button" class="rounded px-1 hover:bg-brand-50"
                                            @click="openMonth('{{ $column }}', '{{ $categoryId }}', @js($item->category))">
                                            <x-money :value="$total" :sign="false" />
                                        </button>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>

                {{-- percentages --}}
                <tbody x-show="view === 'percentage'" x-cloak class="divide-y divide-slate-100">
                    @foreach ($categorySummary->percentages as $item)
                        <tr class="group hover:bg-slate-50">
                            <th scope="row" class="td sticky left-0 bg-white text-left font-medium text-slate-800 group-hover:bg-slate-50">{{ $item->category }}</th>
                            @foreach ($item->months as $total)
                                <td class="td whitespace-nowrap text-right tabular-nums">{!! $total != 0 ? colored_format_percentage($total) : '<span class="text-slate-300">—</span>' !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>

                <tfoot x-show="view === 'currency'" class="border-t border-slate-200 bg-slate-50 font-semibold">
                    <tr>
                        <th scope="row" class="td sticky left-0 bg-slate-50 text-left">{{ __('Total') }}</th>
                        @foreach ($categorySummary->total as $total)
                            <td class="td whitespace-nowrap text-right"><x-money :value="$total" /></td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>

        <div x-show="view === 'currency'" class="flex items-center justify-end gap-3 border-t border-slate-200 px-4 py-3 sm:px-6">
            <span x-show="dirty" x-cloak class="text-sm text-amber-700">{{ __('Unsaved forecast changes') }}</span>
            <x-button icon="floppy-disk" x-on:click="saveForecasts" x-bind:disabled="saving">{{ __('Save forecasts') }}</x-button>
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
                </ul>
                <div x-show="!detail.loading" class="mt-3 flex justify-between border-t border-slate-200 pt-3 text-sm font-semibold">
                    <span>{{ __('Total') }}</span>
                    <span class="amount" :class="detail.total < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="money(detail.total)"></span>
                </div>
            </div>
        </x-modal>
    </div>
</section>

<script>
    function categoryMonths() {
        return {
            dirty: false,
            saving: false,
            detail: { category: '', month: '', rows: [], total: 0, loading: false },
            money(value) {
                return Number(value).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
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
                const payload = {};
                document.querySelectorAll('input[name^="categories["]').forEach((input) => {
                    const match = input.name.match(/categories\[(\d+)\]/);
                    if (match) payload[match[1]] = Number(input.value || 0);
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
