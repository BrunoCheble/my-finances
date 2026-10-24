<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900">{{ __('Welcome back') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Sign in to your account.') }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />
    @if (session('error'))
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">
            <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i><span>{{ session('error') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-brand-700 hover:text-brand-800" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 text-sm text-slate-600">
            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" name="remember">
            {{ __('Remember me') }}
        </label>

        <x-primary-button class="w-full justify-center py-2.5">
            {{ __('Log in') }}
        </x-primary-button>
    </form>

    @if (\App\Services\Auth\GoogleSignInService::configured())
        {{-- Sign in with Google (only accounts that already exist) --}}
        <div class="my-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            <span class="h-px flex-1 bg-slate-200"></span>{{ __('or') }}<span class="h-px flex-1 bg-slate-200"></span>
        </div>
        <a href="{{ route('auth.google') }}"
            class="btn w-full justify-center border border-slate-300 bg-white py-2.5 text-base text-slate-800 shadow-sm hover:bg-slate-50 theme-light">
            <x-google-logo class="h-5 w-5" />{{ __('Continue with Google') }}
        </a>
    @endif

    {{-- Fingerprint / Face ID: a passkey registered on the profile, on this device --}}
    <div x-data="passkeyLogin(@js([
            'options' => route('passkeys.sign-in.options'),
            'signIn' => route('passkeys.sign-in'),
            'texts' => [
                'cancelled' => __('Cancelled on the device. Try again.'),
                'already' => __('This device is already registered.'),
                'failed' => __('The fingerprint could not be checked. Try again.'),
            ],
        ]))" x-show="supported" x-cloak>
        @unless (\App\Services\Auth\GoogleSignInService::configured())
            <div class="my-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span class="h-px flex-1 bg-slate-200"></span>{{ __('or') }}<span class="h-px flex-1 bg-slate-200"></span>
            </div>
        @else
            <div class="h-3"></div>
        @endunless
        <button type="button" @click="signIn()" :disabled="busy"
            class="btn w-full justify-center bg-slate-900 py-3 text-base text-white hover:bg-slate-800">
            <i class="fa-solid fa-fingerprint text-xl" aria-hidden="true"></i>
            <span x-text="busy ? @js(__('Waiting for the device…')) : @js(__('Sign in with fingerprint / Face ID'))"></span>
        </button>
        <p class="mt-2 text-center text-sm font-medium text-rose-600" x-show="error" x-text="error" role="alert"></p>
        <p class="mt-2 text-center text-xs text-slate-400">{{ __('First turn it on in your profile, on this device.') }}</p>
    </div>

    <script src="{{ asset('js/passkeys.js') }}?v={{ filemtime(public_path('js/passkeys.js')) }}"></script>
    <script>
        function passkeyLogin({ options, signIn, texts }) {
            return {
                supported: window.Passkeys && Passkeys.supported(),
                busy: false,
                error: '',
                async signIn() {
                    this.busy = true;
                    this.error = '';
                    try {
                        const result = await Passkeys.signIn(options, signIn);
                        location.href = result.redirect;
                    } catch (error) {
                        this.error = Passkeys.message(error, texts);
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
</x-guest-layout>
