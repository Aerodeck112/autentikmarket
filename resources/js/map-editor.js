import { createViewport, slotSvg, esc } from './svg-viewport';

/**
 * EDITOR HARTĂ (CRM) — SVG, totul în browser.
 * Adăugare, mutare (drag), resize (mâner), rotire, duplicare, ștergere, numerotare, preț, electricitate, blocare.
 * Salvarea trimite tot layout-ul (PUT JSON); serverul validează și sincronizează.
 */
export function mapEditor(config) {
    let tempId = -1;
    return {
        layout: { width_m: 40, height_m: 30, background_url: null, background_opacity: 0.6, name: '' },
        slots: [],
        selectedId: null,
        snap: 0.5,
        prefix: config.prefix || 'ST',
        dirty: false,
        saving: false,
        message: null,
        error: null,
        history: [],
        vp: null,
        drag: null,

        get selected() { return this.slots.find((s) => s.id === this.selectedId) || null; },

        async init() {
            this.vp = createViewport(this.$refs.svg, () => ({ width: this.layout.width_m, height: this.layout.height_m }));
            const r = await window.amFetch(config.dataUrl);
            if (r.ok) {
                this.layout = r.data.layout;
                this.slots = r.data.slots.map((s) => ({ ...s, price: s.price === null ? '' : s.price }));
            }
            this.render();
            this.vp.fit();

            const svg = this.$refs.svg;
            svg.addEventListener('pointerdown', (e) => this.onDown(e));
            window.addEventListener('pointermove', (e) => this.onMove(e));
            window.addEventListener('pointerup', () => this.onUp());
            svg.addEventListener('click', (e) => {
                if (this.vp.wasDrag()) return;
                if (!e.target.closest('[data-id]') && !e.target.closest('[data-handle]')) { this.selectedId = null; this.render(); }
            });
            window.addEventListener('keydown', (e) => this.onKey(e));
            window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
            this.$watch('selectedId', () => this.render());
        },

        snapshot() {
            this.history.push(JSON.stringify(this.slots));
            if (this.history.length > 60) this.history.shift();
            this.dirty = true;
        },
        undo() {
            const prev = this.history.pop();
            if (prev) { this.slots = JSON.parse(prev); this.render(); this.dirty = true; }
        },

        round(v) { return Math.round(v * 100) / 100; },
        snapV(v) { return this.snap > 0 ? Math.round(v / this.snap) * this.snap : this.round(v); },

        render() {
            const L = this.layout;
            const grid = `<defs><pattern id="g1" width="1" height="1" patternUnits="userSpaceOnUse"><path d="M1 0H0V1" fill="none" stroke="#e9ddbf" stroke-width="0.02"/></pattern>
                <pattern id="g5" width="5" height="5" patternUnits="userSpaceOnUse"><path d="M5 0H0V5" fill="none" stroke="#d9c79a" stroke-width="0.05"/></pattern></defs>`;
            const base = `<rect x="0" y="0" width="${L.width_m}" height="${L.height_m}" fill="#fffdf7"/>`;
            const bg = L.background_url ? `<image href="${esc(L.background_url)}" x="0" y="0" width="${L.width_m}" height="${L.height_m}" preserveAspectRatio="none" opacity="${L.background_opacity}"/>` : '';
            const gridRect = `<rect x="0" y="0" width="${L.width_m}" height="${L.height_m}" fill="url(#g1)"/><rect x="0" y="0" width="${L.width_m}" height="${L.height_m}" fill="url(#g5)" stroke="#133e23" stroke-width="0.08"/>`;
            const stands = this.slots.map((s) => {
                const sel = s.id === this.selectedId;
                const fill = s.is_blocked ? '#e7e4dc' : (sel ? '#f7ebc8' : '#e6f3dc');
                const stroke = sel ? '#dd8d2b' : (s.is_blocked ? '#a39d8f' : '#75bd50');
                return slotSvg(s, fill, stroke, '#133e23', 'data-drag="1"');
            }).join('');
            let handles = '';
            const s = this.selected;
            if (s) {
                const cx = s.x + s.width / 2, cy = s.y + s.height / 2;
                handles = `<g transform="translate(${cx} ${cy}) rotate(${s.rotation || 0})">
                    <rect x="${-s.width / 2}" y="${-s.height / 2}" width="${s.width}" height="${s.height}" fill="none" stroke="#dd8d2b" stroke-width="0.06" stroke-dasharray="0.25 0.15" pointer-events="none"/>
                    <circle data-handle="resize" cx="${s.width / 2}" cy="${s.height / 2}" r="0.35" fill="#dd8d2b" stroke="#fff" stroke-width="0.08" style="cursor:nwse-resize"/>
                    <line x1="0" y1="${-s.height / 2}" x2="0" y2="${-s.height / 2 - 0.9}" stroke="#dd8d2b" stroke-width="0.06" pointer-events="none"/>
                    <circle data-handle="rotate" cx="0" cy="${-s.height / 2 - 1}" r="0.3" fill="#fff" stroke="#dd8d2b" stroke-width="0.08" style="cursor:grab"/>
                </g>`;
            }
            this.$refs.layer.innerHTML = grid + base + bg + gridRect + stands + handles;
        },

        onDown(e) {
            const handle = e.target.closest('[data-handle]');
            const g = e.target.closest('[data-id]');
            const p = this.vp.toSvg(e.clientX, e.clientY);
            if (handle && this.selected) {
                e.stopPropagation();
                this.snapshot();
                this.drag = { type: handle.dataset.handle, start: p, orig: { ...this.selected } };
                return;
            }
            if (g) {
                e.stopPropagation();
                const id = Number(g.dataset.id);
                this.selectedId = id;
                this.snapshot();
                this.drag = { type: 'move', start: p, orig: { ...this.selected }, moved: false };
            }
        },

        onMove(e) {
            if (!this.drag || !this.selected) return;
            const p = this.vp.toSvg(e.clientX, e.clientY);
            const s = this.selected, o = this.drag.orig;
            const dx = p.x - this.drag.start.x, dy = p.y - this.drag.start.y;
            if (this.drag.type === 'move') {
                s.x = this.snapV(o.x + dx);
                s.y = this.snapV(o.y + dy);
            } else if (this.drag.type === 'resize') {
                const a = -(o.rotation || 0) * Math.PI / 180;
                const lx = dx * Math.cos(a) - dy * Math.sin(a);
                const ly = dx * Math.sin(a) + dy * Math.cos(a);
                s.width = Math.max(0.5, this.snapV(o.width + lx));
                s.height = Math.max(0.5, this.snapV(o.height + ly));
                // păstrăm colțul stânga-sus pe loc pentru rotație 0
                if (!o.rotation) { s.x = o.x; s.y = o.y; } else {
                    s.x = this.round(o.x + o.width / 2 - s.width / 2);
                    s.y = this.round(o.y + o.height / 2 - s.height / 2);
                }
            } else if (this.drag.type === 'rotate') {
                const cx = o.x + o.width / 2, cy = o.y + o.height / 2;
                let deg = Math.atan2(p.y - cy, p.x - cx) * 180 / Math.PI + 90;
                if (!e.altKey) deg = Math.round(deg / 15) * 15;
                s.rotation = ((Math.round(deg) % 360) + 360) % 360;
            }
            this.dirty = true;
            this.render();
        },

        onUp() { this.drag = null; },

        onKey(e) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
            const s = this.selected;
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); this.undo(); return; }
            if (!s) return;
            const step = e.shiftKey ? 1 : 0.1;
            const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (moves[e.key]) {
                e.preventDefault();
                this.snapshot();
                s.x = this.round(s.x + moves[e.key][0]);
                s.y = this.round(s.y + moves[e.key][1]);
                this.render();
            } else if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault(); this.remove();
            } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
                e.preventDefault(); this.duplicate();
            } else if (e.key.toLowerCase() === 'r') {
                this.rotateBy(e.shiftKey ? -90 : 90);
            }
        },

        nextCode() {
            const nums = this.slots.map((s) => parseInt(String(s.code).split('-').pop(), 10)).filter((n) => !isNaN(n));
            const n = (nums.length ? Math.max(...nums) : 0) + 1;
            return `${this.prefix}-${String(n).padStart(2, '0')}`;
        },

        add() {
            this.snapshot();
            const v = this.vp.view;
            const slot = {
                id: tempId--, code: this.nextCode(), label: '', width: 3, height: 3, rotation: 0,
                x: this.snapV(Math.min(Math.max(0, v.x + v.w / 2 - 1.5), this.layout.width_m - 3)),
                y: this.snapV(Math.min(Math.max(0, v.y + v.h / 2 - 1.5), this.layout.height_m - 3)),
                price: '', has_electricity: false, is_blocked: false, notes: '', features: [],
            };
            this.slots.push(slot);
            this.selectedId = slot.id;
            this.render();
        },

        duplicate() {
            const s = this.selected;
            if (!s) return;
            this.snapshot();
            const copy = { ...s, id: tempId--, code: this.nextCode(), x: this.round(s.x + s.width + 0.5), y: s.y };
            this.slots.push(copy);
            this.selectedId = copy.id;
            this.render();
        },

        remove() {
            if (!this.selected) return;
            this.snapshot();
            this.slots = this.slots.filter((s) => s.id !== this.selectedId);
            this.selectedId = null;
            this.render();
        },

        rotateBy(deg) {
            const s = this.selected;
            if (!s) return;
            this.snapshot();
            s.rotation = (((s.rotation || 0) + deg) % 360 + 360) % 360;
            this.render();
        },

        renumber() {
            if (!confirm(config.labels.confirmRenumber)) return;
            this.snapshot();
            const sorted = [...this.slots].sort((a, b) => (Math.round(a.y) - Math.round(b.y)) || (a.x - b.x));
            sorted.forEach((s, i) => { s.code = `${this.prefix}-${String(i + 1).padStart(2, '0')}`; });
            this.render();
        },

        updateField() { this.dirty = true; this.render(); },

        zoomIn() { this.vp.zoom(1.3); },
        zoomOut() { this.vp.zoom(1 / 1.3); },
        fit() { this.vp.fit(); },

        async save() {
            this.saving = true; this.error = null; this.message = null;
            const payload = {
                layout: { width_m: Number(this.layout.width_m), height_m: Number(this.layout.height_m), background_opacity: Number(this.layout.background_opacity), name: this.layout.name },
                slots: this.slots.map((s) => ({
                    id: s.id > 0 ? s.id : null, code: String(s.code).trim(), label: s.label || null,
                    x: Number(s.x), y: Number(s.y), width: Number(s.width), height: Number(s.height), rotation: Number(s.rotation || 0),
                    price: s.price === '' || s.price === null ? null : Number(s.price),
                    has_electricity: !!s.has_electricity, is_blocked: !!s.is_blocked, notes: s.notes || null,
                })),
            };
            const r = await window.amFetch(config.saveUrl, { method: 'PUT', body: JSON.stringify(payload) });
            this.saving = false;
            if (!r.ok) {
                this.error = r.data?.message || config.labels.saveError;
                if (r.data?.errors) this.error += ' ' + Object.values(r.data.errors).flat().slice(0, 3).join(' ');
                return;
            }
            this.slots = r.data.slots.map((s) => ({ ...s, price: s.price === null ? '' : s.price }));
            this.selectedId = null;
            this.dirty = false;
            this.history = [];
            this.message = config.labels.saved;
            this.render();
        },
    };
}
