import { createViewport, slotSvg } from './svg-viewport';

/**
 * Harta interactivă a standurilor (producător / public / CRM).
 * Fără WebSocket: polling JSON la N secunde.
 */
const COLORS = {
    available: ['#e3efe0', '#5d7550', '#233a2d'],
    hold: ['#fbe9c6', '#d9a43b', '#6b4f12'],
    requested: ['#fde2c8', '#c8742e', '#6b3410'],
    reserved: ['#e7d6cc', '#95421f', '#4a1f0c'],
    occupied: ['#e7d6cc', '#95421f', '#4a1f0c'],
    blocked: ['#e5e2dc', '#9c968c', '#6b665e'],
    unavailable: ['#e5e2dc', '#9c968c', '#6b665e'],
    mine: ['#2f4a3a', '#1e2620', '#f6f1e7'],
    selected: ['#b4532a', '#1e2620', '#ffffff'],
};

export function standMap(config) {
    return {
        layout: { width_m: 40, height_m: 30, background_url: null, background_opacity: 0.6 },
        slots: [],
        selected: null,
        loading: true,
        error: null,
        message: null,
        busy: false,
        vp: null,
        mode: config.mode || 'public',

        init() {
            this.vp = createViewport(this.$refs.svg, () => ({ width: this.layout.width_m, height: this.layout.height_m }));
            this.$refs.svg.addEventListener('click', (e) => {
                if (this.vp.wasDrag()) return;
                const g = e.target.closest('[data-id]');
                if (!g) { this.selected = null; this.render(); return; }
                const slot = this.slots.find((s) => String(s.id) === g.dataset.id);
                this.select(slot);
            });
            this.load(false);
            if (config.pollSeconds) {
                setInterval(() => { if (!document.hidden) this.load(true); }, config.pollSeconds * 1000);
            }
        },

        async load(silent) {
            if (!silent) this.loading = true;
            const r = await window.amFetch(config.dataUrl);
            this.loading = false;
            if (!r.ok) { this.error = r.data?.message || config.labels?.loadError || 'Error'; return; }
            const first = this.slots.length === 0;
            this.layout = r.data.layout;
            this.slots = r.data.slots;
            if (this.selected) this.selected = this.slots.find((s) => s.id === this.selected.id) || null;
            this.render();
            if (first) this.vp.fit();
        },

        colorsFor(slot) {
            if (this.selected && this.selected.id === slot.id) return COLORS.selected;
            if (slot.mine) return COLORS.mine;
            return COLORS[slot.status] || COLORS.unavailable;
        },

        render() {
            const bg = this.layout.background_url
                ? `<image href="${this.layout.background_url}" x="0" y="0" width="${this.layout.width_m}" height="${this.layout.height_m}" preserveAspectRatio="none" opacity="${this.layout.background_opacity}"/>`
                : '';
            const frame = `<rect x="0" y="0" width="${this.layout.width_m}" height="${this.layout.height_m}" fill="#fbf8f2" stroke="#e2d8c5" stroke-width="0.1"/>`;
            const stands = this.slots.map((s) => {
                const [f, st, t] = this.colorsFor(s);
                return slotSvg(s, f, st, t);
            }).join('');
            this.$refs.layer.innerHTML = frame + bg + stands;
        },

        select(slot) {
            this.selected = slot || null;
            this.message = null;
            this.render();
        },

        zoomIn() { this.vp.zoom(1.3); },
        zoomOut() { this.vp.zoom(1 / 1.3); },
        reset() { this.vp.fit(); },

        statusLabel(status) { return config.labels?.status?.[status] || status; },
        canChoose() { return this.mode === 'producer' && this.selected && this.selected.status === 'available' && config.canReserve; },

        async choose() {
            if (!this.canChoose() || this.busy) return;
            this.busy = true;
            this.message = null;
            const url = config.holdUrl.replace('__SLOT__', this.selected.id);
            const r = await window.amFetch(url, { method: 'POST', body: JSON.stringify({}) });
            this.busy = false;
            if (r.ok && r.data?.redirect) { window.location = r.data.redirect; return; }
            this.message = r.data?.message || config.labels?.genericError;
            await this.load(true);
        },
    };
}
