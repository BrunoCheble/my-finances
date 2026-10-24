{{-- Confirmation status of one wallet/month; opens its reconciliation modal. --}}
@if ($balance->is_diverged)
    <button type="button" x-data x-on:click="$dispatch('open-modal', 'reconcile-{{ $balance->id }}')"
        class="badge bg-rose-50 text-rose-700 ring-1 ring-rose-200 hover:bg-rose-100">
        <i class="fa-solid fa-lock-open text-[10px]" aria-hidden="true"></i>{{ __('Diverged') }}
        <x-money :value="$balance->reconciliation_difference" :colored="false" class="font-semibold" />
    </button>
@elseif ($balance->is_reconciled)
    <button type="button" x-data x-on:click="$dispatch('open-modal', 'reconcile-{{ $balance->id }}')"
        class="badge bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
        title="{{ __('Confirmed on :date', ['date' => $balance->reconciled_at?->format('d/m/Y')]) }}">
        <i class="fa-solid fa-circle-check text-[10px]" aria-hidden="true"></i>{{ __('Reconciled') }}
    </button>
@endif
