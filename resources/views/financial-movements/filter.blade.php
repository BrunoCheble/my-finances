<div x-data="filterModal()">

    <x-modal name="modal-filter" :show="false" focusable>
        <div class="space-y-5 p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Filter') }}</h2>
                <x-icon-button icon="xmark" :label="__('Close')" class="-mr-2" x-on:click="$dispatch('close-modal', 'modal-filter')" />
            </div>

            <form action="{{ route('financial-movements.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <!-- Date Range -->
                    <div>
                        <x-input-label for="date_from" :value="__('From')" />
                        <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full"
                            :value="old('date_from', request('date_from'))" autocomplete="date_from" />
                    </div>

                    <div>
                        <x-input-label for="date_to" :value="__('To')" />
                        <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full"
                            :value="old('date_to', request('date_to'))" autocomplete="date_to" />
                    </div>

                    <!-- Sort By -->
                    <div>
                        <x-input-label for="sort" :value="__('Sort by')" />
                        <x-dropdown-select id="sort" name="sort" x-model="selectedSortby" :selected="request('sort')"
                            :options="[
                                'date' => __('Date'),
                                'id' => __('Registration order'),
                                'category_id' => __('Category'),
                                'wallet_id' => __('Wallet'),
                            ]"
                            class="mt-1 w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Filter By -->
                    <div>
                        <x-input-label for="attribute" :value="__('Filter by')" />
                        <x-dropdown-select id="attribute" name="attribute" x-model="selectedAttribute" :selected="request('attribute')"
                            :options="[
                                'description' => __('Description'),
                                'type' => __('Type'),
                                'category_id' => __('Category'),
                                'wallet_id' => __('Wallet'),
                            ]"
                            class="mt-1 w-full" />
                    </div>

                    <!-- Search -->
                    <div>
                        <x-input-label for="search" :value="__('Search')" />
                        <template x-if="searchOptions[selectedAttribute]">
                            <select
                                id="search"
                                name="search"
                                class="input mt-1 block w-full">
                                <template x-for="[key, value] in Object.entries(searchOptions[selectedAttribute])" :key="key">
                                    <option :value="key" x-text="value"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="!searchOptions[selectedAttribute]">
                            <x-text-input id="search" name="search" type="text" value="{{ request('search') }}"
                                class="mt-1 w-full" />
                        </template>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" :href="route('financial-movements.index', array_filter(['wallet_id' => request('wallet_id')]))">{{ __('Clear filters') }}</x-button>
                    <x-primary-button class="justify-center">{{ __('Apply Filters') }}</x-primary-button>
                </div>
                @if (request('wallet_id'))
                    <input type="hidden" name="wallet_id" value="{{ request('wallet_id') }}">
                @endif
            </form>
        </div>
    </x-modal>

</div>

<script>
    function filterModal() {
        return {
            selectedAttribute: '{{ request('attribute', 'description') }}',
            selectedSortby: '{{ request('sort', 'date') }}',
            searchOptions: {
                type: @json($types),
                category_id: @json($categories),
                wallet_id: @json($wallets),
            },
        };
    }
</script>
