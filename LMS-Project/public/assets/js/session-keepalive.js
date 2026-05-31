(() => {
    'use strict';

    const cfg = window.__SESSION_KEEPALIVE__ || {};
    const base = typeof cfg.base === 'string' ? cfg.base : '';
    const keepAliveUrl = base + '/ajax/keepalive.php';
    const loginUrl = base + '/login?timeout=1';

    const TIMEOUT_MS = 15 * 60 * 1000;
    const WARN_MS = 14 * 60 * 1000;
    const KEEPALIVE_MS = 5 * 60 * 1000;
    const TICK_MS = 30 * 1000;

    let lastUserActivity = Date.now();
    let lastKeepAliveAt = Date.now();
    let warningOpen = false;
    let redirectScheduled = false;

    function markActive() {
        lastUserActivity = Date.now();
        if (warningOpen && window.Swal && typeof window.Swal.close === 'function') {
            window.Swal.close();
        }
        warningOpen = false;
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach((eventName) => {
        document.addEventListener(eventName, markActive, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            markActive();
        }
    });

    function redirectToLogin() {
        if (redirectScheduled) {
            return;
        }
        redirectScheduled = true;
        window.location.href = loginUrl;
    }

    function sendKeepAlive() {
        return fetch(keepAliveUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json().catch(() => null).then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok || !data || data.status !== 'ok') {
                    redirectToLogin();
                    return false;
                }
                lastKeepAliveAt = Date.now();
                lastUserActivity = Date.now();
                return true;
            })
            .catch(() => false);
    }

    function showExpiryWarning() {
        if (warningOpen || redirectScheduled) {
            return;
        }

        warningOpen = true;

        const fire = window.Swal && typeof window.Swal.fire === 'function'
            ? window.Swal.fire.bind(window.Swal)
            : null;

        if (!fire) {
            return;
        }

        fire({
            icon: 'warning',
            title: 'Session Expiring',
            text: 'You will be logged out in 1 minute due to inactivity.',
            confirmButtonText: 'Stay Logged In',
            showCancelButton: false,
            allowOutsideClick: false,
            customClass: {
                popup: 'app-swal-popup app-swal-popup-warning',
                title: 'app-swal-title',
                htmlContainer: 'app-swal-body',
                confirmButton: 'btn btn-primary app-swal-confirm',
                actions: 'app-swal-actions',
            },
            buttonsStyling: false,
        }).then((result) => {
            warningOpen = false;
            if (result && result.isConfirmed) {
                markActive();
                sendKeepAlive();
            }
        });
    }

    function tick() {
        if (document.visibilityState !== 'visible') {
            return;
        }

        const idleMs = Date.now() - lastUserActivity;

        if (idleMs >= TIMEOUT_MS) {
            redirectToLogin();
            return;
        }

        if (idleMs >= WARN_MS) {
            showExpiryWarning();
        }

        if (idleMs < KEEPALIVE_MS && (Date.now() - lastKeepAliveAt) >= KEEPALIVE_MS) {
            sendKeepAlive();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        tick();
        window.setInterval(tick, TICK_MS);
    });
})();
