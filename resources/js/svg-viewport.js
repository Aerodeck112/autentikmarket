/**
 * Pan / zoom pentru SVG (mouse, wheel, touch + pinch) — fără dependențe.
 * Coordonatele hărții sunt în METRI (viewBox).
 */
export function createViewport(svg, getBounds, onChange) {
    const view = { x: 0, y: 0, w: 10, h: 10 };
    const pointers = new Map();
    let pinchStart = null;
    let dragMoved = false;

    const apply = () => {
        svg.setAttribute('viewBox', `${view.x} ${view.y} ${view.w} ${view.h}`);
        onChange && onChange(view);
    };

    const toSvg = (clientX, clientY) => {
        const r = svg.getBoundingClientRect();
        const scale = Math.max(view.w / r.width, view.h / r.height);
        const offX = (r.width * scale - view.w) / 2;
        const offY = (r.height * scale - view.h) / 2;
        return { x: view.x - offX + (clientX - r.left) * scale, y: view.y - offY + (clientY - r.top) * scale, scale };
    };

    const fit = () => {
        const b = getBounds();
        const pad = Math.max(b.width, b.height) * 0.04;
        view.x = -pad; view.y = -pad; view.w = b.width + pad * 2; view.h = b.height + pad * 2;
        apply();
    };

    const zoomAt = (factor, cx, cy) => {
        const b = getBounds();
        const minW = 3, maxW = Math.max(b.width, b.height) * 3;
        const newW = Math.min(maxW, Math.max(minW, view.w / factor));
        const k = newW / view.w;
        view.x = cx - (cx - view.x) * k;
        view.y = cy - (cy - view.y) * k;
        view.w = newW;
        view.h = view.h * k;
        apply();
    };

    const zoom = (factor) => zoomAt(factor, view.x + view.w / 2, view.y + view.h / 2);

    svg.addEventListener('wheel', (e) => {
        e.preventDefault();
        const p = toSvg(e.clientX, e.clientY);
        zoomAt(e.deltaY < 0 ? 1.15 : 1 / 1.15, p.x, p.y);
    }, { passive: false });

    svg.addEventListener('pointerdown', (e) => {
        if (e.target.closest('[data-handle]') || e.target.closest('[data-drag]')) return; // editorul tratează separat
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        dragMoved = false;
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            pinchStart = { dist: Math.hypot(a.x - b.x, a.y - b.y), w: view.w };
        }
    });

    svg.addEventListener('pointermove', (e) => {
        if (!pointers.has(e.pointerId)) return;
        const prev = pointers.get(e.pointerId);
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (pointers.size === 2 && pinchStart) {
            const [a, b] = [...pointers.values()];
            const dist = Math.hypot(a.x - b.x, a.y - b.y);
            const mid = toSvg((a.x + b.x) / 2, (a.y + b.y) / 2);
            const targetW = pinchStart.w * (pinchStart.dist / Math.max(dist, 1));
            zoomAt(view.w / targetW, mid.x, mid.y);
            dragMoved = true;
            return;
        }
        const dx = e.clientX - prev.x, dy = e.clientY - prev.y;
        if (Math.abs(dx) + Math.abs(dy) > 2) dragMoved = true;
        if (dragMoved) {
            svg.setPointerCapture?.(e.pointerId);
            const { scale } = toSvg(e.clientX, e.clientY);
            view.x -= dx * scale;
            view.y -= dy * scale;
            apply();
        }
    });

    const end = (e) => {
        pointers.delete(e.pointerId);
        if (pointers.size < 2) pinchStart = null;
    };
    svg.addEventListener('pointerup', end);
    svg.addEventListener('pointercancel', end);
    svg.addEventListener('pointerleave', end);

    return { view, fit, zoom, zoomAt, toSvg, wasDrag: () => dragMoved, apply };
}

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export function slotSvg(slot, fill, stroke, textColor, extra = '') {
    const cx = slot.x + slot.width / 2;
    const cy = slot.y + slot.height / 2;
    const fs = Math.max(0.35, Math.min(slot.width, slot.height) * 0.28);
    const label = esc(slot.label || slot.code);
    return `<g class="stand" data-id="${slot.id}" transform="translate(${cx} ${cy}) rotate(${slot.rotation || 0})" ${extra}>
        <rect x="${-slot.width / 2}" y="${-slot.height / 2}" width="${slot.width}" height="${slot.height}" rx="0.18" fill="${fill}" stroke="${stroke}" stroke-width="0.08"/>
        ${slot.has_electricity ? `<text x="${slot.width / 2 - 0.35}" y="${-slot.height / 2 + 0.55}" font-size="0.5" text-anchor="middle" class="stand-label">⚡</text>` : ''}
        <text x="0" y="${fs * 0.36}" font-size="${fs}" text-anchor="middle" fill="${textColor}" class="stand-label">${label}</text>
    </g>`;
}
