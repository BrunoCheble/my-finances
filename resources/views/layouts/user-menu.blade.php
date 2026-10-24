{{-- Theme: light, dark or follow the system --}}
<div class="px-3 pb-2 pt-1.5" x-data="{ theme: window.escudoTheme.get() }">
    <p id="theme-label-{{ $menuId ?? 'menu' }}" class="mb-1.5 text-xs font-medium text-slate-500">{{ __('Theme') }}</p>
    <div role="radiogroup" aria-labelledby="theme-label-{{ $menuId ?? 'menu' }}" class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1">
        @foreach (['light' => ['sun', __('Light')], 'dark' => ['moon', __('Dark')], 'system' => ['desktop', __('System')]] as $value => [$icon, $label])
            <button type="button" role="radio" :aria-checked="(theme === '{{ $value }}').toString()"
                @click="theme = '{{ $value }}'; window.escudoTheme.set('{{ $value }}')"
                :class="theme === '{{ $value }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                class="flex flex-col items-center gap-0.5 rounded-md px-1 py-1.5 text-[11px] font-medium transition">
                <i class="fa-solid fa-{{ $icon }} text-xs" aria-hidden="true"></i>{{ $label }}
            </button>
        @endforeach
    </div>
</div>
<div class="my-1 border-t border-slate-100"></div>
<a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
    <i class="fa-solid fa-user w-4 text-center text-slate-400" aria-hidden="true"></i>{{ __('Profile') }}
</a>
<form method="POST" action="{{ route('backup.save') }}">
    @csrf
    <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100">
        <i class="fa-solid fa-cloud-arrow-down w-4 text-center text-slate-400" aria-hidden="true"></i>{{ __('Save Backup') }}
    </button>
</form>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100">
        <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center text-slate-400" aria-hidden="true"></i>{{ __('Log Out') }}
    </button>
</form>
