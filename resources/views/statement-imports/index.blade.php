<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Statement') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                @include('layouts.alert')
                <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('New import') }}</h1>
                <p class="mt-2 text-sm text-gray-700">{{ __('Upload the bank statement PDF. The file is only read to build the preview and is not stored.') }}</p>

                <form method="POST" action="{{ route('statement-imports.store') }}" enctype="multipart/form-data" class="mt-6" x-data="statementUpload()">
                    @csrf
                    <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                        <div class="flex-1">
                            <x-input-label for="file" :value="__('Statement (PDF)')" />
                            <input id="file" name="file" type="file" accept="application/pdf" required @change="identify($event.target.files[0])"
                                class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                            <x-input-error class="mt-2" :messages="$errors->get('file')" />
                        </div>
                        <div class="flex-1">
                            <x-input-label for="wallet_id" :value="__('Wallet')" />
                            <x-dropdown-select :options="$wallets" name="wallet_id" x-model="walletId" required />
                            <x-input-error class="mt-2" :messages="$errors->get('wallet_id')" />
                        </div>
                        <x-primary-button x-bind:disabled="loading" class="disabled:opacity-50">{{ __('Read statement') }}</x-primary-button>
                    </div>

                    <div class="mt-4 text-sm" x-show="loading || info || error" x-cloak>
                        <p x-show="loading" class="text-gray-500">{{ __('Reading statement...') }}</p>
                        <p x-show="error" class="text-red-600" x-text="error"></p>
                        <template x-if="info">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-indigo-100 px-2 py-1 font-medium text-indigo-800"
                                    x-text="info.bank_name + (info.account_last4 ? ' · {{ __('account') }} ••••' + info.account_last4 : '')"></span>
                                <span class="text-gray-600" x-text="info.lines + ' {{ __('movements') }}'"></span>
                                <span x-show="info.wallet_id" class="text-green-700" x-text="'→ ' + info.wallet_name + ' ({{ __('remembered') }})'"></span>
                                <span x-show="!info.wallet_id" class="text-yellow-700">{{ __('New account: choose the wallet, it will be remembered next time.') }}</span>
                            </div>
                        </template>
                    </div>
                </form>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('Previous imports') }}</h1>
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full divide-y divide-gray-300">
                        <thead>
                            <tr>
                                <th class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Date') }}</th>
                                <th class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Wallet') }}</th>
                                <th class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Period') }}</th>
                                <th class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Lines') }}</th>
                                <th class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($imports as $import)
                                <tr class="even:bg-gray-50">
                                    <td class="py-3 pl-4 pr-3 text-sm text-gray-700">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-3 pl-4 pr-3 text-sm text-gray-700">{{ $import->wallet->name }}
                                        <span class="text-xs uppercase text-gray-400">{{ $import->bank }}@if ($import->account_last4) · ••••{{ $import->account_last4 }}@endif</span>
                                    </td>
                                    <td class="py-3 pl-4 pr-3 text-sm text-gray-700">
                                        @if ($import->period_start)
                                            {{ date_format_custom($import->period_start) }} - {{ date_format_custom($import->period_end) }}
                                        @endif
                                    </td>
                                    <td class="py-3 pl-4 pr-3 text-sm text-gray-700">{{ $import->lines_count }}</td>
                                    <td class="py-3 pl-4 pr-3 text-sm">
                                        @if ($import->is_pending)
                                            <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800">{{ __('Pending review') }}</span>
                                        @else
                                            <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Confirmed') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pl-4 pr-3 text-right text-sm">
                                        <a href="{{ route('statement-imports.show', $import) }}" class="font-semibold text-indigo-600 hover:text-indigo-500">{{ __('Open') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-sm text-gray-500">{{ __('No imports yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function statementUpload() {
            return {
                walletId: @json((string) old('wallet_id', '')),
                info: null,
                error: null,
                loading: false,
                async identify(file) {
                    this.info = null;
                    this.error = null;
                    if (!file) return;

                    this.loading = true;
                    const body = new FormData();
                    body.append('file', file);
                    try {
                        const response = await fetch(@json(route('statement-imports.identify')), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
                            body,
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            this.error = data.error || Object.values(data.errors || {}).flat()[0] || @json(__('Something went wrong'));
                            return;
                        }
                        this.info = data;
                        if (data.wallet_id) {
                            this.walletId = String(data.wallet_id);
                        }
                    } catch (e) {
                        this.error = @json(__('Something went wrong'));
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
