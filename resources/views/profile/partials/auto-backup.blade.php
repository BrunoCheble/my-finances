{{-- Automatic backup: a token for the scheduled GitHub workflow, which keeps the data encrypted --}}
@php
    $active = Auth::user()->backup_token_hash !== null;
    $newToken = session('backup_token');
@endphp
<section class="space-y-4">
    <header class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600" aria-hidden="true">
            <i class="fa-solid fa-cloud-arrow-up"></i>
        </span>
        <div>
            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                {{ __('Automatic backup') }}
                @if ($active)<x-badge color="green"><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>{{ __('On') }}</x-badge>@endif
            </h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Every day a scheduled job checks your data and, when something changed, keeps an encrypted copy outside this server for 90 days.') }}</p>
        </div>
    </header>

    @if ($newToken)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
            <p class="font-medium">{{ __('Copy the token now: it is shown only once.') }}</p>
            <div class="mt-2 flex gap-2">
                <input type="text" readonly value="{{ $newToken }}" id="backup-token" class="input w-full font-mono text-xs" aria-label="{{ __('Backup token') }}" onclick="this.select()">
                <x-button size="sm" variant="secondary" icon="copy" onclick="navigator.clipboard.writeText(document.getElementById('backup-token').value)">{{ __('Copy') }}</x-button>
            </div>
            <p class="mt-2">{{ __('Save it in the repository secret BACKUP_TOKEN:') }} <code class="font-mono text-xs">gh secret set BACKUP_TOKEN</code></p>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('backup.token') }}" @if ($active) onsubmit="return confirm(@js(__('The current token stops working. Continue?')))" @endif>
            @csrf
            <x-button type="submit" variant="secondary" icon="key">{{ $active ? __('New token') : __('Create token') }}</x-button>
        </form>
        @if ($active)
            <form method="POST" action="{{ route('backup.revoke') }}" onsubmit="return confirm(@js(__('Turn off the automatic backup?')))">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="secondary">{{ __('Turn off') }}</x-button>
            </form>
        @endif
    </div>
</section>
