<div x-data="submitForm()" @keydown.escape.window="handleModalCloseManual">

    <x-modal name="modal-create" :show="false" sheet>
        <div class="flex flex-1 flex-col">
            <div class="flex items-start justify-between gap-4 px-5 pb-4 pt-5 sm:px-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('New movement') }}</h2>
                    <p class="text-sm text-slate-500">{{ __('The form stays open so you can register several in a row.') }}</p>
                </div>
                <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click.prevent="handleModalCloseManual" />
            </div>

            <template x-if="errorMessages.length">
                <div class="mx-5 mb-4 rounded-lg sm:mx-6 border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">
                    <ul class="list-disc space-y-1 pl-4">
                        <template x-for="error in errorMessages" :key="error">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                </div>
            </template>

            {{-- the category of the last saved movement, in its month --}}
            <template x-if="categoryStatus">
                <section class="mx-5 mb-4 rounded-xl border p-4 sm:mx-6" aria-live="polite"
                    :class="{ 'border-rose-200 bg-rose-50': categoryStatus.level === 'over', 'border-amber-200 bg-amber-50': categoryStatus.level === 'close', 'border-slate-200 bg-slate-50': categoryStatus.level === 'ok' }">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="min-w-0 truncate text-sm font-semibold text-slate-900">
                            <i class="fa-solid fa-tag mr-1 text-xs text-slate-400" aria-hidden="true"></i>
                            <span x-text="categoryStatus.name"></span>
                            <span class="font-normal text-slate-500" x-text="'· ' + categoryStatus.month"></span>
                        </h3>
                        <p class="amount shrink-0 text-sm">
                            <span class="font-semibold" :class="categoryStatus.level === 'over' ? 'text-rose-700' : 'text-slate-900'" x-text="money(categoryStatus.total)"></span>
                            <span class="text-slate-500" x-show="categoryStatus.forecast" x-text="@js(__('of :amount', ['amount' => '__A__'])).replace('__A__', money(categoryStatus.forecast))"></span>
                        </p>
                    </div>
                    <div x-show="categoryStatus.forecast" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/70 ring-1 ring-inset ring-slate-200" role="progressbar"
                        :aria-valuenow="categoryStatus.percent" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Forecast used') }}">
                        <div class="h-full rounded-full"
                            :class="{ 'bg-rose-500': categoryStatus.level === 'over', 'bg-amber-400': categoryStatus.level === 'close', 'bg-brand-500': categoryStatus.level === 'ok' && categoryStatus.type === 'expense', 'bg-emerald-500': categoryStatus.type === 'income' }"
                            :style="`width: ${Math.min(100, categoryStatus.percent || 0)}%`"></div>
                    </div>
                    <p class="mt-2 text-xs" :class="categoryStatus.level === 'over' ? 'text-rose-700' : (categoryStatus.level === 'close' ? 'text-amber-800' : 'text-slate-600')">
                        <template x-if="categoryStatus.forecast && categoryStatus.left !== null">
                            <span x-text="categoryStatus.left >= 0
                                ? @js(__(':amount left.', ['amount' => '__A__'])).replace('__A__', money(categoryStatus.left)) + ' · ' + categoryStatus.percent + '%'
                                : @js(__(':amount over.', ['amount' => '__A__'])).replace('__A__', money(-categoryStatus.left)) + ' · ' + categoryStatus.percent + '%'"></span>
                        </template>
                        <template x-if="!categoryStatus.forecast || categoryStatus.left === null">
                            <span x-text="categoryStatus.forecast ? categoryStatus.percent + '% ' + @js(__('of the forecast')) : @js(__('no forecast')) + ' · ' + categoryStatus.count + '×'"></span>
                        </template>
                    </p>
                </section>
            </template>

            <form x-ref="financialForm" @submit.prevent="submitForm" method="POST" class="flex flex-1 flex-col"
                action="{{ route('financial-movements.store') }}" enctype="multipart/form-data">
                @csrf
                @include('financial-movements.form', ['busy' => 'isLoading', 'cancel' => 'handleModalCloseManual()'])
            </form>

            <template x-if="lastMovements.length">
                <div class="border-t border-slate-200 px-5 pb-5 pt-4 sm:px-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Registered now') }}</h3>
                    <ul class="mt-2 divide-y divide-slate-100">
                        <template x-for="movement in lastMovements" :key="movement.id">
                            <li class="flex items-center gap-3 py-2 text-sm">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-slate-900" x-text="movement.description"></p>
                                    <p class="truncate text-xs text-slate-500" x-text="movement.date + ' · ' + movement.wallet_id + ' · ' + movement.category_id"></p>
                                </div>
                                <span class="amount font-semibold text-slate-700" x-text="movement.amount"></span>
                                <x-icon-button icon="trash" tone="danger" :label="__('Delete')" x-on:click.prevent="deleteMovement(movement.id)" />
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </x-modal>

    <div x-show="notification" x-cloak x-transition role="status"
        class="fixed inset-x-4 top-4 z-[60] whitespace-pre-line rounded-lg bg-slate-900 px-4 py-3 text-sm text-white shadow-lg sm:inset-x-auto sm:right-4 sm:max-w-sm"
        x-text="notification"></div>

    {{-- mobile: floating "new movement" button (desktop uses the page header button) --}}
    <button type="button" class="fixed bottom-5 right-4 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 hover:bg-brand-700 md:hidden"
        @click.prevent="$dispatch('open-modal', 'modal-create')">
        <i class="fa-solid fa-plus text-lg" aria-hidden="true"></i>
        <span class="sr-only">{{ __('New movement') }}</span>
    </button>
</div>


<script>
    function submitForm() {
        return {
            selectedType: 'income',
            isLoading: false,
            notification: '',
            errorMessages: [],
            lastMovements: [],
            categoryStatus: null,
            wallets: @json($wallets),
            categories: @json($categories),
            types: @json($types),

            money(value) {
                return Number(value).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
            },

            init() {
                // the toast hides itself after a few seconds
                this.$watch('notification', (value) => {
                    clearTimeout(this.notificationTimer);
                    if (value) this.notificationTimer = setTimeout(() => this.notification = '', 6000);
                });
            },

            handleModalCloseManual() {
                if (this.lastMovements.length > 0) {
                    window.location.reload();
                }
                this.$dispatch('close-modal', 'modal-create');
            },

            deleteMovement(id) {
                const baseUrl = "{{ url('/api/financial-movements') }}";
                const url = `${baseUrl}/${id}?confirm_reconciled=1`;
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
                        this.notification = @json(__('Movement deleted.'));
                    }).catch(error => {
                        console.error('Error:', error);
                        this.notification = @json(__('Something went wrong'));
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

                        for (let i = 0; i < data['movements'].length; i++) {
                            const movement = data['movements'][i];

                            movement.wallet_id = this.wallets[movement.wallet_id];
                            movement.category_id = movement.category_id ? this.categories[movement.category_id] : this.types[movement.type];
                            movement.type = this.types[movement.type];

                            movement.amount = parseFloat(movement.amount).toLocaleString('pt-PT', {
                                style: 'currency',
                                currency: 'EUR'
                            });
                            movement.date = new Date(movement.date).toLocaleDateString('pt-PT');
                            this.lastMovements.push(movement);
                        }

                        this.notification = @json(__('Movement saved.'));
                        // how the category stands in the month (null for transfers, loans, adjustments)
                        this.categoryStatus = data.category;
                        if (data.category) this.notification += '\n' + data.category.text;

                        for (let i = 0; i < data['balances'].length; i++) {
                            const balance = data['balances'][i];
                            const calculated = parseFloat(balance.calculated_balance).toLocaleString('pt-PT', {
                                style: 'currency',
                                currency: 'EUR'
                            });
                            this.notification += '\n' + this.wallets[balance.wallet_id] + ': ' + calculated;
                        }
                        // clear the form for the next entry (keeps date, wallet and type)
                        window.dispatchEvent(new CustomEvent('movement-form-reset'));
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.notification = @json(__('Something went wrong'));
                    })
                    .finally(() => this.isLoading = false);
            }
        }
    }
</script>
