<x-modal name="modal-edit-{{ $balance->id }}" :show="false" focusable>
    <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
        <h2 class="text-lg font-bold text-gray-900">{{ $balance->wallet->name }} - {{ date_format_custom($balance->start_date) }} - {{ date_format_custom($balance->end_date) }}</h2>
        <form method="POST" action="{{ route('financial-balances.update', $balance->id) }}"  role="form" enctype="multipart/form-data">
            {{ method_field('PATCH') }}
            @csrf
            @include('financial-balances.edit-form')
        </form>
    </div>
</x-modal>
