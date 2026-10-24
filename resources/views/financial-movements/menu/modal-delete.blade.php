@php
    $lastConfirmed = $reconciledMonths ?? \App\Services\ReconciliationService::lastReconciledMonths();
    $touchesConfirmed = collect([$financial, $financial->destinationMovement])->filter()
        ->contains(fn ($m) => isset($lastConfirmed[$m->wallet_id]) && substr($m->date, 0, 7) <= $lastConfirmed[$m->wallet_id]);
@endphp
<x-modal name="modal-delete-{{ $financial->id }}" :show="false" focusable>
    <form method="post" action="{{ route('financial-movements.destroy', $financial->id) }}" class="p-6">
        @csrf
        @method('delete')

        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Are you sure you want to delete this transaction?') }}
        </h2>

        @if ($touchesConfirmed)
            <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm" role="alert">
                <p class="flex items-start gap-2 font-semibold text-amber-900">
                    <i class="fa-solid fa-lock mt-0.5" aria-hidden="true"></i>
                    {{ __('This movement is in a month you already confirmed.') }}
                </p>
                <label class="mt-3 flex cursor-pointer items-center gap-2 pl-6 font-medium text-amber-900">
                    <input type="checkbox" name="confirm_reconciled" value="1" required class="h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                    {{ __('Yes, change a confirmed month') }}
                </label>
            </div>
        @endif

        <div class="mt-6 flex justify-end">
            <x-secondary-button x-on:click="$dispatch('close')">
                {{ __('Close') }}
            </x-secondary-button>

            <x-danger-button class="ms-3">
                {{  __('Delete') }}
            </x-danger-button>
        </div>
    </form>
</x-modal>
