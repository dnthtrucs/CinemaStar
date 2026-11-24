const link = document.querySelector('[data-notification-feed]');
if (link) {
    const badge = link.querySelector('[data-unread-count]');
    const notice = document.getElementById('notificationNotice');
    const seen = new Set();
    let initialized = false;
    let busy = false;
    let stopped = false;
    document.getElementById('dismissNotificationNotice').addEventListener('click', () => { notice.hidden = true; });
    async function refresh() {
        if (busy || stopped || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(link.dataset.notificationFeed, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (response.status === 401) { stopped = true; return; }
            if (!response.ok) return;
            const data = await response.json();
            badge.textContent = data.unread;
            badge.hidden = data.unread === 0;
            link.setAttribute('aria-label', `Thông báo: ${data.unread} chưa đọc`);
            const fresh = data.items.filter(item => !seen.has(item.id));
            if (initialized && fresh.length) {
                document.getElementById('notificationNoticeText').textContent = fresh[0].title;
                notice.hidden = false;
            }
            data.items.forEach(item => seen.add(item.id));
            initialized = true;
        } catch { /* Keep the last count and retry without interrupting checkout. */ }
        finally { busy = false; }
    }
    refresh();
    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
}
