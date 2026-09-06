

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

const notificationToast = document.querySelector('[data-notification-toast]');
const notificationBadge = document.querySelector('#notification-count-badge');

if (notificationToast) {
    const countLabel = notificationToast.querySelector('[data-notification-toast-count]');
    const pluralLabel = notificationToast.querySelector('[data-notification-toast-plural]');
    const closeButton = notificationToast.querySelector('[data-notification-toast-close]');
    const endpoint = notificationToast.dataset.notificationUrl;
    let knownCount = Number(notificationToast.dataset.notificationCount || 0);

    const showNotificationToast = (count) => {
        knownCount = count;
        if (countLabel) countLabel.textContent = count;
        if (pluralLabel) pluralLabel.textContent = count === 1 ? '' : 's';
        notificationToast.hidden = false;
    };

    closeButton?.addEventListener('click', () => {
        notificationToast.hidden = true;
    });

    if (knownCount > 0 && !sessionStorage.getItem('planops-notification-toast-seen')) {
        showNotificationToast(knownCount);
        sessionStorage.setItem('planops-notification-toast-seen', '1');
    }

    if (endpoint) {
        window.setInterval(() => {
            fetch(endpoint, { headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : null)
                .then((payload) => {
                    if (!payload || Number(payload.count) <= knownCount) return;
                    showNotificationToast(Number(payload.count));
                    if (notificationBadge) notificationBadge.textContent = payload.count > 99 ? '99+' : payload.count;
                })
                .catch(() => {});
        }, 30000);
    }
}
