<div x-data="submitForm()">

    <x-modal name="modal-create" :show="false" focusable>
        <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
            <h2 class="text-lg font-bold text-gray-900">{{ __('Create a new movement') }}</h2>

            <template x-if="errorMessages.length">
                <div class="bg-red-500 text-white p-4 rounded shadow-lg">
                    <ul>
                        <template x-for="error in errorMessages" :key="error">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                </div>
            </template>

            <form x-ref="financialForm" @submit.prevent="submitForm" method="POST"
                action="{{ route('financial-movements.store') }}" enctype="multipart/form-data">
                @csrf
                @include('financial-movements.form')
            </form>

            <!-- last movements registered -->
            <h2 class="text-lg font-bold text-gray-900 mt-6">{{ __('Last movements registered') }}</h2>
            <table class="w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th scope="col"
                            class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ __('Wallet') }}
                        </th>
                        <th scope="col"
                            class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ __('Date') }}
                        </th>
                        <th scope="col"
                            class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ __('Description') }}
                        </th>
                        <th scope="col"
                            class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ __('Category') }}
                        </th>
                        <th scope="col"
                            class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ __('Type') }}
                        </th>
                        <th scope="col"
                            class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                            {{ __('Amount') }}
                        </th>
                        <th scope="col"
                            class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <template x-for="movement in lastMovements" :key="movement.id">
                        <tr>
                            <td
                                class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span x-text="movement.wallet_id"></span>
                            </td>
                            <td
                                class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span x-text="movement.date"></span>
                            </td>
                            <td
                                class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span x-text="movement.description"></span>
                            </td>
                            <td
                                class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span x-text="movement.category_id"></span>
                            </td>
                            <td
                                class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span x-text="movement.type"></span>
                            </td>
                            <td
                                class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">
                                <span x-text="movement.amount"></span>
                            </td>
                            <td
                                class="px-1 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button class="bg-green-500 rounded-full text-white w-6 h-6 shadow-lg"
                                    @click.prevent="deleteMovement(movement.id)">
                                    <span class="fa fa-trash"></span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </x-modal>

    <template x-if="notification">
        <div class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50" x-text="notification"
            x-show="notification" x-transition></div>
    </template>

    <div class="flex fixed bottom-4 z-50 right-4 gap-4">
        <button class="bg-green-500 rounded-full text-white w-12 h-12 shadow-lg"
            @click.prevent="selectedType = 'income'; $dispatch('open-modal', 'modal-create')">
            <span class="fa fa-plus"></span>
        </button>
        <button class="bg-red-500 rounded-full text-white w-12 h-12 shadow-lg"
            @click.prevent="selectedType = 'expense'; $dispatch('open-modal', 'modal-create')">
            <span class="fa fa-minus"></span>
        </button>
    </div>
</div>

<script>
    function submitForm() {
        return {
            selectedType: 'income',
            isLoading: false,
            notification: '',
            errorMessages: [],
            lastMovements: [],
            wallets: @json($wallets),
            categories: @json($categories),
            types: @json($types),

            deleteMovement(id) {
                const baseUrl = "{{ url('/api/financial-movements') }}";
                const url = `${baseUrl}/${id}`;
                fetch(`${url}`, {
                        method: 'DELETE',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.lastMovements = this.lastMovements.filter(movement => movement.id !== id);
                        this.notification = 'Financial movement deleted successfully!';
                    }).catch(error => {
                        console.error('Error:', error);
                        this.notification = 'Unexpected error. Please try again later.';
                    });
            },

            submitForm() {
                this.isLoading = true;
                this.notification = '';
                this.errorMessages = [];
                let form = this.$refs.financialForm;

                if (!form) {
                    console.error('Form reference not found.');
                    this.isLoading = false;
                    return;
                }

                let formData = new FormData(form);

                fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (Array.isArray(data) === false && data.errors) {
                            this.errorMessages = Object.values(data.errors).flat();
                            return;
                        }

                        for (let i = 0; i < data.length; i++) {
                            data[i].wallet_id = this.wallets[data[i].wallet_id];
                            data[i].category_id = data[i].category_id ? this.categories[data[i].category_id] : 'N/A';
                            data[i].type = this.types[data[i].type];

                            if (data[i].description.length > 10) {
                                data[i].description = data[i].description.substring(0, 10) + '...';
                            }
                            if (data[i].wallet_id.length > 10) {
                                data[i].wallet_id = data[i].wallet_id.substring(0, 10) + '...';
                            }
                            if (data[i].category_id.length > 10) {
                                data[i].category_id = data[i].category_id.substring(0, 10) + '...';
                            }
                            if (data[i].type.length > 10) {
                                data[i].type = data[i].type.substring(0, 10) + '...';
                            }

                            data[i].amount = parseFloat(data[i].amount).toLocaleString('pt-PT', {
                                style: 'currency',
                                currency: 'EUR'
                            });
                            data[i].date = new Date(data[i].date).toLocaleDateString('pt-PT');
                            this.lastMovements.push(data[i]);
                        }
                        this.notification = 'Financial movement saved successfully!';
                        // reset form fields but keep date field value
                        form.reset();
                        form.querySelector('input[name="date"]').value = formData.get('date');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.notification = 'Unexpected error. Please try again later.';
                    })
                    .finally(() => this.isLoading = false);
            }
        }
    }
</script>
