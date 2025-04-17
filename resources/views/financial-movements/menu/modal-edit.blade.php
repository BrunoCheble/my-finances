<x-modal name="modal-edit-{{ $financial->id }}" :show="false" focusable>
    <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
    <form method="POST" action="{{ route('financial-movements.update', $financial->id) }}"  role="form" enctype="multipart/form-data">
        {{ method_field('PATCH') }}
        @csrf
        @include('financial-movements.form')
    </form>
    </div>
</x-modal>
