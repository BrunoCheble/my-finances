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
