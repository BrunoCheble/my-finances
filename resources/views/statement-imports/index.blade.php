<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Import Statement')" :subtitle="__('Read the PDF of your bank statement, review the movements and import them in one go.')" />
    </x-slot>

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        {{-- New import --}}
        <section class="card p-5 sm:p-6" aria-labelledby="new-import-title">
            <h2 id="new-import-title" class="text-base font-semibold text-slate-900">{{ __('New import') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Upload the bank statement PDF. The file is only read to build the preview and is not stored.') }}</p>

            <form method="POST" action="{{ route('statement-imports.store') }}" enctype="multipart/form-data" class="mt-5 space-y-5" x-data="statementUpload()">
                @csrf
                {{-- drop zone: the input covers it, so a tap or a drop both pick the file --}}
                <div class="relative rounded-xl border-2 border-dashed transition"
                    :class="dragging ? 'border-brand-500 bg-brand-50' : (fileName ? 'border-slate-200 bg-white' : 'border-slate-300 bg-slate-50 hover:border-brand-400 hover:bg-brand-50/40')"
                    @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop="dragging = false">
                    <input id="file" name="file" type="file" accept="application/pdf" required
                        @change="pick($event.target.files[0])"
                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0" aria-describedby="file-hint">
                    <div class="pointer-events-none flex flex-col items-center gap-3 px-4 py-8 text-center sm:flex-row sm:text-left">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                            :class="fileName ? 'bg-rose-50 text-rose-600' : 'bg-white text-slate-400 shadow-sm ring-1 ring-slate-200'" aria-hidden="true">
                            <i class="fa-solid text-xl" :class="fileName ? 'fa-file-pdf' : 'fa-cloud-arrow-up'"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900" x-text="fileName || @js(__('Drop the PDF here or tap to choose'))"></p>
                            <p id="file-hint" class="mt-0.5 text-xs text-slate-500" x-show="!fileName">{{ __('PDF up to 10 MB.') }} {{ __('Supported') }}: {{ implode(', ', $banks) }}</p>
                            {{-- what was recognized in the file --}}
                            <p x-show="loading" x-cloak class="mt-1 flex items-center justify-center gap-2 text-xs text-slate-500 sm:justify-start">
                                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>{{ __('Reading statement...') }}
                            </p>
                            <p x-show="error" x-cloak class="mt-1 text-xs font-medium text-rose-600" x-text="error" role="alert"></p>
                            <template x-if="info">
                                <div class="mt-2 flex flex-wrap items-center justify-center gap-1.5 text-xs sm:justify-start">
                                    <span class="badge bg-brand-50 text-brand-700"><i class="fa-solid fa-building-columns text-[10px]" aria-hidden="true"></i>
                                        <span x-text="info.bank_name + (info.account_last4 ? ' · ••••' + info.account_last4 : '')"></span></span>
                                    <span class="badge bg-slate-100 text-slate-700" x-text="info.lines + ' ' + @js(__('movements'))"></span>
                                    <span x-show="info.wallet_id" class="badge bg-emerald-50 text-emerald-700"><i class="fa-solid fa-link text-[10px]" aria-hidden="true"></i>
                                        <span x-text="info.wallet_name + ' · ' + @js(__('remembered'))"></span></span>
                                    <span x-show="!info.wallet_id" class="badge bg-amber-50 text-amber-800">{{ __('New account: choose the wallet, it will be remembered next time.') }}</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('file')" />

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="sm:w-72">
                        <x-input-label for="wallet_id" :value="__('Wallet')" />
                        <select id="wallet_id" name="wallet_id" x-model="walletId" required class="input mt-1 block w-full">
                            <option value="">{{ __('Select wallet') }}</option>
                            @foreach ($wallets as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('wallet_id')" />
                    </div>
                    <x-button type="submit" icon="wand-magic-sparkles" x-bind:disabled="loading || !fileName || !walletId" class="sm:py-2.5">{{ __('Read statement') }}</x-button>
                </div>
            </form>
        </section>

        {{-- History --}}
        <section class="card overflow-hidden" aria-labelledby="history-title">
            <h2 id="history-title" class="border-b border-slate-200 px-5 py-4 text-base font-semibold text-slate-900 sm:px-6">{{ __('Previous imports') }}</h2>
            @if ($imports->isEmpty())
                <x-empty-state icon="file-import" :title="__('No imports yet.')" :description="__('The statements you read show up here.')" />
            @else
                <div class="hidden md:block">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="th">{{ __('Wallet') }}</th>
                                <th scope="col" class="th">{{ __('Period') }}</th>
                                <th scope="col" class="th text-right">{{ __('Lines') }}</th>
                                <th scope="col" class="th">{{ __('Status') }}</th>
                                <th scope="col" class="th">{{ __('Read on') }}</th>
                                <th scope="col" class="th"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($imports as $import)
                                <tr class="hover:bg-slate-50">
                                    <td class="td">
                                        <span class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $import->wallet->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                                            <span class="font-medium text-slate-900">{{ $import->wallet->name }}</span>
                                        </span>
                                        <span class="ml-[18px] text-xs text-slate-500">{{ $banks[$import->bank] ?? strtoupper($import->bank) }}@if ($import->account_last4) · ••••{{ $import->account_last4 }}@endif</span>
                                    </td>
                                    <td class="td whitespace-nowrap">@if ($import->period_start){{ date_format_custom($import->period_start) }} – {{ date_format_custom($import->period_end) }}@else — @endif</td>
                                    <td class="td text-right">{{ $import->lines_count }}</td>
                                    <td class="td">
                                        @if ($import->is_pending)
                                            <x-badge color="yellow"><i class="fa-solid fa-hourglass-half text-[10px]" aria-hidden="true"></i>{{ __('Pending review') }}</x-badge>
                                        @else
                                            <x-badge color="green"><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>{{ __('Confirmed') }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="td whitespace-nowrap text-slate-500">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="td text-right">
                                        <x-button :href="route('statement-imports.show', $import)" :variant="$import->is_pending ? 'primary' : 'secondary'" size="sm">
                                            {{ $import->is_pending ? __('Review') : __('Open') }}
                                        </x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="divide-y divide-slate-100 md:hidden">
                    @foreach ($imports as $import)
                        <li>
                            <a href="{{ route('statement-imports.show', $import) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $import->wallet->color ?? '#94a3b8' }}" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-900">{{ $import->wallet->name }}</span>
                                    <span class="block truncate text-xs text-slate-500">
                                        @if ($import->period_start){{ date_format_custom($import->period_start) }} – {{ date_format_custom($import->period_end) }} · @endif{{ trans_choice(':count line|:count lines', $import->lines_count, ['count' => $import->lines_count]) }}
                                    </span>
                                </span>
                                @if ($import->is_pending)
                                    <x-badge color="yellow">{{ __('Pending review') }}</x-badge>
                                @else
                                    <x-badge color="green">{{ __('Confirmed') }}</x-badge>
                                @endif
                                <i class="fa-solid fa-chevron-right text-xs text-slate-300" aria-hidden="true"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <script>
        function statementUpload() {
            return {
                walletId: @json((string) old('wallet_id', '')),
                fileName: '',
                dragging: false,
                info: null,
                error: null,
                loading: false,
                pick(file) {
                    this.fileName = file ? file.name : '';
                    this.identify(file);
                },
                // bank and account recognized as soon as the file is chosen (the wallet comes along when known)
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
