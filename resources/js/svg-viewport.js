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

    /** Încadrează o zonă anume (ex. doar standurile), cu proporțiile păstrate de viewBox. */
    const fitBox = (x, y, w, h) => {
        view.x = x; view.y = y; view.w = Math.max(w, 3); view.h = Math.max(h, 3);
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

    return { view, fit, fitBox, zoom, zoomAt, toSvg, wasDrag: () => dragMoved, apply };
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

/* ---------- DECOR (zone, clădiri, drumuri, etichete, marcaje) ---------- */

const AREA_STYLE = {
    ground: ['#fffdf7', 'none'],
    asphalt: ['#e8e3d8', 'none'],
    paved: ['#efe7d4', '#d9cba8'],
    green: ['#d6eac6', '#acd28f'],
    water: ['#d3e9f4', '#9fcbe3'],
    building: ['#f4e1cc', '#d6b48a'],
    landmark: ['#e5e2db', '#c8c0ae'],
    road: ['#dcd7cc', '#c6bfaf'],
    path: ['#f2e6c8', '#d8c69c'],
};
const TONE = { dark: '#1d5733', muted: '#56645a', light: '#faf3e1', accent: '#a35f0c' };
const SYMBOL_TEXT = { wc: 'WC', info: 'i', parking: 'P', entrance: '→', water: '≈', power: '⚡', statue: '★' };

const num = (v) => (Number.isFinite(Number(v)) ? Math.round(Number(v) * 100) / 100 : 0);
const pt = (p) => `${num(p[0])} ${num(p[1])}`;

/** Polilinie sau curbă Catmull-Rom (netezită) prin puncte. */
function pathD(points, smooth, closed) {
    if (!Array.isArray(points) || points.length < 2) return '';
    if (!smooth) return `M${points.map(pt).join('L')}${closed ? 'Z' : ''}`;
    let d = `M${pt(points[0])}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i - 1] || points[i], p1 = points[i], p2 = points[i + 1], p3 = points[i + 2] || p2;
        const c1 = [p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6];
        const c2 = [p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6];
        d += `C${pt(c1)} ${pt(c2)} ${pt(p2)}`;
    }
    return d + (closed ? 'Z' : '');
}

function textSvg(x, y, text, size, tone, rotation = 0, weight = 600) {
    if (!text) return '';
    const fill = TONE[tone] || TONE.muted;
    const halo = tone === 'light' ? '' : ` paint-order="stroke" stroke="#fffdf7" stroke-opacity="0.75" stroke-width="${num(size * 0.14)}"`;
    const rot = rotation ? ` transform="rotate(${num(rotation)} ${num(x)} ${num(y)})"` : '';
    return `<text x="${num(x)}" y="${num(y)}" font-size="${num(size)}" font-weight="${weight}" text-anchor="middle" dominant-baseline="middle" fill="${fill}"${halo}${rot} class="map-decor-text">${text}</text>`;
}

function markerSvg(el) {
    const [x, y] = [num(el.at?.[0]), num(el.at?.[1])];
    const r = num(el.r) || 1;
    let inner;
    if (el.symbol === 'church') {
        const s = r * 0.55;
        inner = `<path d="M${x} ${y - s}V${y + s}M${x - s * 0.6} ${y - s * 0.35}H${x + s * 0.6}" stroke="#faf3e1" stroke-width="${num(r * 0.18)}" stroke-linecap="round" fill="none"/>`;
    } else {
        const label = SYMBOL_TEXT[el.symbol] || '•';
        const fs = label.length > 1 ? r * 0.8 : r * 1.05;
        inner = `<text x="${x}" y="${y}" font-size="${num(fs)}" font-weight="700" text-anchor="middle" dominant-baseline="central" fill="#faf3e1">${esc(label)}</text>`;
    }
    return `<circle cx="${x}" cy="${y}" r="${r}" fill="#1d5733" stroke="#fffdf7" stroke-width="${num(r * 0.12)}"/>${inner}`;
}

function arrowSvg(el) {
    const pts = el.points || [];
    if (pts.length < 2) return '';
    const w = num(el.width) || 0.3;
    const entrance = el.kind === 'entrance';
    const color = entrance ? '#dd8d2b' : '#8a8475';
    const head = w * (entrance ? 2.4 : 4);
    const [a, b] = [pts[pts.length - 2], pts[pts.length - 1]];
    const ang = Math.atan2(b[1] - a[1], b[0] - a[0]);
    // linia se oprește la baza vârfului, ca să nu iasă de sub săgeată
    const base = [b[0] - Math.cos(ang) * head * 0.9, b[1] - Math.sin(ang) * head * 0.9];
    const line = [...pts.slice(0, -1), base];
    const side = (s) => [base[0] + Math.cos(ang + s * Math.PI / 2) * head * 0.6, base[1] + Math.sin(ang + s * Math.PI / 2) * head * 0.6];
    const dash = entrance ? '' : ` stroke-dasharray="${num(w * 2.5)} ${num(w * 1.6)}"`;
    return `<path d="${pathD(line, !!el.smooth, false)}" fill="none" stroke="${color}" stroke-width="${w}" stroke-linecap="round"${dash}/>
        <path d="M${pt(b)}L${pt(side(1))}L${pt(side(-1))}Z" fill="${color}"/>`;
}

/**
 * Decorul hărții → SVG. Elementele vin din server deja validate (App\Support\LayoutElements);
 * aici numerele sunt oricum normalizate și textele escapate.
 */
export function decorSvg(elements, lang = document.documentElement.lang || 'ro') {
    if (!Array.isArray(elements) || !elements.length) return '';
    const tr = (o) => esc(o ? (o[lang] || o.ro || o.hu || '') : '');
    const defs = '<defs><pattern id="am-tiles" width="3.75" height="3.75" patternUnits="userSpaceOnUse"><path d="M3.75 0H0V3.75" fill="none" stroke="#dccfb0" stroke-width="0.07"/></pattern></defs>';
    let shapes = '', texts = '';
    for (const el of elements) {
        if (el.type === 'area') {
            const [fill, stroke] = AREA_STYLE[el.kind] || AREA_STYLE.ground;
            const d = pathD(el.points, !!el.smooth, true);
            shapes += `<path d="${d}" fill="${fill}" stroke="${stroke}" stroke-width="0.08" stroke-linejoin="round"/>`;
            if (el.kind === 'paved') shapes += `<path d="${d}" fill="url(#am-tiles)"/>`;
            if (el.label) {
                const at = el.label_at || el.points[0];
                const tone = el.kind === 'building' ? 'accent' : (el.kind === 'landmark' ? 'muted' : 'dark');
                texts += textSvg(at[0], at[1], tr(el.label), num(el.label_size) || 1.2, tone, 0, 700);
            }
        } else if (el.type === 'arrow') {
            shapes += arrowSvg(el);
        } else if (el.type === 'marker') {
            texts += markerSvg(el);
            if (el.label) texts += textSvg(num(el.at[0]), num(el.at[1]) + num(el.r) + 0.9, tr(el.label), 0.8, 'muted');
        } else if (el.type === 'label') {
            texts += textSvg(el.at[0], el.at[1], tr(el.text), num(el.size) || 1, el.tone || 'muted', el.rotation || 0);
        }
    }
    return `<g class="map-decor" pointer-events="none" font-family="Inter Variable, Inter, system-ui, sans-serif">${defs}${shapes}${texts}</g>`;
}
