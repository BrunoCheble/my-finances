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
        placeholder="{{ __('Select category') }}" aria-label="{{ __('Category') }}"
        :class="line.selected && !line.category_id ? '!border-rose-400' : ''"
        class="input block w-full py-1.5 pl-8 text-sm disabled:opacity-70">
    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-[0.8rem] text-xs text-slate-400" aria-hidden="true"></i>

    <ul x-show="open" x-cloak
        class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg lg:left-auto lg:w-72">
        <template x-for="(option, i) in options" :key="option.key">
            <li>
                <div x-show="option.header" class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400" x-text="option.header"></div>
                <button type="button" x-show="!option.header" @mousedown.prevent="pick(i)" @mouseenter="highlight = i"
                    :class="highlight === i ? 'bg-brand-50 text-brand-900' : 'text-slate-700'"
                    class="flex w-full items-center justify-between px-3 py-2 text-left">
                    <span x-text="option.label" :class="option.create ? 'font-semibold text-brand-700' : ''"></span>
                    <span x-show="option.uses" class="ml-2 text-xs text-slate-400" x-text="option.uses + '×'"></span>
                </button>
            </li>
        </template>
        <li x-show="saving" class="px-3 py-2 text-slate-500">{{ __('Creating category...') }}</li>
        <li x-show="error" class="px-3 py-2 text-rose-600" x-text="error"></li>
    </ul>
</div>
