<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900">{{ __('Welcome back') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Sign in to your account.') }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

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
</x-guest-layout>
