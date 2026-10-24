{{-- Pagination in the app's design: prev/next on phones, page numbers from sm up. --}}
@if ($paginator->hasPages())
    @php
        $base = 'inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-lg px-2.5 text-sm font-medium transition';
        $link = $base . ' text-slate-600 hover:bg-slate-100 hover:text-slate-900';
        $disabled = $base . ' cursor-not-allowed text-slate-300';
    @endphp
    <nav role="navigation" aria-label="{{ __('Pagination') }}" class="flex items-center justify-between gap-2">
        {{-- previous --}}
        @if ($paginator->onFirstPage())
            <span class="{{ $disabled }} gap-1.5" aria-disabled="true">
                <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i><span class="sm:sr-only">{{ __('Previous') }}</span>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} gap-1.5">
                <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i><span class="sm:sr-only">{{ __('Previous') }}</span>
            </a>
        @endif

        {{-- phones: just where you are --}}
        <p class="text-sm text-slate-500 sm:hidden">
            {{ __('Page :current of :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
        </p>

        {{-- page numbers --}}
        <ul class="hidden items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="{{ $base }} text-slate-400" aria-hidden="true">…</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $base }} bg-brand-50 font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $link }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        {{-- next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} gap-1.5">
                <span class="sm:sr-only">{{ __('Next') }}</span><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
            </a>
        @else
            <span class="{{ $disabled }} gap-1.5" aria-disabled="true">
                <span class="sm:sr-only">{{ __('Next') }}</span><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
            </span>
        @endif
    </nav>
@endif
