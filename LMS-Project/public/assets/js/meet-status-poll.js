(() => {
    'use strict';

    const cfg = window.__MEET_STATUS_POLL__;
    if (!cfg || !cfg.url) {
        return;
    }

    let busy = false;

    async function pollMeetStatus() {
        if (busy || document.visibilityState !== 'visible') {
            return;
        }

        busy = true;
        try {
            const response = await fetch(cfg.url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (!data || !data.ok) {
                return;
            }

            // Always signal the calendar to refetch events so status changes appear live
            if (window.__LMS_CALENDAR__ && typeof window.__LMS_CALENDAR__.refetchEvents === 'function') {
                window.__LMS_CALENDAR__.refetchEvents();
            }

            if (data.reload && Array.isArray(data.completed) && data.completed.length > 0) {
                const message = data.completed.length === 1
                    ? 'The class was marked completed from Google Meet activity.'
                    : data.completed.length + ' classes were marked completed from Google Meet activity.';

                if (window.AppAlerts && typeof window.AppAlerts.showToast === 'function') {
                    window.AppAlerts.showToast(message, 'success', 'Class completed');
                }

                window.setTimeout(() => {
                    window.location.reload();
                }, 1400);
            }
        } catch (error) {
            // Silent fail — next interval will retry.
        } finally {
            busy = false;
        }
    }

    const intervalSeconds = Math.max(15, parseInt(String(cfg.intervalSeconds || 20), 10) || 20);

    document.addEventListener('DOMContentLoaded', () => {
        pollMeetStatus();
        window.setInterval(pollMeetStatus, intervalSeconds * 1000);
    });
})();
