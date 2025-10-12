<x-app-layout>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <x-slot name="header">
        <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
        <!-- CHANGE MONTH AND YEAR -->
        <x-dropdown-select
            :options="$filter"
            name="month"
            selected="{{ request('month', date('Y-m')) }}"
            onchange="window.location.href='{{ route('dashboard') }}?month=' + this.value"
        />

            </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                @if ($alerts['spending_spike_alert'])
                    @foreach ($alerts['spending_spike_alert'] as $alert)
                        @if ($alert['severity'] === 'warning')
                            <div class="p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
                        @else
                            <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700">
                        @endif
                            <p>{!! $alert['message'] !!}</p>
                        </div>
                    @endforeach
                @endif

                @if ($alerts['category_imbalance_alert'])
                    @foreach ($alerts['category_imbalance_alert'] as $alert)
                        @if ($alert['severity'] === 'warning')
                            <div class="p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
                        @else
                            <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700">
                        @endif
                            <p>{!! $alert['message'] !!}</p>
                        </div>
                    @endforeach
                @endif

                @if ($alerts['custom_alert'])
                    @foreach ($alerts['custom_alert'] as $alert)
                        <div class="p-4 bg-blue-100 border-l-4 border-blue-500 text-blue-700">
                            <p>{!! $alert['message'] !!}</p>
                        </div>
                    @endforeach
                @endif

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
                        const totalIncomeWithUnidentified = labels.map(m => parseFloat(monthlyData[m].total_income) + parseFloat(monthlyData[m].total_unidentified));
                        const totalUnidentified = labels.map(m => monthlyData[m].total_unidentified);
                        console.log(totalIncomeWithUnidentified)
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
                                    },
                                    {
                                        label: "Total Income + Unidentified",
                                        data: totalIncomeWithUnidentified,
                                        borderColor: "#9C27B0",
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

                    <div x-data="financialModal()" x-cloak>


                        <x-modal name="modal-movements" x-show="open" focusable>
                            <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
                                <h2 class="text-lg font-bold text-gray-900">
                                    <span x-text="titleCategory"></span>
                                </h2>
                                <!-- Tabela -->
                                <div class="overflow-y-auto h-96">


                                    <canvas id="movementsChart"></canvas>
                                    <table class="w-full text-sm text-left text-gray-700">
                                        <thead>
                                            <tr class="bg-gray-100 text-xs uppercase">
                                                <th class="px-3 py-2">Data</th>
                                                <th class="px-3 py-2">Descrição</th>
                                                <th class="px-3 py-2 text-right">Valor</th>
                                                <th class="px-3 py-2">Tipo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="item in rows" :key="item.id">
                                                <tr class="border-t">
                                                    <td class="px-3 py-2" x-text="item.date"></td>
                                                    <td class="px-3 py-2" x-text="item.description"></td>
                                                    <td class="px-3 py-2 text-right font-medium"
                                                        :class="item.isExpense ? 'text-red-600' : 'text-green-600'"
                                                        x-text="item.amount"></td>
                                                    <td class="px-3 py-2 capitalize" x-text="item.type"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-t font-bold text-sm text-gray-800">
                                                <td colspan="2" class="px-3 py-2 text-left">Total</td>
                                                <td class="px-3 py-2 text-right"
                                                    :class="totalAmount.startsWith('-') ? 'text-red-600' : 'text-green-600'"
                                                    x-text="totalAmount"></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </x-modal>


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
                                    @foreach ($categorySummary->summary as $category => $item)
                                        <tr class="hover:bg-gray-300">
                                            <!-- Categoria fixa -->
                                            <td
                                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sticky left-0 w-72">
                                                {{ $item->category }}
                                            </td>
                                            <!-- Totais por mês -->
                                            @foreach ($item->months as $month => $total)
                                                <td
                                                    @if ($month !== 'Previsão') @click="openModal('{{ $month }}', '{{ $category }}', '{{ $item->category }}')"
                                class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-100 transition cursor-pointer"
                                @else
                                    class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-100 transition" @endif>
                                                    {!! $total != 0 ? colored_format_currency($total) : '' !!}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <th
                                            class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Total
                                        </th>
                                        @foreach ($categorySummary->total as $total)
                                            <th
                                                class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                {!! $total != 0 ? colored_format_currency($total) : '' !!}
                                            </th>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
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
    <script>
        function financialModal() {
            return {
                open: false,
                items: [],
                rows: [],
                titleCategory: '',
                titleMonthYear: '',
                totalAmount: '',
                total: 0,

                async openModal(monthYear, category, categoryName) {
                    try {
                        this.titleCategory = categoryName;
                        this.titleMonthYear = monthYear;

                        // Extrair mês e ano de 'monthYear'
                        const [month, year] = monthYear.split("/");
                        const date = new Date(`${year}-${month}-01`);
                        const dateFrom = date.toISOString().slice(0, 10);
                        const dateTo = new Date(`${year}-${month}-31`).toISOString().slice(0, 10);

                        const params = new URLSearchParams({
                            attribute: 'category_id',
                            search: category,
                            date_from: dateFrom,
                            date_to: dateTo,
                            sort: 'date',
                            order: 'asc',
                        });

                        const url = `/api/financial-movements/filter?${params.toString()}`;

                        // Fazer a requisição GET
                        const response = await fetch(url);
                        const data = await response.json();

                        this.total = 0;

                        // Formata os dados
                        this.items = data.map(item => {
                            const rawAmount = parseFloat(item.amount);
                            const isExpense = item.type === 'expense' || item.type === 'discount';

                            // Calcular valor com sinal
                            const rawValue = parseFloat(item.amount);
                            const signedValue = isExpense ? -rawValue : rawValue;

                            // Acumular total
                            this.total += signedValue;

                            // Converte a data para dd/mm/yyyy
                            const formattedDate = new Date(item.date).toLocaleDateString('pt-PT');

                            return {
                                id: item.id,
                                date: formattedDate,
                                description: item.description,
                                amount: signedValue.toLocaleString('pt-PT', {
                                    style: 'currency',
                                    currency: 'EUR'
                                }),
                                isExpense,
                                type: item.type
                            };
                        });

                        this.rows = JSON.parse(JSON.stringify(this.items));
                        this.createChart();

                    } catch (err) {
                        console.error("Erro ao buscar dados:", err);
                    }
                },

                createChart() {
                    // Salva o total formatado
                    this.totalAmount = this.total.toLocaleString('pt-PT', {
                        style: 'currency',
                        currency: 'EUR'
                    });

                    // Disparar o evento para abrir o modal
                    this.$dispatch('open-modal', 'modal-movements');

                    // Agrupa por date e soma os valores
                    this.items = this.items.reduce((acc, item) => {
                        const existing = acc.find(i => i.date === item.date);
                        if (existing) {
                            existing.amount = (parseFloat(existing.amount.replace('€', '').replace(',', '.')) + parseFloat(item.amount.replace('€', '').replace(',', '.'))).toLocaleString('pt-PT', {
                                style: 'currency',
                                currency: 'EUR'
                            });
                        } else {
                            acc.push(item);
                        }
                        return acc;
                    }, []);

                    // Configurar o gráfico
                    const ctx = document.getElementById('movementsChart').getContext('2d');

                    // Destrua o gráfico anterior, se existir
                    if (Chart.getChart('movementsChart')) {
                        Chart.getChart('movementsChart').destroy();
                    }

                    const chartData = {
                        labels: this.items.map(item => item.date),
                        datasets: [{
                            label: this.total < 0 ? 'Despesas' : 'Receitas',
                            data: this.items.map(item => this.total < 0 ? -parseFloat(item.amount.replace('€', '').replace(',', '.')) : parseFloat(item.amount.replace('€', '').replace(',', '.'))),
                            borderWidth: 1,
                            borderColor: this.total < 0 ? 'red' : 'green',
                            backgroundColor: this.total < 0 ? 'rgba(255, 99, 132, 0.2)' : 'rgba(75, 192, 192, 0.2)',
                        }]
                    };
                    // Inverter o eixo y
                    chartData.datasets[0].data = chartData.datasets[0].data.reverse();

                    const chartOptions = {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    };
                    const chart = new Chart(ctx, {
                        type: 'line',
                        data: chartData,
                        options: chartOptions
                    });
                },

                closeModal() {
                    this.open = false;
                }
            }
        }
    </script>



</x-app-layout>
