{{-- Signing in with fingerprint / Face ID: the devices registered, and registering this one (after the password) --}}
@php($passkeys = Auth::user()->passkeys()->latest()->get())
<section x-data="passkeyDevices(@js([
        'options' => route('passkeys.options'),
        'store' => route('passkeys.store'),
        'texts' => [
            'cancelled' => __('Cancelled on the device. Try again.'),
            'already' => __('This device is already registered.'),
            'failed' => __('The fingerprint could not be checked. Try again.'),
        ],
    ]))">
    <header class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600" aria-hidden="true">
            <i class="fa-solid fa-fingerprint text-lg"></i>
        </span>
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Fingerprint / Face ID') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Sign in on this device with your fingerprint or Face ID, without typing the password. After 15 minutes without use the app locks and asks for it again.') }}</p>
        </div>
    </header>

    @if ($passkeys->isNotEmpty())
        <ul class="mt-5 divide-y divide-slate-100 rounded-xl border border-slate-200">
            @foreach ($passkeys as $passkey)
                <li class="flex items-center gap-3 px-4 py-3">
                    <i class="fa-solid {{ preg_match('/iPhone|Android|iPad/i', $passkey->name) ? 'fa-mobile-screen-button' : 'fa-laptop' }} w-5 text-center text-slate-400" aria-hidden="true"></i>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $passkey->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ __('Added on :date', ['date' => $passkey->created_at->format('d/m/Y')]) }}
                            @if ($passkey->last_used_at) · {{ __('last used on :date', ['date' => $passkey->last_used_at->format('d/m/Y H:i')]) }}@endif
                        </p>
                    </div>
                    <x-icon-button icon="trash" tone="danger" :label="__('Remove')" x-on:click="$dispatch('open-modal', 'passkey-remove-{{ $passkey->id }}')" />
                </li>
            @endforeach
        </ul>
    @endif

    <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600" x-show="!supported" x-cloak>
        {{ __('This browser cannot use the fingerprint here. Open Escudo on the phone or computer with fingerprint / Face ID, over HTTPS.') }}
    </p>

    <form class="mt-5 max-w-xl space-y-4" x-show="supported" @submit.prevent="add()">
        <div>
            <x-input-label for="passkey_name" :value="__('Name of this device')" />
            <x-text-input id="passkey_name" type="text" class="mt-1 block w-full" x-model="name" maxlength="100" required />
        </div>
        <div>
            <x-input-label for="passkey_password" :value="__('Current Password')" />
            <x-text-input id="passkey_password" type="password" class="mt-1 block w-full" x-model="password" autocomplete="current-password" required />
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <x-button type="submit" x-bind:disabled="busy">
                <i class="fa-solid fa-fingerprint" aria-hidden="true"></i>
                <span x-text="busy ? @js(__('Waiting for the device…')) : @js(__('Use the fingerprint on this device'))"></span>
            </x-button>
            <span class="text-sm font-medium" :class="ok ? 'text-emerald-700' : 'text-rose-600'" x-show="message" x-text="message" role="status"></span>
        </div>
    </form>
</section>

@foreach ($passkeys as $passkey)
    <x-modal name="passkey-remove-{{ $passkey->id }}" maxWidth="md">
        <form method="POST" action="{{ route('passkeys.destroy', $passkey) }}" class="space-y-4 p-5 sm:p-6">
            @csrf
            @method('DELETE')
            <h3 class="text-lg font-semibold text-slate-900">{{ __('Remove :device?', ['device' => $passkey->name]) }}</h3>
            <p class="text-sm text-slate-600">{{ __('It will no longer sign in or unlock with the fingerprint.') }}</p>
            <div class="flex justify-end gap-2">
                <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                <x-danger-button>{{ __('Remove') }}</x-danger-button>
            </div>
        </form>
    </x-modal>
@endforeach

@once
    <script src="{{ asset('js/passkeys.js') }}?v={{ filemtime(public_path('js/passkeys.js')) }}"></script>
    <script>
        function passkeyDevices({ options, store, texts }) {
            const ua = navigator.userAgent;
            const guess = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android' : /Mac/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'Windows' : '';
            return {
                supported: window.Passkeys && Passkeys.supported(),
                name: guess,
                password: '',
                busy: false,
                message: '',
                ok: false,
                async add() {
                    this.busy = true;
                    this.message = '';
                    try {
                        await Passkeys.register(options, store, this.password, this.name.trim() || guess || 'Device');
                        this.ok = true;
                        this.message = @js(__('Done: you can sign in with the fingerprint on this device.'));
                        setTimeout(() => location.reload(), 1200);
                    } catch (error) {
                        this.ok = false;
                        this.message = Passkeys.message(error, texts);
                    } finally {
                        this.password = '';
                        this.busy = false;
                    }
                },
            };
        }
    </script>
@endonce
