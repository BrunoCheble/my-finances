{{-- Confirm a wallet's month, or review what changed after it was confirmed. --}}
@php
    $stored = (float) $balance->getRawOriginal('calculated_balance');
    $monthTitle = ucfirst(\Carbon\Carbon::parse($balance->start_date)->translatedFormat('F Y'));
    $changes = $balance->is_diverged ? \App\Services\ReconciliationService::changesSince($balance) : collect();
    $actions = ['created' => __('Added'), 'updated' => __('Changed'), 'deleted' => __('Deleted')];
@endphp

<x-modal name="reconcile-{{ $balance->id }}" maxWidth="lg">
    <div class="space-y-5 p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ $balance->is_reconciled ? __('Confirmed month') : __('Confirm month') }}
                </h3>
                <p class="text-sm text-slate-500">{{ $balance->wallet_name }} · {{ $monthTitle }}</p>
            </div>
            <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2 -mt-1" x-on:click="$dispatch('close')" />
        </div>

        @if (!$balance->is_reconciled)
            <div class="rounded-xl bg-slate-50 p-4 text-center">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Balance at the end of the month') }}</p>
                <p class="amount mt-1 text-3xl font-semibold text-slate-900">{{ format_currency($stored) }}</p>
            </div>
            <p class="text-sm text-slate-600">{{ __('Compare it with the balance your bank shows at the end of the month. If it matches, confirm it: if something changes this month later, you will be warned and see what changed.') }}</p>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-button variant="secondary" icon="pen" x-on:click="$dispatch('close'); $dispatch('open-modal', 'modal-edit-{{ $balance->id }}')">{{ __('It does not match: adjust') }}</x-button>
                <form method="POST" action="{{ route('financial-balances.reconcile', $balance) }}">
                    @csrf
                    <x-button type="submit" icon="circle-check" class="w-full">{{ __('It matches: confirm') }}</x-button>
                </form>
            </div>
        @else
            <dl @class(['grid gap-3', 'grid-cols-3' => $balance->is_diverged, 'grid-cols-1' => !$balance->is_diverged])>
                <div class="rounded-xl bg-emerald-50 p-3">
                    <dt class="text-xs font-medium text-emerald-800">{{ __('Reconciled') }}</dt>
                    <dd class="amount mt-0.5 text-lg font-semibold text-emerald-900">{{ format_currency($balance->reconciled_balance) }}</dd>
                    <dd class="mt-0.5 text-xs text-emerald-700">
                        {{ $balance->reconciled_at?->format('d/m/Y H:i') }}
                        @if ($balance->reconciled_source === \App\Services\ReconciliationService::SOURCE_STATEMENT) · {{ __('by the statement') }} @endif
                    </dd>
                </div>
                @if ($balance->is_diverged)
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs font-medium text-slate-600">{{ __('Now') }}</dt>
                        <dd class="amount mt-0.5 text-lg font-semibold text-slate-900">{{ format_currency($stored) }}</dd>
                    </div>
                    <div class="rounded-xl bg-rose-50 p-3">
                        <dt class="text-xs font-medium text-rose-700">{{ __('Difference') }}</dt>
                        <dd class="amount mt-0.5 text-lg font-semibold text-rose-700">{{ ($balance->reconciliation_difference > 0 ? '+' : '') . format_currency($balance->reconciliation_difference) }}</dd>
                    </div>
                @endif
            </dl>

            @if ($balance->is_diverged)
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">{{ __('What changed since you confirmed') }}</h4>
                    @if ($changes->isEmpty())
                        <p class="mt-1 text-sm text-slate-500">{{ __('No change was recorded (it may have been made before this feature existed, or by a recalculation).') }}</p>
                    @else
                        <ul class="mt-2 max-h-64 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 text-sm">
                            @foreach ($changes as $change)
                                <li class="flex items-start justify-between gap-3 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-800">{{ $change->description }}</p>
                                        <p class="text-xs text-slate-500">
                                            <x-badge :color="['created' => 'green', 'updated' => 'yellow', 'deleted' => 'red'][$change->action] ?? 'slate'">{{ $actions[$change->action] ?? $change->action }}</x-badge>
                                            {{ __('dated :date', ['date' => date_format_custom($change->date)]) }} · {{ __('on :date', ['date' => $change->created_at->format('d/m/Y H:i')]) }}
                                        </p>
                                    </div>
                                    <p class="amount shrink-0 text-right">
                                        @if ($change->action === 'updated')
                                            <span class="text-slate-400 line-through">{{ format_currency($change->old_amount) }}</span>
                                            <span class="block font-semibold text-slate-900">{{ format_currency($change->new_amount) }}</span>
                                        @elseif ($change->action === 'deleted')
                                            <span class="text-slate-400 line-through">{{ format_currency($change->old_amount) }}</span>
                                        @else
                                            <span class="font-semibold text-slate-900">{{ format_currency($change->new_amount) }}</span>
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <p class="text-sm text-slate-600">{{ __('If the change was a mistake, undo it in the movements. If it is right, accept the new balance. Or create an adjustment to go back to the confirmed value.') }}</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                    <form method="POST" action="{{ route('financial-balances.restore', $balance) }}">
                        @csrf
                        <x-button type="submit" variant="secondary" icon="sliders" class="w-full">{{ __('Adjust back to :amount', ['amount' => format_currency($balance->reconciled_balance)]) }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('financial-balances.reconcile', $balance) }}">
                        @csrf
                        <x-button type="submit" icon="circle-check" class="w-full">{{ __('Accept :amount', ['amount' => format_currency($stored)]) }}</x-button>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('financial-balances.unreconcile', $balance) }}" class="border-t border-slate-100 pt-3 text-right">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-medium text-slate-500 hover:text-rose-700">{{ __('Remove confirmation') }}</button>
            </form>
        @endif
    </div>
</x-modal>
