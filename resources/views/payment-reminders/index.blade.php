<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Payment reminders')" :subtitle="__('A notification on the morning of each payment day.')" />
    </x-slot>

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        {{-- Notifications on this device --}}
        <section class="card p-4 sm:p-5" aria-labelledby="push-title" x-data="pushSetup(@js($vapidKey))">
            <div class="flex flex-wrap items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600" aria-hidden="true">
                    <i class="fa-solid fa-bell"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 id="push-title" class="font-semibold text-slate-900">{{ __('Notifications on this device') }}</h2>
                    <p class="text-sm text-slate-500" role="status" x-text="message"></p>
                </div>
                <div class="flex gap-2">
                    <x-button size="sm" icon="bell" x-show="state === 'off'" x-cloak x-on:click="enable()">{{ __('Turn on') }}</x-button>
                    <x-button size="sm" variant="secondary" icon="paper-plane" x-show="state === 'on'" x-cloak x-on:click="test()">{{ __('Send a test') }}</x-button>
                </div>
            </div>
        </section>

        {{-- New reminder --}}
        <section class="card p-4 sm:p-5" aria-labelledby="new-title">
            <h2 id="new-title" class="mb-3 font-semibold text-slate-900">{{ __('New reminder') }}</h2>
            <form method="POST" action="{{ route('payment-reminders.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_7rem_9rem_1fr_auto] sm:items-end">
                @csrf
                <div>
                    <x-input-label for="reminder-name" :value="__('Name')" />
                    <x-text-input id="reminder-name" name="name" class="mt-1 w-full" :value="old('name')" required maxlength="255" placeholder="{{ __('e.g. Rent') }}" />
                </div>
                <div>
                    <x-input-label for="reminder-day" :value="__('Day of the month')" />
                    <x-text-input id="reminder-day" name="day" type="number" min="1" max="31" class="mt-1 w-full" :value="old('day')" required />
                </div>
                <div>
                    <x-input-label for="reminder-amount" :value="__('Amount (optional)')" />
                    <x-text-input id="reminder-amount" name="amount" type="number" step="0.01" min="0" class="mt-1 w-full" :value="old('amount')" />
                </div>
                <div>
                    <x-input-label for="reminder-reference" :value="__('Reference in the description (optional)')" />
                    <x-text-input id="reminder-reference" name="reference" class="mt-1 w-full" :value="old('reference')" maxlength="255" placeholder="{{ __('e.g. RENDA') }}" />
                </div>
                <x-primary-button class="justify-center">{{ __('Add') }}</x-primary-button>
                <p class="text-xs text-slate-500 sm:col-span-2 lg:col-span-5">{{ __('With a reference, the reminder is marked as paid when a movement of the month has it in the description (no push then).') }}</p>
            </form>
        </section>

        {{-- Reminders --}}
        <section aria-labelledby="list-title">
            <h2 id="list-title" class="mb-3 text-base font-semibold text-slate-900">{{ __('Reminders') }}</h2>
            @if ($reminders->isEmpty())
                <x-card>
                    <x-empty-state icon="calendar-day" :title="__('No reminders yet')" :description="__('Add the payments that come every month: rent, credit card, insurance...')" />
                </x-card>
            @else
                <ul class="card divide-y divide-slate-100">
                    @foreach ($reminders as $reminder)
                        @php
                            $payment = $reminder->paymentIn($month);
                            $due = $reminder->dueDate($month);
                            $late = !$payment && $due->lt(now()->startOfDay());
                        @endphp
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-sm font-semibold {{ $payment ? 'bg-emerald-50 text-emerald-700' : ($late ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700') }}" aria-hidden="true">
                                {{ $reminder->day }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-slate-900">{{ $reminder->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ __('Every month on day :day', ['day' => $reminder->day]) }}@if ($reminder->amount) · {{ format_currency($reminder->amount) }}@endif
                                    @if ($reminder->reference) · {{ __('reference') }} “{{ $reminder->reference }}”@endif
                                </p>
                                <p class="mt-0.5 text-xs font-medium {{ $payment ? 'text-emerald-700' : ($late ? 'text-amber-700' : 'text-slate-500') }}">
                                    @if ($payment && $payment['movement'])
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                        {{ __('Paid on :date', ['date' => date_format_custom($payment['movement']->date)]) }} · {{ format_currency(abs($payment['movement']->amount)) }} · {{ $payment['movement']->description }}
                                    @elseif ($payment)
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ __('Marked as paid this month') }}
                                    @elseif ($late)
                                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ __('Late: it was due on :date', ['date' => date_format_custom($due->toDateString())]) }}
                                    @else
                                        {{ __('Due on :date', ['date' => date_format_custom($due->toDateString())]) }}
                                    @endif
                                </p>
                            </div>
                            <div class="ml-[3.25rem] flex w-full items-center gap-1 sm:ml-0 sm:w-auto">
                                @if (!$payment || $payment['manual'])
                                    <form method="POST" action="{{ route('payment-reminders.paid', $reminder) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-button size="sm" variant="secondary" type="submit" :icon="$payment ? 'rotate-left' : 'check'">
                                            {{ $payment ? __('Unmark') : __('Mark as paid') }}
                                        </x-button>
                                    </form>
                                @endif
                                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="{{ __('Edit') }} {{ $reminder->name }}"
                                    x-data x-on:click="$dispatch('open-modal', 'reminder-edit-{{ $reminder->id }}')">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                </button>
                                <form method="POST" action="{{ route('payment-reminders.destroy', $reminder) }}" onsubmit="return confirm(@js(__('Remove this reminder?')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="{{ __('Remove') }} {{ $reminder->name }}">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>

                            <x-modal name="reminder-edit-{{ $reminder->id }}" maxWidth="md">
                                <form method="POST" action="{{ route('payment-reminders.update', $reminder) }}" class="space-y-4 p-5 sm:p-6">
                                    @csrf
                                    @method('PUT')
                                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Edit reminder') }}</h3>
                                    <div>
                                        <x-input-label for="edit-name-{{ $reminder->id }}" :value="__('Name')" />
                                        <x-text-input id="edit-name-{{ $reminder->id }}" name="name" class="mt-1 w-full" :value="$reminder->name" required maxlength="255" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label for="edit-day-{{ $reminder->id }}" :value="__('Day of the month')" />
                                            <x-text-input id="edit-day-{{ $reminder->id }}" name="day" type="number" min="1" max="31" class="mt-1 w-full" :value="$reminder->day" required />
                                        </div>
                                        <div>
                                            <x-input-label for="edit-amount-{{ $reminder->id }}" :value="__('Amount (optional)')" />
                                            <x-text-input id="edit-amount-{{ $reminder->id }}" name="amount" type="number" step="0.01" min="0" class="mt-1 w-full" :value="$reminder->amount" />
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label for="edit-reference-{{ $reminder->id }}" :value="__('Reference in the description (optional)')" />
                                        <x-text-input id="edit-reference-{{ $reminder->id }}" name="reference" class="mt-1 w-full" :value="$reminder->reference" maxlength="255" placeholder="{{ __('e.g. RENDA') }}" />
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                                    </div>
                                </form>
                            </x-modal>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <script>
        // Web Push on this device: asks permission, subscribes with the server's key and saves the subscription
        function pushSetup(vapidKey) {
            const texts = {
                unsupported: @js(__('This browser does not support notifications. On iPhone, install the app on the home screen first.')),
                denied: @js(__('Notifications are blocked for this site: allow them in the browser settings.')),
                off: @js(__('Turn them on to be notified on this device.')),
                on: @js(__('On: you will be notified on the morning of each payment day.')),
                failed: @js(__('Could not turn on the notifications.')),
                sent: @js(__('Test sent.')),
            };
            const toKey = (base64) => {
                const raw = atob((base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from(raw, (c) => c.charCodeAt(0));
            };
            const post = (url, body) => fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(body || {}),
            });
            return {
                state: 'off',
                message: '',
                async init() {
                    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return this.set('unsupported');
                    if (Notification.permission === 'denied') return this.set('denied');
                    const registration = await navigator.serviceWorker.ready;
                    const subscription = await registration.pushManager.getSubscription();
                    if (subscription) {
                        await post(@js(route('push-subscriptions.store')), subscription.toJSON());
                        return this.set('on');
                    }
                    this.set('off');
                },
                set(state) {
                    this.state = state;
                    this.message = texts[state];
                },
                async enable() {
                    try {
                        if (await Notification.requestPermission() !== 'granted') return this.set('denied');
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(vapidKey) });
                        const response = await post(@js(route('push-subscriptions.store')), subscription.toJSON());
                        if (!response.ok) throw new Error(response.status);
                        this.set('on');
                    } catch (error) {
                        this.message = texts.failed;
                    }
                },
                async test() {
                    const response = await post(@js(route('push-subscriptions.test')));
                    this.message = response.ok ? texts.sent : texts.failed;
                },
            };
        }
    </script>
</x-app-layout>
