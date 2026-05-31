/**
 * Shared AJAX handler for Schedule Class (calendar modal + create page).
 */
(function () {
    'use strict';

    function cleanupModalArtifacts() {
        document.body.classList.remove('modal-open', 'schedule-modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function dismissModal(modalEl) {
        if (!modalEl || typeof bootstrap === 'undefined') {
            cleanupModalArtifacts();
            return;
        }

        var instance = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
        modalEl.addEventListener('hidden.bs.modal', cleanupModalArtifacts, { once: true });
        instance.hide();
        window.setTimeout(cleanupModalArtifacts, 400);
    }

    function resolveRedirectUrl(response, base) {
        if (response && response.redirect_url) {
            return response.redirect_url;
        }
        return (base || '') + '/admin/calendar';
    }

    function isSuccess(response) {
        return response && (response.success === true || response.ok === true);
    }

    function showSuccessThenRedirect(message, redirectUrl) {
        var navigate = function () {
            cleanupModalArtifacts();
            if (window.AppUI && typeof window.AppUI.hideLoader === 'function') {
                window.AppUI.hideLoader();
            }
            window.location.href = redirectUrl;
        };

        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                icon: 'success',
                title: 'Success',
                text: message || 'Class scheduled successfully.',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                customClass: {
                    popup: 'app-swal-popup app-swal-popup-success',
                    title: 'app-swal-title',
                    htmlContainer: 'app-swal-body',
                },
            }).then(navigate).catch(navigate);
        }

        if (typeof window.showSuccess === 'function') {
            return window.showSuccess(message || 'Class scheduled successfully.', 'Success').then(navigate).catch(navigate);
        }

        navigate();
        return Promise.resolve();
    }

    function showScheduleError(message, errors) {
        var text = message || 'Could not schedule class.';
        if (Array.isArray(errors) && errors.length) {
            text = errors.join(' ');
        }

        if (typeof window.showError === 'function') {
            return window.showError(text, 'Error');
        }
        if (window.AppUI && typeof window.AppUI.error === 'function') {
            return window.AppUI.error(text, 'Error');
        }
        window.alert(text);
        return Promise.resolve();
    }

    /**
     * @param {object} response JSON body
     * @param {object} options
     * @param {string} options.base
     * @param {HTMLElement|null} options.modalEl
     * @param {Function} [options.onCalendarRefresh]
     */
    function handleScheduleResponse(response, options) {
        options = options || {};
        var base = options.base || '';

        if (isSuccess(response)) {
            var msg = response.message || 'Class scheduled successfully.';
            var redirectUrl = resolveRedirectUrl(response, base);

            dismissModal(options.modalEl || null);

            if (typeof options.onCalendarRefresh === 'function') {
                try {
                    options.onCalendarRefresh();
                } catch (e) {
                    // ignore
                }
            }

            return showSuccessThenRedirect(msg, redirectUrl);
        }

        var errMsg = (response && response.message) ? response.message : 'Could not schedule class.';
        return showScheduleError(errMsg, response && response.errors);
    }

    /**
     * POST schedule form via fetch.
     *
     * @param {HTMLFormElement} form
     * @param {object} options
     */
    function submitScheduleForm(form, options) {
        options = options || {};
        var base = options.base || '';
        var submitBtn = options.submitButton || form.querySelector('[type="submit"], button[id$="Submit"]');
        var modalEl = options.modalEl || null;

        if (!form) {
            return Promise.resolve();
        }

        if (submitBtn) {
            submitBtn.disabled = true;
        }

        if (window.AppUI && typeof window.AppUI.showLoader === 'function') {
            window.AppUI.showLoader(
                options.loaderTitle || 'Scheduling class...',
                options.loaderText || 'Creating the class and Google Meet link.'
            );
        }

        var fd = new FormData(form);
        if (!fd.has('calendar_ajax')) {
            fd.append('calendar_ajax', '1');
        }

        return fetch(base + '/classes', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var json = null;
                    try {
                        json = text ? JSON.parse(text) : null;
                    } catch (e) {
                        json = null;
                    }
                    return { httpOk: response.ok, status: response.status, json: json, raw: text };
                });
            })
            .then(function (result) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (window.AppUI && typeof window.AppUI.hideLoader === 'function') {
                    window.AppUI.hideLoader();
                }

                if (!result.json) {
                    showScheduleError('Server returned an invalid response (HTTP ' + result.status + ').', []);
                    return;
                }

                if (!result.httpOk && !isSuccess(result.json)) {
                    showScheduleError(result.json.message, result.json.errors);
                    return;
                }

                return handleScheduleResponse(result.json, options);
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (window.AppUI && typeof window.AppUI.hideLoader === 'function') {
                    window.AppUI.hideLoader();
                }
                showScheduleError('Network error while scheduling the class.', []);
            });
    }

    /**
     * Wire a form to AJAX schedule submit (prevents full page POST).
     */
    function bindScheduleForm(form, options) {
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopPropagation();
            submitScheduleForm(form, options);
        });
    }

    window.LmsScheduleClass = {
        submitScheduleForm: submitScheduleForm,
        handleScheduleResponse: handleScheduleResponse,
        bindScheduleForm: bindScheduleForm,
        dismissModal: dismissModal,
        cleanupModalArtifacts: cleanupModalArtifacts,
    };
})();
