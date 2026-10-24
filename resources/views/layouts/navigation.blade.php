@php
    $navigation = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'chart-pie', 'label' => __('Dashboard')],
        ['route' => 'financial-movements.index', 'active' => 'financial-movements.*', 'icon' => 'arrow-right-arrow-left', 'label' => __('Financial Movements')],
        ['route' => 'statement-imports.index', 'active' => 'statement-imports.*', 'icon' => 'file-import', 'label' => __('Import Statement')],
        ['route' => 'financial-balances.index', 'active' => 'financial-balances.*', 'icon' => 'chart-line', 'label' => __('Financial Balances')],
        ['route' => 'loans.index', 'active' => 'loans.*', 'icon' => 'hand-holding-dollar', 'label' => __('Loans')],
        ['route' => 'financial-categories.index', 'active' => 'financial-categories.*', 'icon' => 'tags', 'label' => __('Financial Categories')],
        ['route' => 'wallets.index', 'active' => 'wallets.*', 'icon' => 'wallet', 'label' => __('Wallets')],
    ];
    $user = Auth::user();
    $initials = collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp

{{-- Navigation links, shared by the desktop sidebar and the mobile drawer --}}
@php
    $links = function () use ($navigation) {
        $html = '';
        foreach ($navigation as $item) {
            $active = request()->routeIs($item['active']);
            $classes = $active
                ? 'bg-brand-50 text-brand-700'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
            $html .= '<a href="' . e(route($item['route'])) . '" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ' . $classes . '"'
                . ($active ? ' aria-current="page"' : '') . '>'
                . '<i class="fa-solid fa-' . $item['icon'] . ' w-5 text-center ' . ($active ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-500') . '" aria-hidden="true"></i>'
                . e($item['label']) . '</a>';
        }
        return $html;
    };
@endphp

<div x-data="{ drawer: false }" @keydown.escape.window="drawer = false">
    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex" aria-label="{{ __('Main navigation') }}">
        <a href="{{ route('dashboard') }}" class="flex h-16 items-center px-5">
            <x-brand />
        </a>

        <nav class="flex-1 space-y-1 px-3 py-4">{!! $links() !!}</nav>

        <div class="border-t border-slate-200 p-3" x-data="{ menu: false }" @click.outside="menu = false">
            <div class="relative">
                <button type="button" @click="menu = !menu" :aria-expanded="menu"
                    class="flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700" aria-hidden="true">{{ $initials }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-slate-900">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-up text-xs text-slate-400" aria-hidden="true"></i>
                </button>

                <div x-show="menu" x-cloak x-transition
                    class="absolute bottom-full left-0 right-0 mb-2 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    @include('layouts.user-menu')
                </div>
            </div>
        </div>
    </aside>

    {{-- Mobile / tablet top bar --}}
    <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:hidden">
        <button type="button" class="btn-icon -ml-2" @click="drawer = true" :aria-expanded="drawer" aria-controls="mobile-drawer">
            <i class="fa-solid fa-bars text-lg" aria-hidden="true"></i>
            <span class="sr-only">{{ __('Open menu') }}</span>
        </button>
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <x-brand size="sm" />
        </a>
    </header>

    {{-- Mobile / tablet drawer --}}
    <div x-show="drawer" x-cloak class="relative z-40 lg:hidden" role="dialog" aria-modal="true" id="mobile-drawer">
        <div x-show="drawer" x-transition.opacity class="fixed inset-0 bg-slate-900/40" @click="drawer = false"></div>
        <div x-show="drawer"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl">
            <div class="flex h-14 items-center justify-between border-b border-slate-200 px-4">
                <x-brand size="sm" />
                <button type="button" class="btn-icon -mr-2" @click="drawer = false">
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                    <span class="sr-only">{{ __('Close menu') }}</span>
                </button>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="{{ __('Main navigation') }}">{!! $links() !!}</nav>
            <div class="border-t border-slate-200 p-3">
                <div class="flex items-center gap-3 px-2 pb-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700" aria-hidden="true">{{ $initials }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-slate-900">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                    </span>
                </div>
                @include('layouts.user-menu')
            </div>
        </div>
    </div>
</div>
