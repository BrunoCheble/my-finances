<x-modal name="modal-edit-{{ $financial->id }}" :show="false" focusable>
    <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
        <button
            class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 transition duration-150 ease-in-out"
            @click.prevent="$dispatch('close')"
            aria-label="{{ __('Close') }}"
        >
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <h2 class="text-lg font-bold text-gray-900">{{ __('Edit movement') }}</h2>

        <form method="POST" action="{{ route('financial-movements.update', $financial->id) }}"  role="form" enctype="multipart/form-data">
            {{ method_field('PATCH') }}
            @csrf
            @include('financial-movements.form')
        </form>
    </div>
</x-modal>
