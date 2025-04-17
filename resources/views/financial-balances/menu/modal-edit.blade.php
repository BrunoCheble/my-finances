<x-modal name="modal-edit-{{ $balance->id }}" :show="false" focusable>
    <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
    <form method="POST" action="{{ route('financial-balances.update', $balance->id) }}"  role="form" enctype="multipart/form-data">
        {{ method_field('PATCH') }}
        @csrf
        @include('financial-balances.form')
    </form>
    </div>
</x-modal>
