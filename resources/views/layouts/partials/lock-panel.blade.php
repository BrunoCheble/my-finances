{{-- Unlocking the app (see App\Services\Passkeys\SessionLock): fingerprint / Face ID, or the password, or signing out.
     $overlay: shown over the page (it stays as it was) instead of on the lock screen (which then goes back to it). --}}
@php($hasPasskey = Auth::user()->passkeys()->exists())
<div class="w-full max-w-sm text-center" x-data="lockPanel(@js([
        'options' => route('lock.options'),
        'unlock' => route('lock.unlock'),
        'password' => route('lock.password'),
        'overlay' => $overlay ?? false,
        'hasPasskey' => $hasPasskey,
        'texts' => [
            'cancelled' => __('Cancelled on the device. Try again.'),
            'already' => __('This device is already registered.'),
            'failed' => __('The fingerprint could not be checked. Try again.'),
        ],
    ]))">
    @if ($overlay ?? false)
        <span class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-card ring-1 ring-slate-200">
            <x-application-logo class="h-10 w-10" />
        </span>
    @endif
    <h2 class=" flex items-center justify-center gap-2 text-xl font-semibold text-slate-900">
        <i class="fa-solid fa-lock text-base text-slate-400" aria-hidden="true"></i>{{ __('Escudo locked') }}
    </h2>
    <p class="mt-2 text-sm text-slate-500">
        {{ $hasPasskey
            ? __('Hello, :name. Use your fingerprint or Face ID to continue.', ['name' => Auth::user()->name])
            : __('Hello, :name. Type your password to continue.', ['name' => Auth::user()->name]) }}
    </p>

    <template x-if="hasPasskey">
        <x-button class="mt-6 w-full justify-center py-3 text-base" x-on:click="unlock()" x-bind:disabled="busy">
            <i class="fa-solid fa-fingerprint text-xl" aria-hidden="true"></i>
            <span x-text="busy ? @js(__('Waiting for the device…')) : @js(__('Unlock'))"></span>
        </x-button>
    </template>
    <p class="mt-3 text-sm font-medium text-rose-600" x-show="error" x-text="error" x-cloak role="alert"></p>

    <button type="button" class="mt-4 text-sm font-medium text-slate-500 underline hover:text-slate-800" x-show="hasPasskey && !usePassword"
        @click="usePassword = true; $nextTick(() => $refs.password.focus())">{{ __('Use the password') }}</button>
    <form class="mt-4 flex gap-2" x-show="usePassword" x-cloak @submit.prevent="unlockWithPassword()">
        <input type="password" x-ref="password" x-model="password" autocomplete="current-password" placeholder="{{ __('Password') }}"
            aria-label="{{ __('Password') }}" class="input block min-w-0 flex-1 py-2.5">
        <x-button type="submit" x-bind:disabled="busy || !password">{{ __('Unlock') }}</x-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-800">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>{{ __('Log Out') }}
        </button>
    </form>
</div>

@once
    <script src="{{ asset('js/passkeys.js') }}?v={{ filemtime(public_path('js/passkeys.js')) }}"></script>
    <script>
        // Unlocking: with a passkey of this device for the signed-in user, or the password. Over a page, the page stays
        // as it was (unsaved changes too); on the lock screen, it goes back to the page that was asked for.
        function lockPanel({ options, unlock, password, overlay, hasPasskey, texts }) {
            return {
                busy: false,
                error: '',
                hasPasskey,
                usePassword: !hasPasskey,
                password: '',
                done(result) {
                    this.password = '';
                    this.error = '';
                    if (overlay) window.dispatchEvent(new CustomEvent('app-unlocked'));
                    else location.href = result.redirect;
                },
                failed(error) {
                    // The session ended meanwhile: the page itself shows the way back in.
                    if (error && (error.status === 419 || error.status === 401)) { location.reload(); return; }
                    this.error = Passkeys.message(error, texts);
                },
                async unlock() {
                    if (!Passkeys.supported()) { this.usePassword = true; return; }
                    this.busy = true;
                    this.error = '';
                    try { this.done(await Passkeys.signIn(options, unlock)); } catch (error) { this.failed(error); } finally { this.busy = false; }
                },
                async unlockWithPassword() {
                    this.busy = true;
                    this.error = '';
                    try { this.done(await Passkeys.post(password, { password: this.password })); } catch (error) { this.failed(error); } finally { this.busy = false; }
                },
            };
        }
    </script>
@endonce
