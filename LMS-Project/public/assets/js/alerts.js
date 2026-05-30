(() => {
    'use strict';

    function fireSwal(options) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire(options || {});
        }

        const ui = window.AppUI;
        if (ui && typeof ui.alert === 'function') {
            return ui.alert(options || {});
        }

        return Promise.resolve({ isConfirmed: true });
    }

    const swalModalClass = {
        popup: 'app-swal-popup',
        title: 'app-swal-title swal2-title',
        htmlContainer: 'app-swal-body swal2-html-container',
        confirmButton: 'btn btn-primary app-swal-confirm',
        cancelButton: 'btn btn-outline-secondary app-swal-cancel',
        actions: 'app-swal-actions',
    };

    function showSuccess(message, title = 'Success') {
        return fireSwal({
            icon: 'success',
            title: title || 'Success',
            text: message || '',
            timer: 2500,
            timerProgressBar: true,
            showConfirmButton: false,
            customClass: swalModalClass,
        });
    }

    function showError(message, title = 'Error') {
        return fireSwal({
            icon: 'error',
            title: title || 'Error',
            text: message || '',
            confirmButtonColor: '#0d6efd',
            customClass: swalModalClass,
            buttonsStyling: false,
        });
    }

    function showWarning(message, title = 'Warning') {
        return fireSwal({
            icon: 'warning',
            title: title || 'Warning',
            text: message || '',
            confirmButtonColor: '#0d6efd',
            customClass: swalModalClass,
            buttonsStyling: false,
        });
    }

    function showToast(message, type = 'success', title = '') {
        const ui = window.AppUI;
        if (ui && typeof ui.toast === 'function') {
            return ui.toast(type, message, title);
        }

        return fireSwal({
            toast: true,
            position: 'top-end',
            icon: type || 'success',
            title: title || message || '',
            text: title ? (message || '') : '',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    }

    function showConfirm(optionsOrCallback, maybeCallback) {
        const options = typeof optionsOrCallback === 'function' ? {} : (optionsOrCallback || {});
        const callback = typeof optionsOrCallback === 'function'
            ? optionsOrCallback
            : (typeof maybeCallback === 'function' ? maybeCallback : null);

        const ui = window.AppUI;
        if (ui && typeof ui.confirm === 'function') {
            return ui.confirm(options).then((confirmed) => {
                if (confirmed && callback) {
                    callback();
                }
                return confirmed;
            });
        }

        return fireSwal({
            icon: options.icon || 'warning',
            title: options.title || 'Are you sure?',
            text: options.text || 'Please confirm this action.',
            showCancelButton: true,
            confirmButtonText: options.confirmButtonText || 'Continue',
            cancelButtonText: options.cancelButtonText || 'Cancel',
            reverseButtons: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#94a3b8',
            buttonsStyling: false,
            customClass: swalModalClass,
        }).then((result) => {
            const confirmed = Boolean(result && result.isConfirmed);
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

        return showToast(text, 'info', title || 'Notice');
    }

    window.AppAlerts = {
        show: (options) => fireSwal(options),
        showSuccess,
        showError,
        showWarning,
        showInfo: (message, title = 'Notice') => showToast(message, 'info', title || 'Notice'),
        showToast,
        showConfirm,
        presentFlash,
    };

    window.showSuccess = showSuccess;
    window.showError = showError;
    window.showWarning = showWarning;
    window.showConfirm = showConfirm;
    window.showToast = showToast;
})();
