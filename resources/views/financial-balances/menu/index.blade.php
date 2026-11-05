@php
$menu = $menu ?? ['all'];
@endphp

{{-- pass modals => false when the modals are rendered elsewhere --}}
@if ($modals ?? true)
    @include('financial-balances.menu.modal-edit')
    @include('financial-balances.menu.modal-dates')
@endif

<div class="flex justify-end gap-1">
    @unless ($balance->is_reconciled)
        <x-icon-button icon="circle-check" :label="__('Confirm month')" x-data x-on:click.prevent="$dispatch('open-modal', 'reconcile-{{ $balance->id }}')" />
    @endunless
    <x-icon-button icon="calendar-days" :label="__('Edit dates')" x-data x-on:click.prevent="$dispatch('open-modal', 'modal-dates-{{ $balance->id }}')" />
    <x-icon-button icon="pen" :label="__('Adjust balance')" x-data x-on:click.prevent="$dispatch('open-modal', 'modal-edit-{{ $balance->id }}')" />
    <form method="POST" action="{{ route('financial-balances.recalculate', $balance->id) }}">
        @method('PATCH')
        @csrf
        <x-icon-button type="submit" icon="rotate" :label="__('Recalculate')" />
    </form>
    {{-- only offered when nothing moved in the period; the server checks the movements --}}
    @if ($balance->total_expense == 0 && $balance->total_income == 0 && $balance->total_unidentified == 0)
        <form method="POST" action="{{ route('financial-balances.destroy', $balance) }}" x-data
            x-on:submit="if (!confirm(@js(__('Remove the balance of :wallet? It has no movements.', ['wallet' => $balance->wallet_name])))) $event.preventDefault()">
            @method('DELETE')
            @csrf
            <x-icon-button type="submit" icon="trash" tone="danger" :label="__('Remove')" />
        </form>
    @endif
</div>
