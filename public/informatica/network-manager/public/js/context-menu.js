/**
 * ContextMenu — menú contextual flotante genérico.
 *
 * Uso:
 *   const menu = new ContextMenu();
 *   menu.show(x, y, [
 *     { icon: 'fa-wifi',   label: 'Ping',    action: () => ... },
 *     { separator: true },
 *     { icon: 'fa-trash',  label: 'Eliminar', action: () => ..., danger: true },
 *   ]);
 */
class ContextMenu {
    constructor() {
        this._el = null;
        this._onDocClick = this._onDocClick.bind(this);
    }

    // ── Mostrar ────────────────────────────────────────────────
    show(x, y, items) {
        this.hide();

        const el = document.createElement('div');
        el.className = 'ctx-menu';
        el.innerHTML = items.map(item => {
            if (item.separator) return '<div class="ctx-sep"></div>';
            return `
                <button class="ctx-item${item.danger ? ' danger' : ''}${item.disabled ? ' disabled' : ''}"
                        data-idx="${items.indexOf(item)}" ${item.disabled ? 'disabled' : ''}>
                    <i class="fas ${item.icon}"></i>
                    <span>${item.label}</span>
                </button>`;
        }).join('');

        document.body.appendChild(el);
        this._el    = el;
        this._items = items;

        // Position — keep inside viewport
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        el.style.visibility = 'hidden';
        el.style.display    = 'block';
        const w = el.offsetWidth;
        const h = el.offsetHeight;
        el.style.left = `${Math.min(x, vw - w - 8)}px`;
        el.style.top  = `${Math.min(y, vh - h - 8)}px`;
        el.style.visibility = '';

        el.addEventListener('click', (e) => {
            const btn = e.target.closest('.ctx-item');
            if (!btn || btn.disabled) return;
            const idx = parseInt(btn.dataset.idx, 10);
            const item = items[idx];
            if (item?.action) item.action();
            this.hide();
        });

        setTimeout(() => document.addEventListener('click', this._onDocClick), 0);
    }

    // ── Ocultar ────────────────────────────────────────────────
    hide() {
        this._el?.remove();
        this._el = null;
        document.removeEventListener('click', this._onDocClick);
    }

    _onDocClick() { this.hide(); }
}
