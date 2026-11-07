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
            <form method="POST" action="{{ route('payment-reminders.store') }}" class="grid gap-3 sm:grid-cols-[1fr_7rem_9rem_auto] sm:items-end">
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
                <x-primary-button class="justify-center">{{ __('Add') }}</x-primary-button>
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
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                                <span class="text-sm font-semibold leading-none">{{ $reminder->day }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-slate-900">{{ $reminder->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ __('Every month on day :day', ['day' => $reminder->day]) }}@if ($reminder->amount) · {{ format_currency($reminder->amount) }}@endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('payment-reminders.destroy', $reminder) }}" onsubmit="return confirm(@js(__('Remove this reminder?')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="{{ __('Remove') }} {{ $reminder->name }}">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
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
