@php
$menu = $menu ?? ['all'];
@endphp

@include('financial-movements.menu.modal-delete')
@include('financial-movements.menu.modal-edit', ['financialMovement' => $financial->originalMovement ?? $financial])

<div class="flex gap-4">
    <!-- action link, delete and edit -->
    <button class="cursor-pointer bg-blue-500 rounded-full w-8 h-8 flex justify-center items-center text-white" x-data="" x-on:click.prevent="$dispatch('open-modal', 'modal-edit-{{ $financial->originalMovement ? $financial->originalMovement->id : $financial->id }}')">
        <i class="fas fa-edit"></i>
    </button>
    <button class="cursor-pointer bg-red-500 rounded-full w-8 h-8 flex justify-center items-center text-white" x-data="" x-on:click.prevent="$dispatch('open-modal', 'modal-delete-{{ $financial->originalMovement ? $financial->originalMovement->id : $financial->id  }}')">
        <i class="fas fa-trash"></i>
    </button>

</div>
