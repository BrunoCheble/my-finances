@php
    $reopen = old('_modal') ?? $openModal;
    $titles = [
        'own' => [__('Your accounts'), __('Banks and cards that are yours.')],
        'third_party' => [__('Third-party money'), __('Money of other people kept in your banks.')],
        'inactive' => [__('Inactive wallets'), __('Kept for the history.')],
    ];
    $typeLabels = \App\Models\Wallet::types();
    $all = collect($groups)->flatten(1);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Wallets')" :subtitle="__('Your accounts, cards and money you keep for others.')">
            <x-slot name="actions">
                <x-button icon="plus" x-data x-on:click="$dispatch('open-modal', 'wallet-new')">{{ __('New wallet') }}</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-8 px-4 py-6 sm:px-6 lg:px-8" x-data x-init="@if ($reopen) $nextTick(() => $dispatch('open-modal', @js($reopen))) @endif">
        @if ($all->isEmpty())
            <x-card>
                <x-empty-state icon="wallet" :title="__('No wallets yet')" :description="__('Create your first bank account or card to start registering movements.')">
                    <x-button class="mt-4" icon="plus" x-on:click="$dispatch('open-modal', 'wallet-new')">{{ __('New wallet') }}</x-button>
                </x-empty-state>
            </x-card>
        @endif

        @foreach ($groups as $key => $wallets)
            @continue($wallets->isEmpty())
            <section aria-labelledby="group-{{ $key }}" @if ($key === 'inactive') x-data="{ open: false }" @endif>
                <div class="mb-3">
                    @if ($key === 'inactive')
                        <button type="button" id="group-{{ $key }}" @click="open = !open" :aria-expanded="open" class="flex items-center gap-2 text-base font-semibold text-slate-900">
                            {{ $titles[$key][0] }} <x-badge>{{ $wallets->count() }}</x-badge>
                            <i class="fa-solid fa-chevron-down text-xs text-slate-400 transition" :class="open && 'rotate-180'" aria-hidden="true"></i>
                        </button>
                    @else
                        <h2 id="group-{{ $key }}" class="text-base font-semibold text-slate-900">{{ $titles[$key][0] }}</h2>
                    @endif
                    <p class="text-sm text-slate-500">{{ $titles[$key][1] }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" @if ($key === 'inactive') x-show="open" x-cloak @endif>
                    @foreach ($wallets as $wallet)
                        <article class="card relative flex flex-col overflow-hidden p-4 sm:p-5">
                            <span class="absolute inset-x-0 top-0 h-1" style="background-color: {{ $wallet->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-slate-900">{{ $wallet->name }}</h3>
                                    <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                        <i class="fa-solid fa-{{ ['debit' => 'building-columns', 'credit' => 'credit-card', 'third_party' => 'user-shield'][$wallet->type] ?? 'wallet' }}" aria-hidden="true"></i>
                                        {{ $typeLabels[$wallet->type] ?? $wallet->type }}
                                    </p>
                                </div>
                                <div class="-mr-2 -mt-1 flex">
                                    <x-icon-button icon="pen" :label="__('Edit')" x-on:click="$dispatch('open-modal', 'wallet-edit-{{ $wallet->id }}')" />
                                    @if ($wallet->movements_count > 0)
                                        <span class="btn-icon cursor-not-allowed opacity-40" title="{{ __('Has movements: deactivate it instead of deleting.') }}">
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                            <span class="sr-only">{{ __('Cannot delete: has movements') }}</span>
                                        </span>
                                    @else
                                        <x-icon-button icon="trash" tone="danger" :label="__('Delete')" x-on:click="$dispatch('open-modal', 'wallet-delete-{{ $wallet->id }}')" />
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4">
                                @if ($wallet->is_third_party)
                                    <p class="text-xs text-slate-500">{{ __('Kept for someone else') }}</p>
                                    <p class="amount text-2xl font-semibold text-purple-700">{{ format_currency(-$wallet->balance) }}</p>
                                @else
                                    <p class="text-xs text-slate-500">{{ __('Balance') }}</p>
                                    <p @class(['amount text-2xl font-semibold', 'text-rose-600' => $wallet->balance < 0 && $wallet->type === 'debit', 'text-slate-900' => !($wallet->balance < 0 && $wallet->type === 'debit')])>
                                        {{ format_currency($wallet->balance) }}
                                    </p>
                                @endif
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                                <a href="{{ route('financial-movements.index', ['wallet_id' => $wallet->id]) }}" class="font-medium text-brand-700 hover:text-brand-800">
                                    {{ trans_choice(':count movement|:count movements', $wallet->movements_count, ['count' => $wallet->movements_count]) }}
                                </a>
                                @if ((float) $wallet->reserved_amount != 0)
                                    <x-badge color="yellow">{{ __('held :amount', ['amount' => format_currency(abs($wallet->reserved_amount))]) }}</x-badge>
                                @endif
                            </div>
                        </article>

                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    @include('wallets.form-modal', ['wallet' => null])
    {{-- modals outside the (possibly hidden) groups so they always open --}}
    @foreach ($all as $wallet)
        @include('wallets.form-modal', ['wallet' => $wallet])
        @if ($wallet->movements_count === 0)
            <x-modal name="wallet-delete-{{ $wallet->id }}" maxWidth="md">
                <form method="POST" action="{{ route('wallets.destroy', $wallet) }}" class="space-y-4 p-5 sm:p-6">
                    @csrf
                    @method('DELETE')
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Delete :wallet?', ['wallet' => $wallet->name]) }}</h3>
                    <p class="text-sm text-slate-600">{{ __('This wallet has no movements and will be removed.') }}</p>
                    <div class="flex justify-end gap-2">
                        <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                        <x-danger-button>{{ __('Delete') }}</x-danger-button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endforeach
</x-app-layout>
