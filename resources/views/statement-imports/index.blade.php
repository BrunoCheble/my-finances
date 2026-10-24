<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Import Statement')" :subtitle="__('Read the PDF of your bank statement, review the movements and import them in one go.')" />
    </x-slot>

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @include('statement-imports.steps', ['current' => 1])

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            {{-- New import --}}
            <section class="card p-5 sm:p-6" aria-labelledby="new-import-title">
                <h2 id="new-import-title" class="sr-only">{{ __('New import') }}</h2>
                <form method="POST" action="{{ route('statement-imports.store') }}" enctype="multipart/form-data" class="space-y-5" x-data="statementUpload()">
                    @csrf
                    {{-- drop zone: the input covers it, so a tap or a drop both pick the file --}}
                    <div class="relative overflow-hidden rounded-2xl border-2 border-dashed transition"
                        :class="dragging ? 'border-brand-500 bg-brand-50' : (error ? 'border-rose-300 bg-rose-50/40' : (info ? 'border-emerald-300 bg-emerald-50/40' : (fileName ? 'border-slate-300 bg-white' : 'border-slate-300 bg-gradient-to-b from-slate-50 to-white hover:border-brand-400')))"
                        @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop="dragging = false">
                        <input id="file" name="file" type="file" accept="application/pdf" required
                            @change="pick($event.target.files[0])"
                            class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" aria-describedby="file-hint">

                        {{-- nothing chosen yet --}}
                        <div x-show="!fileName" class="pointer-events-none flex flex-col items-center px-6 py-10 text-center sm:py-14">
                            <span class="relative flex h-20 w-20 items-center justify-center" aria-hidden="true">
                                <span class="absolute inset-0 rotate-6 rounded-2xl bg-brand-100"></span>
                                <span class="absolute inset-0 -rotate-3 rounded-2xl bg-white shadow-card ring-1 ring-slate-200"></span>
                                <i class="fa-solid fa-file-pdf relative text-3xl text-rose-500"></i>
                                <span class="absolute -bottom-2 -right-2 flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg ring-4 ring-white">
                                    <i class="fa-solid fa-arrow-up text-xs"></i>
                                </span>
                            </span>
                            <p class="mt-6 text-base font-semibold text-slate-900">{{ __('Drop the statement PDF here') }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ __('or') }} <span class="font-semibold text-brand-700">{{ __('choose a file') }}</span></p>
                            <p id="file-hint" class="mt-3 text-xs text-slate-400">{{ __('PDF up to 10 MB. Read in the moment, never stored.') }}</p>
                        </div>

                        {{-- a file chosen: what was recognized in it --}}
                        <div x-show="fileName" x-cloak class="pointer-events-none flex items-center gap-4 p-4 sm:p-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-rose-500 shadow-card ring-1 ring-slate-200" aria-hidden="true">
                                <i class="fa-solid fa-file-pdf text-xl"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900" x-text="fileName"></p>
                                <p x-show="loading" class="mt-1 text-xs text-slate-500"><i class="fa-solid fa-circle-notch fa-spin mr-1" aria-hidden="true"></i>{{ __('Reading statement...') }}</p>
                                <p x-show="error" class="mt-1 text-xs font-medium text-rose-600" x-text="error" role="alert"></p>
                                <template x-if="info">
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="badge bg-white text-slate-700 ring-1 ring-slate-200"><i class="fa-solid fa-building-columns text-[10px]" aria-hidden="true"></i>
                                            <span x-text="info.bank_name + (info.account_last4 ? ' · ••••' + info.account_last4 : '')"></span></span>
                                        <span class="badge bg-white text-slate-700 ring-1 ring-slate-200" x-text="info.lines + ' ' + @js(__('movements'))"></span>
                                        <span x-show="info.wallet_id" class="badge bg-emerald-100 text-emerald-800"><i class="fa-solid fa-link text-[10px]" aria-hidden="true"></i>
                                            <span x-text="info.wallet_name"></span></span>
                                    </div>
                                </template>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-brand-700">{{ __('Change file') }}</span>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('file')" />

                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                        <div>
                            <x-input-label for="wallet_id" :value="__('Which wallet is this statement from?')" />
                            <select id="wallet_id" name="wallet_id" x-model="walletId" required class="input mt-1 block w-full py-2.5">
                                <option value="">{{ __('Select wallet') }}</option>
                                @foreach ($wallets as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <p x-show="info && !info.wallet_id" x-cloak class="mt-1 text-xs text-amber-700">{{ __('New account: choose the wallet, it will be remembered next time.') }}</p>
                            <p x-show="info && info.wallet_id" x-cloak class="mt-1 text-xs text-emerald-700"><i class="fa-solid fa-link mr-0.5" aria-hidden="true"></i>{{ __('Remembered from the last time.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('wallet_id')" />
                        </div>
                        <x-button type="submit" x-bind:disabled="loading || !fileName || !walletId" class="py-2.5 sm:px-6">
                            {{ __('Read statement') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </x-button>
                    </div>
                </form>
            </section>

            {{-- How it works and the banks it reads --}}
            <aside class="card p-5 sm:p-6" aria-labelledby="how-title">
                <h2 id="how-title" class="text-sm font-semibold text-slate-900">{{ __('How it works') }}</h2>
                <ol class="mt-4 space-y-4">
                    @foreach ([
                        ['file-arrow-up', __('Upload the PDF'), __('Only its text is read; the file is not kept.')],
                        ['list-check', __('Review'), __('Categories come from your history; new and duplicated movements are flagged.')],
                        ['circle-check', __('Import'), __('Balances are recalculated and, when they match the bank, the month is confirmed.')],
                    ] as [$icon, $title, $text])
                        <li class="flex gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600" aria-hidden="true"><i class="fa-solid fa-{{ $icon }} text-sm"></i></span>
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $title }}</p>
                                <p class="text-xs text-slate-500">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <h3 class="mt-6 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Supported banks') }}</h3>
                <ul class="mt-2 space-y-2">
                    @foreach ($banks as $bank => $name)
                        <li class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                            <i class="fa-solid fa-building-columns text-slate-400" aria-hidden="true"></i>{{ $name }}
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>

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
