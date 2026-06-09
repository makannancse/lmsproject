(() => {
    'use strict';

    function getLoader() {
        return document.getElementById('appLoader');
    }

    function getLoaderText() {
        return document.querySelector('[data-app-loader-text]');
    }

    function getLoaderDetail() {
        return document.querySelector('[data-app-loader-detail]');
    }

    function showAppLoader(message, detail) {
        const el = getLoader();
        if (!el) {
            return;
        }

        const loaderText = getLoaderText();
        const loaderDetail = getLoaderDetail();
        if (loaderText && message) {
            loaderText.textContent = message;
        }
        if (loaderDetail && detail) {
            loaderDetail.textContent = detail;
        }

        el.classList.remove('d-none');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('app-loader-on');
    }

    function hideAppLoader() {
        const el = getLoader();
        if (!el) {
            return;
        }

        el.classList.add('d-none');
        el.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('app-loader-on');
    }

    function removeFallbackNode(node) {
        if (node && node.parentNode) {
            node.parentNode.removeChild(node);
        }
    }

    function showFallbackToast(icon, title, text) {
        const toast = document.createElement('div');
        toast.className = 'app-fallback-toast app-fallback-toast-' + (icon || 'info');
        const heading = document.createElement('strong');
        heading.textContent = title || 'Notice';
        const body = document.createElement('div');
        body.textContent = text || '';
        toast.appendChild(heading);
        toast.appendChild(body);
        document.body.appendChild(toast);
        window.setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        window.setTimeout(() => {
            toast.classList.remove('show');
            window.setTimeout(() => removeFallbackNode(toast), 180);
        }, 3200);
    }

    function renderFallbackDialog(options) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'app-fallback-dialog-backdrop';

            const dialog = document.createElement('div');
            dialog.className = 'app-fallback-dialog';

            const title = document.createElement('h3');
            title.className = 'app-fallback-dialog-title';
            title.textContent = options.title || 'Notice';

            const text = document.createElement('p');
            text.className = 'app-fallback-dialog-text';
            text.textContent = options.text || '';

            const actions = document.createElement('div');
            actions.className = 'app-fallback-dialog-actions';

            const close = (confirmed) => {
                removeFallbackNode(overlay);
                resolve({ isConfirmed: confirmed });
            };

            if (options.showCancelButton) {
                const cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'btn btn-outline-secondary';
                cancelButton.textContent = options.cancelButtonText || 'Cancel';
                cancelButton.addEventListener('click', () => close(false));
                actions.appendChild(cancelButton);
            }

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'btn btn-primary';
            confirmButton.textContent = options.confirmButtonText || 'OK';
            confirmButton.addEventListener('click', () => close(true));
            actions.appendChild(confirmButton);

            dialog.appendChild(title);
            if (options.text) {
                dialog.appendChild(text);
            }
            dialog.appendChild(actions);
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
        });
    }

    function getToastOptions(icon, title, text) {
        return {
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3200,
            timerProgressBar: true,
            icon: icon || 'info',
            title: title || '',
            text: text || '',
            customClass: {
                popup: 'app-toast-popup',
                title: 'app-toast-title',
                htmlContainer: 'app-toast-body',
                timerProgressBar: 'app-toast-progress',
            },
        };
    }

    function fireSwal(options) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire(options);
        }
        return renderFallbackDialog(options || {});
    }

    function queueFlashNotifications(messages) {
        if (!Array.isArray(messages) || messages.length === 0) {
            return;
        }

        messages.forEach((item, index) => {
            const delay = index * 220;
            window.setTimeout(() => {
                const type = item && item.type ? item.type : 'info';
                const title = item && item.title ? item.title : '';
                const text = item && item.text ? item.text : '';
                const mode = item && item.mode ? item.mode : '';
                if (mode === 'toast' || ((type === 'success' || type === 'info') && mode !== 'modal')) {
                    AppUI.toast(type, text, title);
                } else {
                    AppUI.alert({ icon: type, title: title, text: text });
                }
            }, delay);
        });
    }

    const AppUI = {
        showLoader(message, detail) {
            showAppLoader(message, detail);
        },
        hideLoader() {
            hideAppLoader();
        },
        alert(options) {
            const icon = options && options.icon ? options.icon : 'info';
            return fireSwal({
                icon: icon,
                title: options && options.title ? options.title : '',
                text: options && options.text ? options.text : '',
                confirmButtonColor: '#0d6efd',
                customClass: {
                    popup: 'app-swal-popup app-swal-popup-' + icon,
                    title: 'app-swal-title',
                    htmlContainer: 'app-swal-body',
                    confirmButton: 'btn btn-primary app-swal-confirm',
                    cancelButton: 'btn btn-outline-secondary app-swal-cancel',
                    actions: 'app-swal-actions',
                },
                buttonsStyling: false,
            });
        },
        success(text, title = 'Success') {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    icon: 'success',
                    title,
                    text,
                    timer: 2500,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: false,
                    customClass: {
                        popup: 'app-swal-popup app-swal-popup-success',
                        title: 'app-swal-title',
                        htmlContainer: 'app-swal-body',
                    },
                });
            }
            return AppUI.alert({ icon: 'success', title, text });
        },
        error(text, title = 'Error') {
            return AppUI.alert({ icon: 'error', title, text });
        },
        warning(text, title = 'Warning') {
            return AppUI.alert({ icon: 'warning', title, text });
        },
        info(text, title = 'Notice') {
            return AppUI.alert({ icon: 'info', title, text });
        },
        toast(type, text, title = '') {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire(getToastOptions(type, title, text));
            }
            showFallbackToast(type, title, text);
            return Promise.resolve({ isConfirmed: true });
        },
        confirm(options) {
            const icon = (options && options.icon) || 'warning';
            return fireSwal({
                title: (options && options.title) || 'Are you sure?',
                text: (options && options.text) || 'Please confirm this action.',
                icon: icon,
                showCancelButton: true,
                confirmButtonText: (options && options.confirmButtonText) || 'Continue',
                cancelButtonText: (options && options.cancelButtonText) || 'Cancel',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#94a3b8',
                reverseButtons: true,
                customClass: {
                    popup: 'app-swal-popup app-swal-popup-' + icon,
                    title: 'app-swal-title',
                    htmlContainer: 'app-swal-body',
                    confirmButton: 'btn btn-primary app-swal-confirm',
                    cancelButton: 'btn btn-outline-secondary app-swal-cancel',
                    actions: 'app-swal-actions',
                },
                buttonsStyling: false,
            }).then((result) => Boolean(result && result.isConfirmed));
        },
        flushQueuedFlashes() {
            queueFlashNotifications(window.__APP_FLASHES__ || []);
            window.__APP_FLASHES__ = [];
        },
    };

    window.AppUI = AppUI;

    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach((form) => {
        form.addEventListener(
            'submit',
            (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            },
            false
        );
    });

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        let confirmOptions = null;
        if (submitter && submitter.dataset.confirm === '1') {
            confirmOptions = {
                title: submitter.dataset.confirmTitle || 'Are you sure?',
                text: submitter.dataset.confirmText || 'Please confirm this action.',
                icon: submitter.dataset.confirmIcon || 'warning',
                confirmButtonText: submitter.dataset.confirmButton || 'Continue',
                cancelButtonText: submitter.dataset.confirmCancel || 'Cancel',
            };
        } else if (form.dataset.confirm === '1') {
            confirmOptions = {
                title: form.dataset.confirmTitle || 'Are you sure?',
                text: form.dataset.confirmText || 'Please confirm this action.',
                icon: form.dataset.confirmIcon || 'warning',
                confirmButtonText: form.dataset.confirmButton || 'Continue',
                cancelButtonText: form.dataset.confirmCancel || 'Cancel',
            };
        } else if (form.dataset.confirmStatusValue) {
            const statusField = form.querySelector('[name="status"]');
            const watchedValues = String(form.dataset.confirmStatusValue)
                .split(',')
                .map((value) => value.trim().toLowerCase())
                .filter(Boolean);
            const selectedValue = statusField instanceof HTMLSelectElement
                ? statusField.value.trim().toLowerCase()
                : '';
            if (selectedValue && watchedValues.includes(selectedValue)) {
                confirmOptions = {
                    title: form.dataset.confirmTitle || 'Are you sure?',
                    text: form.dataset.confirmText || 'Please confirm this action.',
                    icon: form.dataset.confirmIcon || 'warning',
                    confirmButtonText: form.dataset.confirmButton || 'Continue',
                    cancelButtonText: form.dataset.confirmCancel || 'Cancel',
                };
            }
        }

        if (confirmOptions && form.dataset.confirmed !== '1') {
            event.preventDefault();
            event.stopPropagation();
            AppUI.confirm(confirmOptions).then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') {
                    if (submitter) {
                        form.requestSubmit(submitter);
                    } else {
                        form.requestSubmit();
                    }
                } else {
                    form.submit();
                }
            });
            return;
        }

        if (form.dataset.confirmed === '1') {
            form.dataset.confirmed = '0';
        }

        if (form.dataset.noLoader === '1' || form.classList.contains('no-app-loader')) {
            return;
        }
        if (!form.checkValidity()) {
            return;
        }

        showAppLoader(form.dataset.loaderTitle || 'Working on it...', form.dataset.loaderText || 'Please wait a moment.');
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-app-toast]') : null;
        if (!trigger) {
            return;
        }

        event.preventDefault();
        AppUI.toast(
            trigger.getAttribute('data-app-toast-type') || 'info',
            trigger.getAttribute('data-app-toast') || '',
            trigger.getAttribute('data-app-toast-title') || ''
        );
    });

    document.addEventListener('DOMContentLoaded', () => {
        AppUI.flushQueuedFlashes();

        const root = document.querySelector('[data-auto-refresh-seconds]');
        if (root) {
            const sec = parseInt(String(root.getAttribute('data-auto-refresh-seconds') || '0'), 10);
            if (sec >= 15) {
                window.setInterval(() => {
                    if (document.visibilityState !== 'visible') {
                        return;
                    }
                    window.location.reload();
                }, sec * 1000);
            }
        }
    });
})();
