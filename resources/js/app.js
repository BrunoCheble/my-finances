import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Theme: 'light', 'dark' or 'system' (default). The class is first applied by an
 * inline script in the layouts, before paint; this keeps it in sync afterwards.
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
        if (document.documentElement.classList.contains('dark') !== dark) {
            document.documentElement.classList.toggle('dark', dark);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
        }
    },
};
darkQuery.addEventListener('change', () => window.escudoTheme.apply());

Alpine.start();
