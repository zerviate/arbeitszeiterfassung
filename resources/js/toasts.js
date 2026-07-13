function bindToasts() {
    const toasts = document.querySelectorAll('[data-toast]');

    toasts.forEach((toast) => {
        const timeoutValue = Number.parseInt(toast.dataset.timeout || '3500', 10);
        const closeButton = toast.querySelector('[data-toast-dismiss]');

        const dismiss = () => {
            toast.classList.add('toast-leave');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        };

        if (closeButton) {
            closeButton.addEventListener('click', dismiss);
        }

        if (Number.isFinite(timeoutValue) && timeoutValue > 0) {
            window.setTimeout(dismiss, timeoutValue);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindToasts, { once: true });
} else {
    bindToasts();
}
