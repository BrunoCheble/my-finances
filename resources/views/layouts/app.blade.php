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
        <!-- font awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

        <!-- theme (light / dark / system) applied before the first paint -->
        <script>try { var t = localStorage.getItem('theme'); if (t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
        <!-- colors for Chart.js, read from the CSS variables of the current theme (inline: page scripts run before the bundle) -->
        <script>
            window.chartInk = () => {
                const style = getComputedStyle(document.documentElement);
                const read = (name) => style.getPropertyValue('--chart-' + name).trim();
                return {
                    muted: read('muted'), grid: read('grid'), tooltip: read('tooltip'),
                    income: read('income'), expense: read('expense'), brand: read('brand'), brandFill: read('brand-fill'),
                };
            };
        </script>
        <!-- remembered sidebar state, applied before the first paint -->
        <script>try { if (localStorage.getItem('sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}</script>

        @include('layouts.pwa-head')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">
            {{ __('Skip to content') }}
        </a>

        @include('layouts.navigation')

        <div class="app-shell min-h-screen transition-[padding] duration-200 lg:pl-64">
            @if (isset($header))
                <header class="border-b border-slate-200 bg-white">
                    <div class="px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            @include('layouts.toasts')

            {{-- locks itself after 15 minutes without use: fingerprint / Face ID or password to continue --}}
            @auth
                @include('layouts.partials.app-lock')
            @endauth

            <main id="content" tabindex="-1" class="focus:outline-none">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
