/**
 * ============================================================
 *  NetMonitor Stealth — toast.js
 *  ToastManager: non-blocking notification system.
 *
 *  Usage:
 *      const toast = new ToastManager();
 *      toast.show('Escaneo completado', 'success');
 *      toast.show('Error de red',       'error');
 *      toast.show('Escaneando...',      'info');
 *
 *  Types: 'info' | 'success' | 'error' | 'warning'
 *
 *  Renders into #toastContainer if present, otherwise appends
 *  directly to document.body as fallback.
 * ============================================================
 */

class ToastManager {
    constructor() {
        this._container = document.getElementById('toastContainer') ?? document.body;
    }

    /**
     * Display a toast notification.
     * @param {string} message
     * @param {'info'|'success'|'error'|'warning'} type
     * @param {number} durationMs
     */
    show(message, type = 'info', durationMs = 3500) {
        const icons = {
            info:    'fa-info-circle',
            success: 'fa-check-circle',
            error:   'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
        };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${icons[type] ?? icons.info}"></i>
            <span>${this._esc(message)}</span>
            <button class="toast-close" aria-label="Cerrar">×</button>`;

        // Animate in
        toast.style.opacity   = '0';
        toast.style.transform = 'translateX(20px)';
        this._container.appendChild(toast);

        // Force reflow then transition in
        requestAnimationFrame(() => {
            toast.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            toast.style.opacity    = '1';
            toast.style.transform  = 'translateX(0)';
        });

        // Close button
        toast.querySelector('.toast-close')?.addEventListener('click', () => this._dismiss(toast));

        // Auto-dismiss
        setTimeout(() => this._dismiss(toast), durationMs);
    }

    _dismiss(toast) {
        toast.style.opacity   = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 220);
    }

    _esc(str) {
        const d = document.createElement('div');
        d.textContent = String(str ?? '');
        return d.innerHTML;
    }
}
