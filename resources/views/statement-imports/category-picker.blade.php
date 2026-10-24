{{-- Category of one preview line, as a chip: opens a searchable list that can create a category on the fly. --}}
<div x-data="categoryPicker({{ $index }})" x-show="needsCategory(line)" class="relative" @click.outside="close()" @keydown.escape.stop="close()">
    <input type="hidden" name="lines[{{ $line->id }}][category_id]" :value="line.category_id">
    @if ($pending)
        <button type="button" @click="open ? close() : openList()" :aria-expanded="open.toString()" aria-haspopup="listbox"
            :class="line.category_id
                ? 'border-transparent bg-brand-50 text-brand-800 hover:bg-brand-100'
                : (line.selected ? 'border-dashed border-rose-300 bg-rose-50/50 text-rose-700 hover:bg-rose-50' : 'border-dashed border-slate-300 text-slate-500 hover:bg-slate-50')"
            class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition">
            <i class="fa-solid text-[10px]" :class="line.category_id ? 'fa-tag' : 'fa-plus'" aria-hidden="true"></i>
            <span class="truncate" x-text="selectedName() || @js(__('Choose category'))"></span>
            <i class="fa-solid fa-chevron-down text-[9px] opacity-60" aria-hidden="true"></i>
        </button>
    @else
        <span x-show="line.category_id" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
            <i class="fa-solid fa-tag text-[10px]" aria-hidden="true"></i><span x-text="selectedName()"></span>
        </span>
    @endif

    {{-- on phones a sheet at the top of the screen (the keyboard opens at the bottom), a popover from sm up --}}
    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
        class="fixed inset-x-3 top-3 z-40 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:top-auto sm:left-0 sm:z-30 sm:mt-1.5 sm:w-72 sm:rounded-xl sm:shadow-xl">
        <div class="relative border-b border-slate-100 p-2">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
            <input type="text" x-ref="search" x-model="search" @input="highlight = options.findIndex(o => !o.header)" autocomplete="off"
                @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="pick(highlight)" @keydown.tab="close()"
                placeholder="{{ __('Search or create') }}" aria-label="{{ __('Category') }}"
                class="block w-full rounded-lg border-0 bg-slate-50 py-1.5 pl-7 text-sm focus:ring-2 focus:ring-brand-500">
        </div>
        <ul role="listbox" class="max-h-[50vh] overflow-auto py-1 text-sm sm:max-h-64">
            <template x-for="(option, i) in options" :key="option.key">
                <li>
                    <div x-show="option.header" class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400" x-text="option.header"></div>
                    <button type="button" x-show="!option.header" role="option" :aria-selected="(option.id === line.category_id).toString()"
                        @mousedown.prevent="pick(i)" @mouseenter="highlight = i"
                        :class="highlight === i ? 'bg-brand-50 text-brand-900' : 'text-slate-700'"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left">
                        <span class="truncate" x-text="option.label" :class="option.create ? 'font-semibold text-brand-700' : ''"></span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span x-show="option.uses" class="text-xs text-slate-400" x-text="option.uses + '×'"></span>
                            <i x-show="option.id && option.id === line.category_id" class="fa-solid fa-check text-xs text-brand-600" aria-hidden="true"></i>
                        </span>
                    </button>
                </li>
            </template>
            <li x-show="saving" class="px-3 py-2 text-slate-500">{{ __('Creating category...') }}</li>
            <li x-show="error" class="px-3 py-2 text-rose-600" x-text="error"></li>
        </ul>
    </div>
</div>
