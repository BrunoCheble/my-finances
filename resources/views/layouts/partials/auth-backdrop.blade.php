{{-- Animated background of the login and lock screens: shields of the logo floating slowly over soft brand
     colours, with a large watermark of the mark. $tone: 'light' (over the pale page) or 'dark' (over the brand panel).
     Decorative only; still when the system asks for reduced motion. --}}
@php($tone = $tone ?? 'light')
<div class="auth-backdrop auth-backdrop--{{ $tone }}" aria-hidden="true">
    <span class="auth-backdrop__glow auth-backdrop__glow--a"></span>
    <span class="auth-backdrop__glow auth-backdrop__glow--b"></span>
    <span class="auth-backdrop__glow auth-backdrop__glow--c"></span>

    {{-- the mark, large and faint --}}
    <svg class="auth-backdrop__mark" viewBox="0 0 40 40">
        <path d="M20 2.5 34.5 7.6v11.6c0 9.3-6.1 15.6-14.5 18.3C11.6 34.8 5.5 28.5 5.5 19.2V7.6z" fill="currentColor" fill-opacity=".07" stroke="currentColor" stroke-opacity=".12" stroke-width=".4"/>
        <rect class="auth-backdrop__bar" x="12" y="20.5" width="4.4" height="8" rx="1.6" fill="currentColor" fill-opacity=".08"/>
        <rect class="auth-backdrop__bar" x="17.8" y="16.5" width="4.4" height="12" rx="1.6" fill="currentColor" fill-opacity=".1"/>
        <rect class="auth-backdrop__bar" x="23.6" y="12.5" width="4.4" height="16" rx="1.6" fill="currentColor" fill-opacity=".12"/>
        <circle class="auth-backdrop__coin" cx="14.2" cy="13.6" r="3.2" fill="#34d399" fill-opacity=".25"/>
    </svg>

    {{-- small shields drifting up --}}
    @foreach ([[8, 14, 0, 26], [22, 9, -6, 31], [38, 18, -12, 24], [55, 11, -3, 34], [70, 16, -18, 28], [84, 10, -9, 30], [93, 13, -15, 26]] as [$left, $size, $delay, $duration])
        <svg class="auth-backdrop__shield" viewBox="0 0 40 40"
            style="left: {{ $left }}%; width: {{ $size * 4 }}px; height: {{ $size * 4 }}px; animation-delay: {{ $delay }}s; animation-duration: {{ $duration }}s;">
            <path d="M20 2.5 34.5 7.6v11.6c0 9.3-6.1 15.6-14.5 18.3C11.6 34.8 5.5 28.5 5.5 19.2V7.6z" fill="none" stroke="currentColor" stroke-width="1.6"/>
        </svg>
    @endforeach
</div>

@once
    <style>
        .auth-backdrop { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
        .auth-backdrop--light { color: #1b63a2; background: linear-gradient(160deg, #f8fafc 0%, #eef5fc 55%, #e6f0fa 100%); }
        .auth-backdrop--dark { color: #fff; }
        html.dark .auth-backdrop--light { color: #7cb6e8; background: linear-gradient(160deg, #0b1220 0%, #0f1b2e 60%, #10213a 100%); }

        .auth-backdrop__glow { position: absolute; border-radius: 9999px; filter: blur(60px); opacity: .55; animation: auth-glow 22s ease-in-out infinite alternate; }
        .auth-backdrop__glow--a { width: 34rem; height: 34rem; left: -10rem; top: -12rem; background: #2f8ad3; opacity: .22; }
        .auth-backdrop__glow--b { width: 28rem; height: 28rem; right: -8rem; bottom: -10rem; background: #174f86; opacity: .2; animation-duration: 28s; animation-delay: -8s; }
        .auth-backdrop__glow--c { width: 16rem; height: 16rem; right: 18%; top: 12%; background: #34d399; opacity: .14; animation-duration: 18s; animation-delay: -4s; }
        .auth-backdrop--dark .auth-backdrop__glow--a { background: #5aa7e6; }
        .auth-backdrop--dark .auth-backdrop__glow--b { background: #0d3a66; opacity: .35; }
        /* over the brand panel the text sits on the mark: keep it quieter */
        .auth-backdrop--dark .auth-backdrop__mark { opacity: .6; }
        .auth-backdrop--dark .auth-backdrop__coin { fill-opacity: .15; }

        .auth-backdrop__mark { position: absolute; left: 50%; top: 50%; width: min(78vh, 90%); height: min(78vh, 90%); transform: translate(-50%, -50%); animation: auth-breathe 9s ease-in-out infinite; }
        .auth-backdrop__bar { transform-box: fill-box; transform-origin: bottom; animation: auth-bar 6s ease-in-out infinite; }
        .auth-backdrop__bar:nth-of-type(2) { animation-delay: -1.2s; }
        .auth-backdrop__bar:nth-of-type(3) { animation-delay: -2.4s; }
        .auth-backdrop__coin { animation: auth-coin 6s ease-in-out infinite; }

        .auth-backdrop__shield { position: absolute; bottom: -12%; opacity: 0; animation-name: auth-rise; animation-timing-function: linear; animation-iteration-count: infinite; }

        @keyframes auth-glow { from { transform: translate(0, 0) scale(1); } to { transform: translate(4rem, 3rem) scale(1.15); } }
        @keyframes auth-breathe { 0%, 100% { transform: translate(-50%, -50%) scale(1); } 50% { transform: translate(-50%, -50%) scale(1.03); } }
        @keyframes auth-bar { 0%, 100% { transform: scaleY(1); } 50% { transform: scaleY(.82); } }
        @keyframes auth-coin { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-1.2px); } }
        @keyframes auth-rise {
            0% { transform: translateY(0) rotate(-8deg); opacity: 0; }
            12% { opacity: .16; }
            85% { opacity: .1; }
            100% { transform: translateY(-120vh) rotate(10deg); opacity: 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            .auth-backdrop *, .auth-backdrop__mark { animation: none !important; }
            .auth-backdrop__shield { opacity: .08; bottom: auto; top: 30%; }
        }
    </style>
@endonce
