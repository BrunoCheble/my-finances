<div x-data="modalSummaryMovements()" x-cloak>
    <x-modal name="modal-movements">
        <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
            <h2 class="text-lg font-bold text-gray-900">
                <span x-text="titleCategory"></span>
            </h2>
            <div class="overflow-y-auto sh-96">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead>
                        <tr class="bg-gray-100 text-xs uppercase">
                            <th class="px-3 py-2">{{ __('Wallet') }}</th>
                            <th class="px-3 py-2">{{ __('Date') }}</th>
                            <th class="px-3 py-2">{{ __('Description') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Amount') }}</th>
                            <th class="px-3 py-2">{{ __('Type') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in rows" :key="item.id">
                            <tr class="border-t">
                                <td class="px-3 py-2" x-text="item.wallet"></td>
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
                            <td colspan="3" class="px-3 py-2 text-left">Total</td>
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
</div>
<script>
    function modalSummaryMovements() {
        return {
            titleCategory: 'Movimentações',
            rows: [],
            totalAmount: '',
            total: 0,
            open: false,
            init() {
                window.modalSummary = this;
            },
            async openModal(startDate, endDate, type, walletId = undefined) {
                this.titleCategory = `Movimentações de ${startDate} a ${endDate}`;

                const params = new URLSearchParams({
                    date_from: startDate,
                    date_to: endDate,
                    attribute: 'type',
                    search: type
                });

                if (walletId) {
                    params.append('wallet_id', walletId);
                }

                const baseUrl = "{{ route('financial-movements.filter') }}";
                const url = `${baseUrl}?${params.toString()}`;

                const response = await fetch(url);
                const data = await response.json();

                this.total = 0;

                this.items = data.map(item => {
                    const rawAmount = parseFloat(item.amount);
                    const isExpense = item.type === 'discount' || item.type === 'refund';

                    const rawValue = parseFloat(item.amount);
                    const signedValue = isExpense ? -rawValue : rawValue;

                    this.total += signedValue;

                    const formattedDate = new Date(item.date).toLocaleDateString('pt-PT');

                    return {
                        id: item.id,
                        date: formattedDate,
                        wallet: item.wallet_id,
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

                this.totalAmount = this.total.toLocaleString('pt-PT', {
                    style: 'currency',
                    currency: 'EUR'
                });

                this.$dispatch('open-modal', 'modal-movements');
            },
            closeModal() {
                this.$dispatch('close-modal', 'modal-movements');
            },

        }
    }
</script>
