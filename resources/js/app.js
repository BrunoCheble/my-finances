import './bootstrap';

import Alpine from 'alpinejs';
import { parseSpokenMovement, speechSupported, listen } from './voice-movement';

// filling a movement by voice (see voice-movement.js)
window.escudoVoice = { parse: parseSpokenMovement, supported: speechSupported, listen };

window.Alpine = Alpine;

/**
 * Theme: 'light', 'dark', 'system' (default) or 'escudo' (light, with the navigation
 * in the blue of the app icon). The classes are first applied by an inline script in
 * the layouts, before paint; this keeps them in sync afterwards.
 */
const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
window.escudoTheme = {
    get() {
        try { return localStorage.getItem('theme') || 'system'; } catch (e) { return 'system'; }
    },
    set(theme) {
        try { localStorage.setItem('theme', theme); } catch (e) {}
        this.apply();
    },
    apply() {
        const theme = this.get();
        const dark = theme === 'dark' || (theme === 'system' && darkQuery.matches);
        const escudo = theme === 'escudo';
        document.documentElement.classList.toggle('theme-escudo', escudo);
        document.querySelector('meta[name=theme-color]')?.setAttribute('content', dark ? '#141c2b' : (escudo ? '#1b63a2' : '#ffffff'));
        if (document.documentElement.classList.contains('dark') !== dark) {
            document.documentElement.classList.toggle('dark', dark);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
        }
    },
};
darkQuery.addEventListener('change', () => window.escudoTheme.apply());

Alpine.start();
