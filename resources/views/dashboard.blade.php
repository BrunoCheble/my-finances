@php
    $selected = \Carbon\Carbon::createFromFormat('Y-m-d', $month . '-01');
    $monthKey = $selected->format('m/Y');
    $monthLabel = ucfirst($selected->translatedFormat('F Y'));

    // percentage change against the previous month, as a readable line
    $delta = function (float $current, float $previous) {
        if ($previous == 0) {
            return null;
        }
        return round((abs($current) - abs($previous)) / abs($previous) * 100);
    };
    $incomeDelta = $kpis['has_previous'] ? $delta($kpis['income'], $kpis['previous_income']) : null;
    $expensesDelta = $kpis['has_previous'] ? $delta($kpis['expenses'], $kpis['previous_expenses']) : null;

    // last 12 months up to the selected one
    $chartMonths = collect(array_keys($monthlySummary))->sort()->filter(fn ($m) => $m <= $month)->take(-12)->values();
    $chart = $chartMonths->map(fn ($m) => [
        'label' => ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $m . '-01')->translatedFormat('M/y')),
        'income' => round((float) $monthlySummary[$m]['total_income'], 2),
        'expenses' => round(abs((float) $monthlySummary[$m]['total_expenses']), 2),
    ]);
    $netWorth = \App\Services\DashboardKpiService::netWorthSeries($chartMonths->all());

    // spending by category in the selected month, against the forecast
    $spending = collect($categorySummary->summary)
        ->map(fn ($item, $id) => (object) [
            'id' => $id,
            'name' => $item->category,
            'amount' => (float) ($item->months[$monthKey] ?? 0),
            'forecast' => (float) ($item->months['Previsão'] ?? 0),
        ])
        ->filter(fn ($row) => $row->amount < 0)
        ->sortBy('amount')
        ->values();
    $spendingTotal = abs($spending->sum('amount'));
    $spendingTop = $spending->take(7);
    $spendingOthers = abs($spending->slice(7)->sum('amount'));
    $spendingMax = abs($spendingTop->first()->amount ?? 1) ?: 1;

@endphp

<x-app-layout>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <x-slot name="header">
        <x-page-header :title="__('Dashboard')" :subtitle="$monthLabel">
            <x-slot name="actions">
                <label for="month" class="sr-only">{{ __('Month') }}</label>
                <select id="month" class="input w-44" onchange="window.location.href='{{ route('dashboard') }}?range={{ $range }}&month=' + this.value">
                    @foreach (array_reverse($filter, true) as $value => $label)
                        <option value="{{ $value }}" @selected($value === $month)>
                            {{ ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $value . '-01')->translatedFormat('F Y')) }}
                        </option>
                    @endforeach
                </select>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        {{-- Headline numbers --}}
        <section aria-label="{{ __('Summary') }}" class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-stat :label="__('Your net worth')" :value="$kpis['net_worth']" icon="shield-halved" tone="brand">
                <x-slot name="footer">
                    @if ($kpis['third_party'] != 0)
                        {{ __('In banks') }}: {{ format_currency($kpis['in_banks']) }}
                    @else
                        {{ __('All wallets') }}
                    @endif
                </x-slot>
            </x-stat>

            <x-stat :label="__('Total income')" :value="$kpis['income']" icon="arrow-up" tone="green">
                <x-slot name="footer">
                    @if ($incomeDelta !== null)
                        @if ($incomeDelta == 0)
                            {{ __('Same as previous month') }}
                        @else
                            <span @class(['font-medium', 'text-emerald-700' => $incomeDelta > 0, 'text-rose-700' => $incomeDelta < 0])>
                                {{ $incomeDelta > 0 ? '▲' : '▼' }} {{ abs($incomeDelta) }}%
                            </span>
                            {{ __('vs previous month') }}
                        @endif
                    @else
                        {{ $monthLabel }}
                    @endif
                </x-slot>
            </x-stat>

            <x-stat :label="__('Total expenses')" :value="abs($kpis['expenses'])" icon="arrow-down" tone="red">
                <x-slot name="footer">
                    @if ($expensesDelta !== null)
                        {{-- spending more is the bad direction --}}
                        @if ($expensesDelta == 0)
                            {{ __('Same as previous month') }}
                        @else
                            <span @class(['font-medium', 'text-rose-700' => $expensesDelta > 0, 'text-emerald-700' => $expensesDelta < 0])>
                                {{ $expensesDelta > 0 ? '▲' : '▼' }} {{ abs($expensesDelta) }}%
                            </span>
                            {{ __('vs previous month') }}
                        @endif
                    @else
                        {{ $monthLabel }}
                    @endif
                </x-slot>
            </x-stat>

            <x-stat :label="__('Month result')" :value="$kpis['result']" icon="scale-balanced" :colored="true" :sign="true">
                <x-slot name="footer">
                    {{ $kpis['result'] >= 0 ? __('You saved this month') : __('You spent more than you earned') }}
                </x-slot>
            </x-stat>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Income vs expenses --}}
            <section class="card p-4 sm:p-6 lg:col-span-2" aria-labelledby="flow-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="flow-title" class="text-base font-semibold text-slate-900">{{ __('Income vs expenses') }}</h2>
                        <p class="text-sm text-slate-500">{{ __('Last :count months', ['count' => $chart->count()]) }}</p>
                    </div>
                    <ul class="flex items-center gap-4 text-sm text-slate-600">
                        <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-[#059669]" aria-hidden="true"></span>{{ __('Total income') }}</li>
                        <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-[#e34948]" aria-hidden="true"></span>{{ __('Total expenses') }}</li>
                    </ul>
                </div>
                <div class="relative mt-4 h-64 sm:h-72">
                    <canvas id="flowChart" role="img" aria-label="{{ __('Monthly income and expenses chart; the table below has the same data.') }}"></canvas>
                </div>
                <table class="sr-only">
                    <caption>{{ __('Income vs expenses') }}</caption>
                    <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Total income') }}</th><th>{{ __('Total expenses') }}</th></tr></thead>
                    <tbody>
                        @foreach ($chart as $row)
                            <tr><td>{{ $row['label'] }}</td><td>{{ format_currency($row['income']) }}</td><td>{{ format_currency($row['expenses']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{-- Alerts --}}
            @include('dashboard.alerts')
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Spending by category --}}
            <section class="card p-4 sm:p-6 lg:col-span-2" aria-labelledby="spending-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="spending-title" class="text-base font-semibold text-slate-900">{{ __('Spending by category') }}</h2>
                        <p class="text-sm text-slate-500">{{ $monthLabel }} · {{ __('total') }} {{ format_currency($spendingTotal) }}</p>
                    </div>
                </div>

                @if ($spending->isEmpty())
                    <x-empty-state icon="tags" :title="__('No spending in this month')" class="!py-10" />
                @else
                    <ul class="mt-5 space-y-4">
                        @foreach ($spendingTop as $row)
                            @php
                                $spent = abs($row->amount);
                                $forecast = $row->forecast < 0 ? abs($row->forecast) : null;
                                $over = $forecast && $spent > $forecast;
                            @endphp
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="truncate font-medium text-slate-800">{{ $row->name }}</span>
                                    <span class="amount shrink-0 font-semibold text-slate-900">{{ format_currency($spent) }}</span>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ max(2, round($spent / $spendingMax * 100)) }}%"></div>
                                </div>
                                <div class="mt-1 flex justify-between gap-3 text-xs text-slate-500">
                                    <span>{{ round($spent / max($spendingTotal, 0.01) * 100) }}% {{ __('of spending') }}</span>
                                    @if ($forecast)
                                        <span @class(['font-medium text-rose-700' => $over])>
                                            @if ($over)<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ __('above forecast') }} · @endif
                                            {{ __('forecast') }} {{ format_currency($forecast) }}
                                        </span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                        @if ($spendingOthers > 0)
                            <li class="flex items-baseline justify-between border-t border-slate-100 pt-3 text-sm text-slate-500">
                                <span>{{ __('Other categories') }} ({{ $spending->count() - $spendingTop->count() }})</span>
                                <span class="amount font-medium">{{ format_currency($spendingOthers) }}</span>
                            </li>
                        @endif
                    </ul>
                @endif
            </section>

            {{-- Net worth over time --}}
            <section class="card p-4 sm:p-6" aria-labelledby="networth-title">
                <h2 id="networth-title" class="text-base font-semibold text-slate-900">{{ __('Net worth evolution') }}</h2>
                <p class="text-sm text-slate-500">
                    {{ __('Change in :month', ['month' => $monthLabel]) }}:
                    <x-money :value="$kpis['net_worth_change']" class="font-medium" />
                </p>
                <div class="relative mt-4 h-56">
                    <canvas id="netWorthChart" role="img" aria-label="{{ __('Net worth at the end of each month') }}"></canvas>
                </div>
            </section>
        </div>

        {{-- Categories x months, with editable forecasts --}}
        @include('dashboard.category-table')
    </div>

    <script>
        (() => {
            const money = (value) => Number(value).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
            const compact = (value) => Number(value).toLocaleString('pt-PT', { notation: 'compact', maximumFractionDigits: 1 });
            const ink = { muted: '#64748b', grid: '#eef2f6' };
            const flow = @json($chart);
            const netWorth = @json($netWorth);

            Chart.defaults.font.family = 'Figtree, ui-sans-serif, system-ui, sans-serif';
            Chart.defaults.color = ink.muted;

            const axes = {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    grid: { color: ink.grid }, border: { display: false },
                    ticks: { callback: (value) => compact(value) + ' €', maxTicksLimit: 5 },
                },
            };
            const tooltip = {
                backgroundColor: '#0f172a', padding: 10, cornerRadius: 8, boxPadding: 4,
                callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${money(ctx.parsed.y)}` },
            };

            new Chart(document.getElementById('flowChart'), {
                type: 'bar',
                data: {
                    labels: flow.map((row) => row.label),
                    datasets: [
                        { label: @json(__('Total income')), data: flow.map((row) => row.income), backgroundColor: '#059669', borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 18 },
                        { label: @json(__('Total expenses')), data: flow.map((row) => row.expenses), backgroundColor: '#e34948', borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 18 },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    datasets: { bar: { categoryPercentage: 0.6, barPercentage: 0.85 } },
                    plugins: { legend: { display: false }, tooltip },
                    scales: { ...axes, y: { ...axes.y, beginAtZero: true } },
                },
            });

            new Chart(document.getElementById('netWorthChart'), {
                type: 'line',
                data: {
                    labels: flow.map((row) => row.label),
                    datasets: [{
                        label: @json(__('Net worth')),
                        data: Object.values(netWorth),
                        borderColor: '#2479bf', borderWidth: 2, tension: 0.3,
                        pointRadius: 0, pointHoverRadius: 5, pointHitRadius: 12, pointBackgroundColor: '#2479bf',
                        fill: true, backgroundColor: 'rgba(36, 121, 191, 0.08)',
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false }, tooltip },
                    scales: { ...axes, x: { ...axes.x, ticks: { maxTicksLimit: 6 } } },
                },
            });
        })();
    </script>
</x-app-layout>
