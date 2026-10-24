@php
    // one edit/delete modal per movement; a transfer mirror acts on its original movement
    $targets = $financials->map(fn ($financial) => $financial->originalMovement ?? $financial)->unique('id');
@endphp

<div x-data="deleteSelected()" class="card overflow-hidden">
    {{-- Toolbar: selection actions or summary --}}
    <div class="flex min-h-[3.25rem] items-center justify-between gap-3 border-b border-slate-200 px-4 py-2">
        <div x-show="selected.length === 0" class="text-sm text-slate-500">
            {{ __('Showing :from–:to of :total', ['from' => $financials->firstItem() ?? 0, 'to' => $financials->lastItem() ?? 0, 'total' => $financials->total()]) }}
        </div>
        <div x-show="selected.length > 0" x-cloak class="flex items-center gap-3">
            <span class="text-sm font-medium text-slate-900" x-text="selected.length + ' {{ __('selected') }}'"></span>
            <x-button variant="danger" size="sm" icon="trash" x-on:click="deleteSelectedMovements">{{ __('Delete') }}</x-button>
            <button type="button" class="text-sm font-medium text-slate-500 hover:text-slate-900" @click="selected = []">{{ __('Cancel') }}</button>
        </div>
        <x-icon-button icon="rotate-right" :label="__('Update Table')" x-on:click="window.location.reload()" />
    </div>

    @if ($financials->isEmpty())
        <x-empty-state icon="receipt" :title="__('No movements found')"
            :description="__('Try another wallet or clear the filters, or register a new movement.')" />
    @else
        {{-- Desktop / tablet: table --}}
        <div class="hidden md:block">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="w-10 py-2.5 pl-4 pr-1">
                            <span class="sr-only">{{ __('Select') }}</span>
                        </th>
                        <th scope="col" class="th">{{ __('Date') }}</th>
                        <th scope="col" class="th">{{ __('Description') }}</th>
                        <th scope="col" class="th hidden lg:table-cell">{{ __('Category') }}</th>
                        @if (!$walletSection)
                            <th scope="col" class="th hidden xl:table-cell">{{ __('Wallet') }}</th>
                        @endif
                        <th scope="col" class="th text-right">{{ __('Amount') }}</th>
                        <th scope="col" class="w-24 py-2.5 pr-4"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($financials as $financial)
                        @include('financial-movements.table.row')
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile: list grouped by day --}}
        <div class="pb-20 md:hidden">
            @foreach ($financials->groupBy('date') as $date => $dayMovements)
                <h3 class="sticky top-14 z-10 border-b border-slate-100 bg-slate-50/95 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 backdrop-blur">
                    {{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}
                </h3>
                <ul class="divide-y divide-slate-100">
                    @foreach ($dayMovements as $financial)
                        @include('financial-movements.table.card')
                    @endforeach
                </ul>
            @endforeach
        </div>

        @if ($financials->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {!! $financials->withQueryString()->links() !!}
            </div>
        @endif
    @endif
</div>

@foreach ($targets as $financial)
    @include('financial-movements.menu.modal-delete')
    @include('financial-movements.menu.modal-edit', ['financialMovement' => $financial])
@endforeach

<script>
    function deleteSelected() {
        return {
            selected: [],
            deleteSelectedMovements(confirmed = false) {
                if (this.selected.length === 0) return;
                if (!confirmed && !confirm(@json(__('Are you sure you want to delete the selected movements?')))) return;

                fetch(@json(url('/api/financial-movements/bulk-delete')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({ ids: this.selected, confirm_reconciled: confirmed }),
                    credentials: 'same-origin'
                })
                .then(async response => {
                    if (response.status === 422) {
                        const data = await response.json();
                        const message = data.errors?.confirm_reconciled?.[0];
                        if (message && confirm(message)) this.deleteSelectedMovements(true);
                        throw 'cancelled';
                    }
                    return response.json();
                })
                .then(() => {
                    this.selected.forEach(id => {
                        document.querySelectorAll(`[data-movement="${id}"]`).forEach(el => el.remove());
                    });
                    this.selected = [];
                })
                .catch(error => {
                    if (error === 'cancelled') return;
                    console.error('Error deleting:', error);
                    alert(@json(__('Something went wrong')));
                });
            }
        }
    }
</script>
