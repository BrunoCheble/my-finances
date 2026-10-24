{{-- Installable app (PWA): manifest, icons, theme color and the service worker --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Escudo">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<script>
    // the bar color follows the theme chosen before paint
    if (document.documentElement.classList.contains('dark')) document.querySelector('meta[name=theme-color]').content = '#141c2b';
    // the install prompt can fire before the bundle loads: keep it for the "Install app" button
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.escudoInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('install-available'));
    });
    window.addEventListener('appinstalled', () => { window.escudoInstallPrompt = null; });
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(@json(asset('sw.js'))).catch(() => {}));
    }
</script>
