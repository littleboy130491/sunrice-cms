/*
 * Starter script for the Sunrice starter templates (no dependencies).
 * Published to public/sunrice-theme/app.js and loaded in the layout's
 * <head>, so the "js" class is set before the page paints.
 *
 * - Small screens: the header's "Menu" button opens and closes the menu.
 *   Without JavaScript the menu simply stays open.
 */
document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const menu = document.getElementById(button.getAttribute('aria-controls'));
        if (!menu) return;

        const setOpen = (open) => {
            button.setAttribute('aria-expanded', String(open));
            menu.classList.toggle('is-open', open);
        };

        button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                button.focus();
            }
        });
    });
});
