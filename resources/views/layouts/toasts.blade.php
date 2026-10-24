{{--
    Toast notifications, top right (full width on phones).
    From JS: window.toast({ type: 'success' | 'error' | 'info', title, message, rows: [{ label, value, color, hint }] })
    From the server: session('success') / session('error') show up the same way.
--}}
@php
    $flash = collect([
        session('success') ? ['type' => 'success', 'title' => __(session('success'))] : null,
        session('error') ? ['type' => 'error', 'title' => __(session('error'))] : null,
    ])->filter()->values();
@endphp

<div x-data="toasts(@js($flash))" @toast.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 top-0 z-[70] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-5"
    aria-live="polite" aria-atomic="false">
    <template x-for="toast in items" :key="toast.id">
        <div x-show="toast.visible" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
            x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0"
            @mouseenter="pause(toast)" @mouseleave="resume(toast)" @focusin="pause(toast)" @focusout="resume(toast)"
            :role="toast.type === 'error' ? 'alert' : 'status'"
            class="pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg ring-1 ring-black/5">
            <div class="flex gap-3 p-4">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="{ 'bg-emerald-50 text-emerald-600': toast.type === 'success', 'bg-rose-50 text-rose-600': toast.type === 'error', 'bg-brand-50 text-brand-600': toast.type === 'info' }" aria-hidden="true">
                    <i class="fa-solid text-sm" :class="{ 'fa-check': toast.type === 'success', 'fa-triangle-exclamation': toast.type === 'error', 'fa-circle-info': toast.type === 'info' }"></i>
                </span>
                <div class="min-w-0 flex-1 pt-1">
                    <p class="text-sm font-semibold text-slate-900" x-text="toast.title"></p>
                    <p x-show="toast.message" class="mt-0.5 text-sm text-slate-600" x-text="toast.message"></p>
                    <ul x-show="toast.rows && toast.rows.length" class="mt-3 space-y-1.5 border-t border-slate-100 pt-3">
                        <template x-for="row in toast.rows" :key="row.label">
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background-color: ${row.color || '#94a3b8'}`" aria-hidden="true"></span>
                                    <span class="truncate text-slate-700" x-text="row.label"></span>
                                    <span x-show="row.hint" class="shrink-0 text-xs text-slate-400" x-text="row.hint"></span>
                                </span>
                                <span class="amount font-semibold" :class="row.tone || 'text-slate-900'" x-text="row.value"></span>
                            </li>
                        </template>
                    </ul>
                </div>
                <button type="button" @click="dismiss(toast)" class="-mr-1 -mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="sr-only">{{ __('Close') }}</span>
                </button>
            </div>
            {{-- time left before it closes (errors stay until closed) --}}
            <div x-show="toast.duration" class="absolute inset-x-0 bottom-0 h-0.5 bg-slate-100" aria-hidden="true">
                <div class="h-full origin-left"
                    :class="{ 'bg-emerald-500': toast.type === 'success', 'bg-brand-500': toast.type === 'info' }"
                    :style="`transform: scaleX(${toast.progress}); transition: transform ${toast.paused ? 0 : 100}ms linear`"></div>
            </div>
        </div>
    </template>
</div>

<script>
    window.toast = (detail) => window.dispatchEvent(new CustomEvent('toast', { detail }));

    function toasts(initial) {
        return {
            items: [],
            next: 1,
            init() {
                initial.forEach((toast) => this.push(toast));
            },
            push(detail) {
                const type = detail.type || 'success';
                const toast = {
                    id: this.next++, type, title: detail.title || '', message: detail.message || '', rows: detail.rows || [],
                    duration: detail.duration ?? (type === 'error' ? 0 : (detail.rows?.length ? 7000 : 4500)),
                    left: 0, progress: 1, paused: false, visible: false, timer: null,
                };
                toast.left = toast.duration;
                // only a few at a time: the oldest go first
                this.items.filter((t) => t.visible).slice(0, -2).forEach((t) => this.dismiss(t));
                this.items.push(toast);
                // work on the reactive copy kept in the list, or the view won't follow
                const live = this.items[this.items.length - 1];
                this.$nextTick(() => { live.visible = true; this.tick(live); });
            },
            tick(toast) {
                if (!toast.duration) return;
                clearInterval(toast.timer);
                toast.timer = setInterval(() => {
                    if (toast.paused) return;
                    toast.left -= 100;
                    toast.progress = Math.max(0, toast.left / toast.duration);
                    if (toast.left <= 0) this.dismiss(toast);
                }, 100);
            },
            pause(toast) { toast.paused = true; },
            resume(toast) { toast.paused = false; },
            dismiss(toast) {
                clearInterval(toast.timer);
                toast.visible = false;
                setTimeout(() => { this.items = this.items.filter((t) => t.id !== toast.id); }, 200);
            },
        };
    }
</script>
