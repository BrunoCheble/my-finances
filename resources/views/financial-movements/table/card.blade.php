@php($target = $financial->originalMovement ?? $financial)
<li data-movement="{{ $financial->id }}" class="flex items-center gap-3 px-4 py-3" x-data="{ menu: false }">
    @include('financial-movements.table.movement-icon')
    <button type="button" class="min-w-0 flex-1 text-left" @click="$dispatch('open-modal', 'modal-edit-{{ $target->id }}')">
        <p class="flex items-center gap-1.5 text-sm font-medium text-slate-900">
            <span class="truncate">{{ $financial->description }}</span>
            @if ($financial->include_alert)
                <i class="fa-solid fa-triangle-exclamation text-xs text-amber-500" aria-label="{{ __('Alert') }}"></i>
            @endif
        </p>
        <p class="truncate text-xs text-slate-500">
            {{ $financial->category?->name ?? $financial->type_name }}
            @if (!$walletSection)
                · <span style="color: {{ $financial->wallet?->color }}">●</span> {{ $financial->wallet?->name }}
            @endif
        </p>
    </button>
    <x-money :value="$financial->amount" class="text-sm font-semibold" />
    <div class="relative" @click.outside="menu = false">
        <x-icon-button icon="ellipsis-vertical" :label="__('Actions')" class="-mr-2" x-on:click="menu = !menu" x-bind:aria-expanded="menu" />
        <div x-show="menu" x-cloak x-transition
            class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                @click="menu = false; $dispatch('open-modal', 'modal-edit-{{ $target->id }}')">
                <i class="fa-solid fa-pen w-4 text-slate-400" aria-hidden="true"></i>{{ __('Edit') }}
            </button>
            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50"
                @click="menu = false; $dispatch('open-modal', 'modal-delete-{{ $target->id }}')">
                <i class="fa-solid fa-trash w-4" aria-hidden="true"></i>{{ __('Delete') }}
            </button>
        </div>
    </div>
</li>
