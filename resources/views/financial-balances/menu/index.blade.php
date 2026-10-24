@php
$menu = $menu ?? ['all'];
@endphp

{{-- pass modals => false when the modals are rendered elsewhere --}}
@if ($modals ?? true)
    @include('financial-balances.menu.modal-delete')
    @include('financial-balances.menu.modal-edit')
@endif

<div class="flex justify-end gap-1">
    <x-icon-button icon="pen" :label="__('Adjust balance')" x-data x-on:click.prevent="$dispatch('open-modal', 'modal-edit-{{ $balance->id }}')" />
    <form method="POST" action="{{ route('financial-balances.recalculate', $balance->id) }}">
        @method('PATCH')
        @csrf
        <x-icon-button type="submit" icon="rotate" :label="__('Recalculate')" />
    </form>
    <x-icon-button icon="trash" tone="danger" :label="__('Delete')" x-data x-on:click.prevent="$dispatch('open-modal', 'modal-delete-{{ $balance->id }}')" />
</div>
