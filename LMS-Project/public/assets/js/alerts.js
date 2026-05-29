(() => {
    'use strict';

    function appUi() {
        return window.AppUI || null;
    }

    function normalizeConfirmOptions(options) {
        return {
            title: options.title || 'Are you sure?',
            text: options.text || 'Please confirm this action.',
            icon: options.icon || 'warning',
            confirmButtonText: options.confirmButtonText || 'Continue',
            cancelButtonText: options.cancelButtonText || 'Cancel',
        };
    }

    function withAppUi(method, fallback) {
        const ui = appUi();
        if (ui && typeof ui[method] === 'function') {
            return ui[method].bind(ui);
        }

        return fallback;
    }

    function show(options) {
        const invoke = withAppUi('alert', (alertOptions) => {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire(alertOptions || {});
            }
            return Promise.resolve({ isConfirmed: true });
        });
        return invoke(options || {});
    }

    function showSuccess(message, title = 'Success') {
        return withAppUi('success', (text, heading) => show({ icon: 'success', text, title: heading }))(message, title);
    }

    function showError(message, title = 'Error') {
        return withAppUi('error', (text, heading) => show({ icon: 'error', text, title: heading }))(message, title);
    }

    function showWarning(message, title = 'Warning') {
        return withAppUi('warning', (text, heading) => show({ icon: 'warning', text, title: heading }))(message, title);
    }

    function showInfo(message, title = 'Notice') {
        const ui = appUi();
        if (ui && typeof ui.info === 'function') {
            return ui.info(message, title);
        }

        return show({ icon: 'info', text: message, title });
    }

    function showToast(message, type = 'success', title = '') {
        return withAppUi('toast', (icon, text, heading) => {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    icon,
                    title: heading || '',
                    text: text || '',
                });
            }
            return Promise.resolve({ isConfirmed: true });
        })(type, message, title);
    }

    function showConfirm(optionsOrCallback, maybeCallback) {
        const options = typeof optionsOrCallback === 'function' ? {} : (optionsOrCallback || {});
        const callback = typeof optionsOrCallback === 'function'
            ? optionsOrCallback
            : (typeof maybeCallback === 'function' ? maybeCallback : null);

        return withAppUi('confirm', (confirmOptions) => {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    ...normalizeConfirmOptions(confirmOptions),
                    showCancelButton: true,
                }).then((result) => Boolean(result && result.isConfirmed));
            }
            const confirmed = window.confirm((confirmOptions && confirmOptions.text) || 'Please confirm this action.');
            return Promise.resolve(confirmed);
        })(normalizeConfirmOptions(options)).then((confirmed) => {
            if (confirmed && callback) {
                callback();
            }
            return confirmed;
        });
    }

    function presentFlash(item) {
        const flash = item || {};
        const type = flash.type || 'info';
        const title = flash.title || '';
        const text = flash.text || '';
        const mode = flash.mode || '';

        if (mode === 'toast' || ((type === 'success' || type === 'info') && mode !== 'modal')) {
            return showToast(text, type, title);
        }

        if (type === 'success') {
            return showSuccess(text, title || 'Success');
        }
        if (type === 'error') {
            return showError(text, title || 'Error');
        }
        if (type === 'warning') {
            return showWarning(text, title || 'Warning');
        }

        return showInfo(text, title || 'Notice');
    }

    window.AppAlerts = {
        show,
        showSuccess,
        showError,
        showWarning,
        showInfo,
        showToast,
        showConfirm,
        presentFlash,
    };

    window.showSuccess = showSuccess;
    window.showError = showError;
    window.showWarning = showWarning;
    window.showConfirm = showConfirm;
})();
