<div x-data="filterModal()">

    <x-modal name="modal-filter" :show="false" focusable>
        <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">{{ __('Filter') }}</h2>
                <button type="button" class="text-gray-400 hover:text-gray-600"
                    @click="$dispatch('close-modal', 'modal-filter')">&times;</button>
            </div>

            <form action="{{ route('financial-movements.index') }}" method="GET" class="space-y-4">
                <div class="flex items-center flex-wrap gap-4 mb-4">
                    <!-- Date Range -->
                    <div class="flex-1">
                        <x-input-label for="date_from" :value="__('From')" />
                        <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full"
                            :value="old('date_from', request('date_from'))" autocomplete="date_from" />
                    </div>

                    <div class="flex-1">
                        <x-input-label for="date_to" :value="__('To')" />
                        <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full"
                            :value="old('date_to', request('date_to'))" autocomplete="date_to" />
                    </div>

                    <!-- Sort By -->
                    <div class="flex-1">
                        <x-input-label for="sort" :value="__('Sort by')" />
                        <x-dropdown-select id="sort" name="sort" x-model="selectedSortby" :selected="request('sort')"
                            :options="[
                                'date' => 'Date',
                                'id' => 'ID',
                                'category_id' => 'Category',
                                'wallet_id' => 'Wallet',
                            ]"
                            class="mt-1 w-full" />
                    </div>
                </div>
                <div class="flex items-center flex-wrap gap-4 mb-4">
                    <!-- Filter By -->
                    <div class="flex-1">
                        <x-input-label for="attribute" :value="__('Filter by')" />
                        <x-dropdown-select id="attribute" name="attribute" x-model="selectedAttribute" :selected="request('attribute')"
                            :options="[
                                'description' => 'Description',
                                'type' => 'Type',
                                'category_id' => 'Category',
                                'wallet_id' => 'Wallet',
                            ]"
                            class="mt-1 w-full" />
                    </div>

                    <!-- Search -->
                    <div class="flex-1">
                        <x-input-label for="search" :value="__('Search')" />
                        <template x-if="searchOptions[selectedAttribute]">
                            <select
                                id="search"
                                name="search"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
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

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Apply Filters') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>

    <!-- Floating Button -->
    <button type="button" class="fixed bottom-4 left-4 z-50 bg-blue-500 rounded-full text-white w-12 h-12 shadow-lg"
        @click="$dispatch('open-modal', 'modal-filter')">
        <i class="fa fa-filter"></i>
    </button>
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
            init() {
                console.log('Filter modal initialized:', this.searchOptions);
            }
        };
    }
</script>
