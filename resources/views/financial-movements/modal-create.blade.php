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
                    <h3 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ __('Registered now') }}
                        <span class="badge bg-slate-100 text-slate-600" x-text="lastMovements.length"></span>
                    </h3>
                    {{-- newest first; only the last few unless expanded --}}
                    <ul class="mt-2 divide-y divide-slate-100">
                        <template x-for="movement in visibleMovements" :key="movement.id">
                            <li class="flex items-center gap-3 py-2 text-sm">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-slate-900" x-text="movement.description"></p>
                                    <p class="truncate text-xs text-slate-500" x-text="movement.date + ' · ' + movement.wallet_id + ' · ' + movement.category_id"></p>
                                </div>
                                <span class="amount font-semibold" :class="movement.value < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="movement.amount"></span>
                                <x-icon-button icon="trash" tone="danger" :label="__('Delete')" x-on:click.prevent="deleteMovement(movement.id)" />
                            </li>
                        </template>
                    </ul>
                    <button type="button" x-show="lastMovements.length > recentLimit" @click="showAllRecent = !showAllRecent" :aria-expanded="showAllRecent.toString()"
                        class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                        <i class="fa-solid fa-chevron-down text-xs transition" :class="showAllRecent && 'rotate-180'" aria-hidden="true"></i>
                        <span x-text="showAllRecent ? @js(__('Show less')) : @js(__('Show all :count', ['count' => '__N__'])).replace('__N__', lastMovements.length)"></span>
                    </button>
                </div>
            </template>
        </div>
    </x-modal>

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
            errorMessages: [],
            lastMovements: [],
            recentLimit: 5,
            showAllRecent: false,
            categoryStatus: null,
            wallets: @json($wallets),
            // color and kind of each wallet, for the balances in the toast
            walletInfo: @json($walletsSidebar->mapWithKeys(fn ($wallet) => [$wallet->id => ['color' => $wallet->color ?? '#94a3b8', 'thirdParty' => $wallet->is_third_party]])),
            categories: @json($categories),
            types: @json($types),

            get visibleMovements() {
                return this.showAllRecent ? this.lastMovements : this.lastMovements.slice(0, this.recentLimit);
            },

            money(value) {
                return Number(value).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
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
                        window.toast({ type: 'info', title: @json(__('Movement deleted.')) });
                    }).catch(error => {
                        console.error('Error:', error);
                        window.toast({ type: 'error', title: @json(__('Something went wrong')) });
                    });
            },

            submitForm() {
                this.isLoading = true;
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

                        const batch = [];
                        for (let i = 0; i < data['movements'].length; i++) {
                            const movement = data['movements'][i];
                            movement.value = Number(movement.amount);

                            movement.wallet_id = this.wallets[movement.wallet_id];
                            movement.category_id = movement.category_id ? this.categories[movement.category_id] : this.types[movement.type];
                            movement.type = this.types[movement.type];

                            movement.amount = parseFloat(movement.amount).toLocaleString('de-DE', {
                                style: 'currency',
                                currency: 'EUR'
                            });
                            movement.date = new Date(movement.date).toLocaleDateString('pt-PT');
                            batch.push(movement);
                        }
                        this.lastMovements.unshift(...batch);

                        // how the category stands in the month (null for transfers, loans, adjustments)
                        this.categoryStatus = data.category;

                        // the toast: what was saved and the balance of each wallet it touched, in its month
                        const saved = batch[0];
                        window.toast({
                            type: 'success',
                            title: @json(__('Movement saved.')),
                            message: saved ? saved.description + ' · ' + saved.amount : '',
                            rows: data.balances.map((balance) => {
                                const info = this.walletInfo[balance.wallet_id] || {};
                                const value = Number(balance.calculated_balance);
                                const start = new Date(balance.start_date + 'T00:00:00');
                                const month = start.toLocaleDateString('pt-PT', { month: 'short' }).replace('.', '') + '/' + String(start.getFullYear()).slice(2);
                                return {
                                    label: this.wallets[balance.wallet_id],
                                    color: info.color,
                                    hint: month,
                                    // third-party money: what is kept for the person
                                    value: info.thirdParty ? @json(__('kept')) + ' ' + this.money(-value) : this.money(value),
                                    tone: info.thirdParty ? 'text-purple-700' : (value < 0 ? 'text-rose-600' : 'text-slate-900'),
                                };
                            }),
                        });
                        // clear the form for the next entry (keeps date, wallet and type)
                        window.dispatchEvent(new CustomEvent('movement-form-reset'));
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        window.toast({ type: 'error', title: @json(__('Something went wrong')) });
                    })
                    .finally(() => this.isLoading = false);
            }
        }
    }
</script>
