@php
    $months = $history['months'];
    $total = $history['total'];
    $hasHistory = count($months) > 0;

    $current = $hasHistory ? end($total) : 0;
    $start = $hasHistory ? $total[0] : 0;
    $change = $current - $start;
    $changePercent = $start != 0 ? round($change / abs($start) * 100, 1) : null;

    // month over month change (first month is only the starting point)
    $monthly = [];
    for ($i = 1; $i < count($months); $i++) {
        $monthly[$months[$i]] = round($total[$i] - $total[$i - 1], 2);
    }
    $best = $monthly ? array_keys($monthly, max($monthly))[0] : null;
    $worst = $monthly ? array_keys($monthly, min($monthly))[0] : null;
    $monthName = fn ($month) => ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $month . '-01')->translatedFormat('M/y'));

    $periods = ['6m' => __('6 months'), '1y' => __('1 year'), '2y' => __('2 years'), 'all' => __('All')];

    // inline SVG sparkline points for a series
    $sparkline = function (array $values) {
        $count = count($values);
        if ($count < 2) {
            return '';
        }
        $min = min($values);
        $max = max($values);
        $range = ($max - $min) ?: 1;
        return collect($values)->map(fn ($value, $i) => round($i / ($count - 1) * 100, 2) . ',' . round(28 - ($value - $min) / $range * 24, 2))->implode(' ');
    };
@endphp

<x-app-layout>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <x-slot name="header">
        <x-page-header :title="__('Net worth')" :subtitle="__('How your money evolves over time, wallet by wallet.')">
            <x-slot name="actions">
                <nav class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" aria-label="{{ __('Period') }}">
                    @foreach ($periods as $key => $label)
                        <a href="{{ route('financial-balances.index', array_merge(request()->except('period'), ['period' => $key])) }}"
                            @class(['rounded-md px-3 py-1.5', 'bg-white text-slate-900 shadow-sm' => $period === $key, 'text-slate-600 hover:text-slate-900' => $period !== $key])
                            @if ($period === $key) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('financial-balances.modal-summary')

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @include('layouts.alert')

        @if (!$hasHistory)
            <x-card>
                <x-empty-state icon="scale-balanced" :title="__('No balances yet')"
                    :description="__('Register movements or import a statement and the evolution shows up here.')" />
            </x-card>
        @else
            {{-- Headline numbers --}}
            <section aria-label="{{ __('Summary') }}" class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                <x-stat :label="__('Your net worth')" :value="$current" icon="shield-halved" tone="brand">
                    <x-slot name="footer">
                        @if ($summary['third_party'] != 0)
                            {{ __('In banks') }}: {{ format_currency($summary['in_banks']) }}
                        @else
                            {{ __('Today') }}
                        @endif
                    </x-slot>
                </x-stat>
                <x-stat :label="__('Change in period')" :value="$change" icon="chart-line" :colored="true" :sign="true">
                    <x-slot name="footer">
                        @if ($changePercent !== null)
                            <span @class(['font-medium', 'text-emerald-700' => $change >= 0, 'text-rose-700' => $change < 0])>{{ $change >= 0 ? '▲' : '▼' }} {{ number_format(abs($changePercent), 1, ',', '.') }}%</span>
                        @endif
                        {{ __('since :month', ['month' => $monthName($months[0])]) }}
                    </x-slot>
                </x-stat>
                <x-stat :label="__('Best month')" :value="$best ? $monthly[$best] : 0" icon="arrow-trend-up" tone="green" :colored="true" :sign="true">
                    <x-slot name="footer">{{ $best ? $monthName($best) : '—' }}</x-slot>
                </x-stat>
                <x-stat :label="__('Worst month')" :value="$worst ? $monthly[$worst] : 0" icon="arrow-trend-down" :tone="$worst && $monthly[$worst] < 0 ? 'red' : 'slate'" :colored="true" :sign="true">
                    <x-slot name="footer">{{ $worst ? $monthName($worst) : '—' }}</x-slot>
                </x-stat>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Evolution --}}
                <section class="card p-4 sm:p-6 lg:col-span-2" aria-labelledby="evolution-title">
                    <h2 id="evolution-title" class="text-base font-semibold text-slate-900">{{ __('Net worth evolution') }}</h2>
                    <p class="text-sm text-slate-500">{{ __('End of each month') }}</p>
                    <div class="relative mt-4 h-64 sm:h-80">
                        <canvas id="netWorthChart" role="img" aria-label="{{ __('Net worth at the end of each month; the table below has the same data.') }}"></canvas>
                    </div>
                </section>

                {{-- By wallet --}}
                <section class="card p-4 sm:p-6" aria-labelledby="wallets-title">
                    <h2 id="wallets-title" class="text-base font-semibold text-slate-900">{{ __('By wallet') }}</h2>
                    <ul class="mt-4 divide-y divide-slate-100">
                        @foreach ($history['wallets'] as $wallet)
                            @php
                                $last = end($wallet->values);
                                $walletChange = $last - $wallet->values[0];
                            @endphp
                            <li class="flex items-center gap-3 py-3">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $wallet->color }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="truncate text-sm font-medium text-slate-800">{{ $wallet->name }}</p>
                                        @if ($wallet->third_party)
                                            <span class="amount text-sm font-semibold text-purple-700">{{ format_currency(-$last) }}</span>
                                        @else
                                            <x-money :value="$last" :colored="false" :sign="false" class="text-sm font-semibold text-slate-900" />
                                        @endif
                                    </div>
                                    <div class="mt-1 flex items-center justify-between gap-2">
                                        <p class="truncate text-xs text-slate-500">
                                            @if ($wallet->third_party)
                                                {{ __('Kept for someone else') }}
                                            @elseif ($walletChange != 0)
                                                <x-money :value="$walletChange" /> {{ __('in the period') }}
                                            @else
                                                {{ __('No change in the period') }}
                                            @endif
                                        </p>
                                        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="h-5 w-16 shrink-0" aria-hidden="true">
                                            <polyline points="{{ $sparkline($wallet->values) }}" fill="none" stroke="{{ $wallet->third_party ? '#9333ea' : '#2479bf' }}" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            {{-- Monthly change --}}
            @if ($monthly)
                <section class="card p-4 sm:p-6" aria-labelledby="monthly-title">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="monthly-title" class="text-base font-semibold text-slate-900">{{ __('Month over month change') }}</h2>
                            <p class="text-sm text-slate-500">{{ __('How much your net worth grew or shrank each month') }}</p>
                        </div>
                        <ul class="flex items-center gap-4 text-sm text-slate-600">
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-[#059669]" aria-hidden="true"></span>{{ __('Grew') }}</li>
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-[#e34948]" aria-hidden="true"></span>{{ __('Shrank') }}</li>
                        </ul>
                    </div>
                    <div class="relative mt-4 h-56">
                        <canvas id="monthlyChart" role="img" aria-label="{{ __('Net worth change per month; the table below has the same data.') }}"></canvas>
                    </div>
                </section>
            @endif

            <table class="sr-only">
                <caption>{{ __('Net worth evolution') }}</caption>
                <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Net worth') }}</th><th>{{ __('Change') }}</th></tr></thead>
                <tbody>
                    @foreach ($months as $i => $month)
                        <tr><td>{{ $monthName($month) }}</td><td>{{ format_currency($total[$i]) }}</td><td>{{ isset($monthly[$month]) ? format_currency($monthly[$month]) : '' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Monthly detail (existing balances, with edit / recalculate) --}}
        <section aria-labelledby="detail-title" class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="detail-title" class="text-base font-semibold text-slate-900">{{ __('Monthly detail') }}</h2>
                    <p class="text-sm text-slate-500">{{ __('Balances of each wallet per month. Edit to adjust to the real balance.') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <label for="year" class="sr-only">{{ __('Year') }}</label>
                    <select id="year" class="input w-44" onchange="window.location.href='{{ route('financial-balances.index') }}?period={{ $period }}&year=' + this.value">
                        <option value="">{{ __('All years') }}</option>
                        @foreach ($filter as $value => $label)
                            <option value="{{ $value }}" @selected((string) request('year') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-button variant="secondary" icon="plus" :href="route('financial-balances.create')">{{ __('Open month') }}</x-button>
                </div>
            </div>

            @foreach ($groups as $key => $group)
                @include('financial-balances.accordion-item.index', ['group' => $group, 'key' => $key])
            @endforeach
        </section>
    </div>

    @if ($hasHistory)
        <script>
            (() => {
                const money = (value) => Number(value).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
                const compact = (value) => Number(value).toLocaleString('pt-PT', { notation: 'compact', maximumFractionDigits: 1 });
                const labels = @json(array_map($monthName, $months));
                const total = @json($total);
                const monthly = @json(array_values($monthly));

                Chart.defaults.font.family = 'Figtree, ui-sans-serif, system-ui, sans-serif';
                Chart.defaults.color = '#64748b';
                const axes = {
                    x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 8 } },
                    y: { grid: { color: '#eef2f6' }, border: { display: false }, ticks: { callback: (v) => compact(v) + ' €', maxTicksLimit: 5 } },
                };
                const tooltip = {
                    backgroundColor: '#0f172a', padding: 10, cornerRadius: 8,
                    callbacks: { label: (ctx) => ' ' + money(ctx.parsed.y) },
                };

                new Chart(document.getElementById('netWorthChart'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: @json(__('Net worth')), data: total,
                            borderColor: '#2479bf', borderWidth: 2, tension: 0.3,
                            pointRadius: total.length > 24 ? 0 : 3, pointHoverRadius: 6, pointHitRadius: 14, pointBackgroundColor: '#2479bf',
                            fill: true, backgroundColor: 'rgba(36, 121, 191, 0.08)',
                        }],
                    },
                    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false }, tooltip }, scales: axes },
                });

                const monthlyCanvas = document.getElementById('monthlyChart');
                if (monthlyCanvas) {
                    new Chart(monthlyCanvas, {
                        type: 'bar',
                        data: {
                            labels: labels.slice(1),
                            datasets: [{
                                label: @json(__('Change')), data: monthly,
                                backgroundColor: monthly.map((v) => v >= 0 ? '#059669' : '#e34948'),
                                borderRadius: 4, maxBarThickness: 22,
                            }],
                        },
                        options: {
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: (ctx) => ' ' + (ctx.parsed.y > 0 ? '+' : '') + money(ctx.parsed.y) } } },
                            scales: axes,
                        },
                    });
                }
            })();
        </script>
    @endif
</x-app-layout>
