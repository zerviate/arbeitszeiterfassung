function bindAppShell() {
    const body = document.body;
    const toggleButtons = document.querySelectorAll('[data-app-sidebar-toggle]');
    const closeButtons = document.querySelectorAll('[data-app-sidebar-close]');
    const mediaQuery = window.matchMedia('(min-width: 1024px)');

    const openSidebar = () => body.classList.add('app-shell-sidebar-open');
    const closeSidebar = () => body.classList.remove('app-shell-sidebar-open');

    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (body.classList.contains('app-shell-sidebar-open')) {
                closeSidebar();

                return;
            }

            openSidebar();
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    mediaQuery.addEventListener('change', (event) => {
        if (event.matches) {
            closeSidebar();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindAppShell, { once: true });
} else {
    bindAppShell();
}
