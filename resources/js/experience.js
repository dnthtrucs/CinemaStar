const readable = document.getElementById('readableMode');
try { document.documentElement.classList.toggle('readable-mode', localStorage.getItem('cinemastar:readable') === '1'); } catch {}
function reflectReadable() { readable?.setAttribute('aria-pressed', String(document.documentElement.classList.contains('readable-mode'))); }
reflectReadable();
readable?.addEventListener('click', () => {
    const on = document.documentElement.classList.toggle('readable-mode');
    try { localStorage.setItem('cinemastar:readable', on ? '1' : '0'); } catch {}
    reflectReadable();
});
const install = document.getElementById('installApp');
let installPrompt;
window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); installPrompt = event; install.hidden = false; });
install?.addEventListener('click', async () => { if (!installPrompt) return; await installPrompt.prompt(); installPrompt = null; install.hidden = true; });
window.addEventListener('appinstalled', () => { install.hidden = true; });
if ('serviceWorker' in navigator && window.isSecureContext) window.addEventListener('load', () => { navigator.serviceWorker.register('/sw.js').catch(() => {}); });
