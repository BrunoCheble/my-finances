{{-- Sign in with Google: link or unlink the Google account of the signed-in user --}}
@php
    $linked = Auth::user()->google_id !== null;
@endphp
<section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <header class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-slate-200 theme-light" aria-hidden="true">
            <x-google-logo class="h-5 w-5" />
        </span>
        <div>
            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                Google
                @if ($linked)<x-badge color="green"><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>{{ __('Linked') }}</x-badge>@endif
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ $linked
                    ? __('You can sign in with your Google account.')
                    : __('Sign in with your Google account. Google accounts with the same email as yours are recognized automatically.') }}
            </p>
        </div>
    </header>
    @if ($linked)
        <form method="POST" action="{{ route('auth.google.unlink') }}" class="shrink-0">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="secondary">{{ __('Unlink') }}</x-button>
        </form>
    @elseif (\App\Services\Auth\GoogleSignInService::configured())
        <x-button :href="route('auth.google')" variant="secondary" class="theme-light shrink-0 whitespace-nowrap">
            <x-google-logo class="h-4 w-4" />{{ __('Link Google') }}
        </x-button>
    @endif
</section>
