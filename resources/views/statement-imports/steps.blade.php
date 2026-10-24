{{-- Upload → Review → Import. $current: 1, 2 or 3 (3 = all done). --}}
@php
    $steps = [__('Upload'), __('Review'), __('Import')];
@endphp
<ol class="flex items-center gap-2 text-xs font-medium sm:text-sm" aria-label="{{ __('Steps') }}">
    @foreach ($steps as $i => $step)
        @php
            $state = $i + 1 < $current || $current === 3 ? 'done' : ($i + 1 === $current ? 'current' : 'next');
        @endphp
        <li class="flex items-center gap-2" @if ($state === 'current') aria-current="step" @endif>
            <span @class(['flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                'bg-emerald-500 text-white' => $state === 'done', 'bg-brand-600 text-white ring-4 ring-brand-100' => $state === 'current', 'bg-slate-200 text-slate-500' => $state === 'next'])>
                @if ($state === 'done')<i class="fa-solid fa-check" aria-hidden="true"></i>@else{{ $i + 1 }}@endif
            </span>
            <span @class(['text-slate-900' => $state !== 'next', 'text-slate-400' => $state === 'next'])>{{ $step }}</span>
        </li>
        @if (!$loop->last)<li class="h-px w-6 bg-slate-300 sm:w-10" aria-hidden="true"></li>@endif
    @endforeach
</ol>
