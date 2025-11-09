<div x-data="deleteSelected()">
    <div>
        <button
            id="delete-selected-btn"
            @click="deleteSelectedMovements"
            :disabled="selected.length === 0"
            class="mb-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white"
            :class="selected.length ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-900 cursor-not-allowed'"
        >
            {{ __('Delete Selected') }} <span x-text="selected.length ? `(${selected.length})` : ''" class="ml-2"></span>
        </button>
    </div>

    <table class="w-full divide-y divide-gray-300">
        <thead>
            <tr>
                <th scope="col" class="px-3 py-3"></th>
                @if (!$walletSection)
                    <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Wallet') }}</th>
                @endif
                <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Date') }}</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Description') }}</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Category') }}</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Type') }}</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">{{ __('Amount') }}</th>
                <th scope="col" class="px-3 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @foreach ($financials as $financial)
                @include('financial-movements.table.row')
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 px-4">
        {!! $financials->withQueryString()->links() !!}
    </div>
</div>

<script>
    function deleteSelected() {
        return {
            selected: [],

            deleteSelectedMovements() {
                this.selected = Array.from(document.querySelectorAll('input[name="selected_financials[]"]:checked')).map(cb => cb.value);
                console.log(this.selected);
                if (this.selected.length === 0) return;
                if (!confirm('Are you sure you want to delete the selected movements?')) return;

                const baseUrl = "{{ url('/api/financial-movements/bulk-delete') }}";

                fetch(baseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({ ids: this.selected }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    this.selected.forEach(id => {
                        const row = document.querySelector(`input[value="${id}"]`)?.closest('tr');
                        if (row) row.remove();
                    });
                    this.selected = [];
                })
                .catch(error => {
                    console.error('Error deleting:', error);
                    alert('An unexpected error occurred.');
                });
            }
        }
    }
</script>
