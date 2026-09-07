

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
    const lastNotifiedCountKey = 'planops-notification-toast-count';
    sessionStorage.removeItem('planops-notification-toast-seen');
    let lastNotifiedCount = Number(sessionStorage.getItem(lastNotifiedCountKey) || 0);
    let dismissTimer;

    const showNotificationToast = (count) => {
        knownCount = count;
        lastNotifiedCount = count;
        if (countLabel) countLabel.textContent = count;
        if (pluralLabel) pluralLabel.textContent = count === 1 ? '' : 's';
        notificationToast.hidden = false;
        sessionStorage.setItem(lastNotifiedCountKey, String(count));
        window.clearTimeout(dismissTimer);
        dismissTimer = window.setTimeout(() => {
            notificationToast.hidden = true;
        }, 3000);
    };

    closeButton?.addEventListener('click', () => {
        window.clearTimeout(dismissTimer);
        notificationToast.hidden = true;
    });

    if (knownCount > 0 && knownCount > lastNotifiedCount) {
        showNotificationToast(knownCount);
    } else if (knownCount < lastNotifiedCount) {
        lastNotifiedCount = knownCount;
        sessionStorage.setItem(lastNotifiedCountKey, String(knownCount));
    }

    if (endpoint) {
        window.setInterval(() => {
            fetch(endpoint, { headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : null)
                .then((payload) => {
                    if (!payload) return;

                    const count = Number(payload.count);
                    if (count <= knownCount) return;
                    showNotificationToast(count);
                    if (notificationBadge) notificationBadge.textContent = count > 99 ? '99+' : count;
                })
                .catch(() => {});
        }, 30000);
    }
}
