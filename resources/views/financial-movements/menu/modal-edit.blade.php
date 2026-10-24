<x-modal name="modal-edit-{{ $financial->id }}" :show="false" sheet>
    <div class="flex flex-1 flex-col">
        <div class="flex items-start justify-between gap-4 px-5 pb-4 pt-5 sm:px-6">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Edit movement') }}</h2>
                <p class="truncate text-sm text-slate-500">{{ $financial->description }}</p>
            </div>
            <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click.prevent="$dispatch('close')" />
        </div>

        <form method="POST" action="{{ route('financial-movements.update', $financial->id) }}" class="flex flex-1 flex-col">
            @method('PATCH')
            @csrf
            @include('financial-movements.form')
        </form>
    </div>
</x-modal>
