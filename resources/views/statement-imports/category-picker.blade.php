{{-- Searchable category picker for one preview line; can create a category on the fly. --}}
<div x-data="categoryPicker({{ $index }})" x-show="needsCategory(line)" class="relative" @click.outside="close()">
    <input type="hidden" name="lines[{{ $line->id }}][category_id]" :value="line.category_id">
    <input type="text" x-ref="input" autocomplete="off" @disabled(!$pending)
        :value="open ? search : selectedName()"
        @input="search = $event.target.value; highlight = 0"
        @focus="openList()"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter.prevent="pick(highlight)"
        @keydown.escape.prevent="close(); $refs.input.blur()"
        @keydown.tab="close()"
        placeholder="{{ __('Select category') }}"
        :class="line.selected && !line.category_id ? 'border-red-400' : 'border-gray-300'"
        class="block w-full rounded-md border bg-white py-1 pl-2 pr-6 text-xs focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-gray-100">

    <ul x-show="open" x-cloak
        class="absolute right-0 z-30 mt-1 max-h-64 w-64 overflow-auto rounded-md border border-gray-200 bg-white py-1 text-xs shadow-lg">
        <template x-for="(option, i) in options" :key="option.key">
            <li>
                <div x-show="option.header" class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400" x-text="option.header"></div>
                <button type="button" x-show="!option.header" @mousedown.prevent="pick(i)" @mouseenter="highlight = i"
                    :class="highlight === i ? 'bg-indigo-50 text-indigo-900' : 'text-gray-700'"
                    class="flex w-full items-center justify-between px-3 py-1.5 text-left">
                    <span x-text="option.label" :class="option.create ? 'font-semibold text-indigo-700' : ''"></span>
                    <span x-show="option.uses" class="ml-2 text-gray-400" x-text="option.uses + '×'"></span>
                </button>
            </li>
        </template>
        <li x-show="saving" class="px-3 py-1.5 text-gray-500">{{ __('Creating category...') }}</li>
        <li x-show="error" class="px-3 py-1.5 text-red-600" x-text="error"></li>
    </ul>
</div>
