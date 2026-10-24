<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-slate-900 antialiased">
    <div class="flex min-h-screen">
        {{-- Brand panel (large screens) --}}
        <aside class="relative hidden w-[44%] overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <svg class="pointer-events-none absolute -right-24 -top-24 h-[28rem] w-[28rem] text-white/5" viewBox="0 0 100 100" aria-hidden="true">
                <circle cx="50" cy="50" r="50" fill="currentColor" />
            </svg>
            <svg class="pointer-events-none absolute -bottom-32 -left-16 h-[24rem] w-[24rem] text-white/5" viewBox="0 0 100 100" aria-hidden="true">
                <circle cx="50" cy="50" r="50" fill="currentColor" />
            </svg>

            <div class="relative flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white shadow-sm"><x-application-logo class="h-9 w-9" /></span>
                <span class="text-xl font-bold tracking-tight text-white">Escudo</span>
            </div>

            <div class="relative max-w-md">
                <h1 class="text-3xl font-semibold leading-tight text-white xl:text-4xl">{{ __('Your money, clear and under control.') }}</h1>
                <p class="mt-4 text-base text-brand-100">{{ __('Import bank statements, follow every wallet and see where your money goes, month by month.') }}</p>
            </div>

            <p class="relative text-sm text-brand-200">© {{ date('Y') }} Escudo</p>
        </aside>

        {{-- Form --}}
        <main class="flex flex-1 items-center justify-center bg-slate-50 px-4 py-12 sm:px-6">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex justify-center lg:hidden">
                    <x-brand size="lg" />
                </div>
                <div class="card p-6 sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>
</body>

</html>
