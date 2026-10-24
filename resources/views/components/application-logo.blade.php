{{-- Escudo mark: a shield (protection, and Portugal's old currency) with rising bars and a coin. --}}
{{-- unique gradient id: a hidden copy of the logo would otherwise hide the gradient of the visible one --}}
@php($gradient = 'logo-bg-' . \Illuminate\Support\Str::random(6))
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Escudo" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gradient }}" x1="6" y1="3" x2="34" y2="37" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#2f8ad3"/>
            <stop offset="1" stop-color="#174f86"/>
        </linearGradient>
    </defs>
    <path d="M20 2.5 34.5 7.6v11.6c0 9.3-6.1 15.6-14.5 18.3C11.6 34.8 5.5 28.5 5.5 19.2V7.6z" fill="url(#{{ $gradient }})"/>
    <rect x="12" y="20.5" width="4.4" height="8" rx="1.6" fill="#fff" fill-opacity=".55"/>
    <rect x="17.8" y="16.5" width="4.4" height="12" rx="1.6" fill="#fff" fill-opacity=".8"/>
    <rect x="23.6" y="12.5" width="4.4" height="16" rx="1.6" fill="#fff"/>
    <circle cx="14.2" cy="13.6" r="3.2" fill="#34d399"/>
</svg>
