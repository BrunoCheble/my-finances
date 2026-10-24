@php($target = $financial->originalMovement ?? $financial)
<tr data-movement="{{ $financial->id }}" class="group hover:bg-slate-50" :class="selected.includes('{{ $financial->id }}') && 'bg-brand-50/60'">
    <td class="py-3 pl-4 pr-1">
        <input type="checkbox" name="selected_financials[]" value="{{ $financial->id }}" x-model="selected"
            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            aria-label="{{ __('Select') }} {{ $financial->description }}">
    </td>
    <td class="td whitespace-nowrap text-slate-500">{{ date_format_custom($financial->date) }}</td>
    <td class="td">
        <div class="flex items-center gap-3">
            @include('financial-movements.table.movement-icon')
            <div class="min-w-0">
                <p class="flex items-center gap-2 font-medium text-slate-900">
                    <span class="truncate">{{ $financial->description }}</span>
                    @if ($financial->include_alert)
                        <i class="fa-solid fa-triangle-exclamation text-amber-500" title="{{ __('Alert') }}" aria-label="{{ __('Alert') }}"></i>
                    @endif
                </p>
                <p class="truncate text-xs text-slate-500">
                    {{ $financial->type_name }}
                    <span class="lg:hidden">@if ($financial->category) · {{ $financial->category->name }}@endif</span>
                    @if (!$walletSection)<span class="xl:hidden"> · {{ $financial->wallet?->name }}</span>@endif
                </p>
            </div>
        </div>
    </td>
    <td class="td hidden lg:table-cell">
        @if ($financial->category)
            <x-badge>{{ $financial->category->name }}</x-badge>
        @else
            <span class="text-slate-300">—</span>
        @endif
    </td>
    @if (!$walletSection)
        <td class="td hidden whitespace-nowrap xl:table-cell">
            <span class="inline-flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $financial->wallet?->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                {{ $financial->wallet?->name }}
            </span>
        </td>
    @endif
    <td class="td text-right font-semibold">
        <x-money :value="$financial->amount" />
    </td>
    <td class="py-3 pr-4">
        <div class="flex justify-end gap-1 opacity-100 transition lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100">
            <x-icon-button icon="pen" :label="__('Edit')" x-on:click.prevent="$dispatch('open-modal', 'modal-edit-{{ $target->id }}')" />
            <x-icon-button icon="trash" tone="danger" :label="__('Delete')" x-on:click.prevent="$dispatch('open-modal', 'modal-delete-{{ $target->id }}')" />
        </div>
    </td>
</tr>
