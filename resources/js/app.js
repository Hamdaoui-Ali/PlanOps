

import Alpine from 'alpinejs';
import '@phosphor-icons/web/regular';

window.Alpine = Alpine;

Alpine.start();

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
