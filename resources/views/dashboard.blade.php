<x-app-layout>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Wallets') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div x-data="{ table: 'currency' }" class="p-6 bg-white border-b border-gray-200">
                    <div class="">
                        <canvas id="financialChart"></canvas>
                    </div>

                    <script>
                        const monthlyData = @json($monthlySummary);

                        const labels = Object.keys(monthlyData);
                        const totalBalance = labels.map(m => monthlyData[m].total_balance);
                        const balanceChange = labels.map(m => monthlyData[m].balance_change);
                        const totalExpenses = labels.map(m => monthlyData[m].total_expenses);
                        const totalIncome = labels.map(m => monthlyData[m].total_income);
                        const totalUnidentified = labels.map(m => monthlyData[m].total_unidentified);

                        new Chart(document.getElementById("financialChart"), {
                            type: "line",
                            data: {
                                labels: labels,
                                datasets: [{
                                        label: "Total Balance",
                                        data: totalBalance,
                                        borderColor: "#4CAF50",
                                        fill: false
                                    },
                                    {
                                        label: "Change Balance",
                                        data: balanceChange,
                                        borderColor: "#FF9800",
                                        fill: false
                                    },
                                    {
                                        label: "Total Expenses",
                                        data: totalExpenses,
                                        borderColor: "#FF6384",
                                        fill: false
                                    },
                                    {
                                        label: "Total Income",
                                        data: totalIncome,
                                        borderColor: "#36A2EB",
                                        fill: false
                                    },
                                    {
                                        label: "Total Unidentified",
                                        data: totalUnidentified,
                                        borderColor: "#FFCE56",
                                        fill: false
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    </script>

                    <x-primary-button @click="table = (table === 'currency' ? 'percentage' : 'currency')">
                        <span x-text="table === 'percentage' ? 'View Currency' : 'View Percentage'"></span>
                    </x-primary-button>
                    <div x-show="table === 'currency'" class="overflow-x-auto mt-8">
                        <table class="min-w-max divide-y divide-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <!-- Cabeçalho fixo da categoria -->
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sticky left-0 bg-gray-50 z-10 w-72">
                                        Category
                                    </th>
                                    <!-- Cabeçalhos dos meses -->
                                    @foreach ($categorySummary->allMonths as $month)
                                        <th
                                            class="px-8 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            {{ $month }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($categorySummary->summary as $item)
                                    <tr>
                                        <!-- Categoria fixa -->
                                        <td
                                            class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sticky left-0 bg-white w-72">
                                            {{ $item->category }}
                                        </td>
                                        <!-- Totais por mês -->
                                        @foreach ($item->months as $total)
                                            <td
                                                class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                {!! $total != 0 ? colored_format_currency($total) : '' !!}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div x-show="table === 'percentage'" class="overflow-x-auto mt-8">
                        <table class="min-w-max divide-y divide-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <!-- Cabeçalho fixo da categoria -->
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sticky left-0 bg-gray-50 z-10 w-72">
                                        Category
                                    </th>
                                    <!-- Cabeçalhos dos meses -->
                                    @foreach ($categorySummary->allMonths as $month)
                                        <th
                                            class="px-8 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            {{ $month }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($categorySummary->percentages as $item)
                                    <tr>
                                        <!-- Categoria fixa -->
                                        <td
                                            class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sticky left-0 bg-white w-72">
                                            {{ $item->category }}
                                        </td>
                                        <!-- Totais por mês -->
                                        @foreach ($item->months as $total)
                                            <td
                                                class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                {!! $total != 0 ? colored_format_percentage($total) : '' !!}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
