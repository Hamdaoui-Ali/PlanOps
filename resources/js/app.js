

import Alpine from 'alpinejs';
import '@phosphor-icons/web/regular';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('keydown', (event) => {
    if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
        event.preventDefault();
        document.querySelector('#nav-search-query')?.focus();
    }
});

const root = document.documentElement;

document.querySelectorAll('select[name="theme"], select[name="density"]').forEach((select) => {
    select.addEventListener('change', (event) => {
        const value = event.target.value.toLowerCase();
        const prefix = event.target.name === 'theme' ? 'theme-' : 'density-';

        [...root.classList]
            .filter((className) => className.startsWith(prefix))
            .forEach((className) => root.classList.remove(className));

        root.classList.add(`${prefix}${value}`);
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.passwordToggle);
        const icon = toggle.querySelector('i');
        if (!input) return;

        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        icon?.classList.toggle('ph-eye', !isHidden);
        icon?.classList.toggle('ph-eye-slash', isHidden);
    });
});
