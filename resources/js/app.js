import Alpine from 'alpinejs';
import { standMap } from './stand-map';
import { mapEditor } from './map-editor';
import { qrScanner } from './qr-scanner';
import { updateRunner } from './update-runner';
import { countdown } from './countdown';

window.Alpine = Alpine;

Alpine.data('standMap', standMap);
Alpine.data('mapEditor', mapEditor);
Alpine.data('qrScanner', qrScanner);
Alpine.data('updateRunner', updateRunner);
Alpine.data('countdown', countdown);

// Fetch JSON cu CSRF (toate apelurile AJAX trec prin Laravel CSRF).
window.amFetch = async (url, options = {}) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            ...(options.headers || {}),
        },
    });
    let data = null;
    try { data = await res.json(); } catch (e) { data = null; }
    return { ok: res.ok, status: res.status, data };
};

Alpine.start();
