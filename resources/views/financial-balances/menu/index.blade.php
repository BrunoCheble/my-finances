@php
$menu = $menu ?? ['all'];
@endphp

@include('financial-balances.menu.modal-delete')
@include('financial-balances.menu.modal-edit')

<div class="flex gap-2 justify-end">
    <button class="cursor-pointer bg-blue-500 rounded-full w-8 h-8 flex justify-center items-center text-white" x-data="" x-on:click.prevent="$dispatch('open-modal', 'modal-edit-{{ $balance->id }}')">
        <i class="fas fa-edit"></i>
    </button>
    <button class="cursor-pointer bg-red-500 rounded-full w-8 h-8 flex justify-center items-center text-white" x-data="" x-on:click.prevent="$dispatch('open-modal', 'modal-delete-{{ $balance->id }}')">
        <i class="fas fa-trash"></i>
    </button>

    <form method="POST" action="{{ route('financial-balances.recalculate', $balance->id) }}"  role="form" enctype="multipart/form-data">
        {{ method_field('PATCH') }}
        @csrf
        <button type="submit" class="bg-green-500 rounded-full w-8 h-8 flex justify-center items-center text-white">
        <i class="fas fa-refresh"></i>
        </button>
    </form>

</div>
