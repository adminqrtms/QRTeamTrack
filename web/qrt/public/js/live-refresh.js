/**
 * Live refresh: every few seconds, re-fetch the current page in the background
 * and swap in the fresh contents of every element marked with data-live="<id>".
 * Filters and pagination are kept because the same URL is fetched.
 * Rows marked data-live-key="<key>" that were not there before are highlighted.
 */
(function () {
    const regions = () => document.querySelectorAll('[data-live]');
    if (!regions().length) return;

    const interval = parseInt(document.body.dataset.liveInterval || '5000', 10);
    let timer = null;
    let busy = false;

    function isBusy(region) {
        // Don't replace content the admin is currently interacting with.
        const active = document.activeElement;
        if (active && active !== document.body && region.contains(active)) return true;
        return !!region.querySelector('.dropdown-menu.show, .modal.show');
    }

    function keysIn(root) {
        return new Set(Array.from(root.querySelectorAll('[data-live-key]')).map(el => el.dataset.liveKey));
    }

    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const res = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Live-Refresh': '1' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            // Session expired or page moved: stop polling instead of swapping in a login page.
            if (!res.ok || res.redirected) { stop(); return; }

            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');

            regions().forEach(region => {
                const fresh = doc.querySelector(`[data-live="${region.dataset.live}"]`);
                if (!fresh || fresh.innerHTML === region.innerHTML || isBusy(region)) return;

                const before = keysIn(region);
                region.innerHTML = fresh.innerHTML;
                region.querySelectorAll('[data-live-key]').forEach(el => {
                    if (!before.has(el.dataset.liveKey)) el.classList.add('live-new');
                });
            });

            document.dispatchEvent(new CustomEvent('live-refresh:updated'));
        } catch (e) {
            // Network hiccup: try again on the next tick.
        } finally {
            busy = false;
        }
    }

    function start() { if (!timer) timer = setInterval(refresh, interval); }
    function stop() { clearInterval(timer); timer = null; }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { stop(); } else { refresh(); start(); }
    });

    start();
})();
