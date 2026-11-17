/*
 * PeasantGo.AI – utilitaires JavaScript communs à toutes les pages.
 *   api(url, {method, body})  → appel JSON vers le backend Symfony (même origine)
 *   esc(texte)                → échappe le HTML (évite l'injection dans innerHTML)
 *   toast(message, type)      → petite notification en haut à droite
 */
(function (w) {
    'use strict';

    async function api(url, options = {}) {
        const opts = {
            method: options.method || 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        };
        if (options.body instanceof FormData) {
            opts.body = options.body;
        } else if (options.body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(options.body);
        }

        let res;
        try {
            res = await fetch(url, opts);
        } catch (e) {
            throw Object.assign(new Error('Erreur de connexion au serveur. Veuillez réessayer.'), { network: true });
        }

        if (res.status === 401 || (res.redirected && res.url.includes('/login'))) {
            w.location.href = '/login';
            throw new Error('Session expirée, veuillez vous reconnecter.');
        }

        let data = {};
        const type = res.headers.get('Content-Type') || '';
        if (type.includes('application/json')) {
            data = await res.json();
        }
        if (!res.ok || data.success === false) {
            const err = new Error(data.message || (res.status === 403 ? 'Accès refusé.' : 'Une erreur est survenue (' + res.status + ').'));
            err.status = res.status;
            err.errors = data.errors || {};
            throw err;
        }
        return data;
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function toast(message, type = 'success') {
        let box = document.getElementById('pg-toasts');
        if (!box) {
            box = document.createElement('div');
            box.id = 'pg-toasts';
            box.style.cssText = 'position:fixed;top:80px;right:16px;z-index:2000;display:flex;flex-direction:column;gap:8px;max-width:360px';
            document.body.appendChild(box);
        }
        const colors = { success: '#2e7d32', danger: '#c62828', warning: '#ef6c00', info: '#0277bd' };
        const icons = { success: 'fa-check-circle', danger: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        const el = document.createElement('div');
        el.setAttribute('role', 'status');
        el.style.cssText = `background:#fff;border-left:5px solid ${colors[type] || colors.info};box-shadow:0 4px 14px rgba(0,0,0,.15);border-radius:8px;padding:12px 14px;font-size:.95rem;white-space:pre-line;transition:opacity .3s`;
        el.innerHTML = `<i class="fas ${icons[type] || icons.info} me-2" style="color:${colors[type] || colors.info}"></i>${esc(message)}`;
        box.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, type === 'danger' ? 6000 : 3500);
    }

    /** Affiche les erreurs de champ renvoyées par le serveur ({champ: message}) dans des éléments #<champ>Error. */
    function showFieldErrors(errors, map = {}) {
        Object.entries(errors || {}).forEach(([field, msg]) => {
            const el = document.getElementById((map[field] || field) + 'Error');
            if (el) { el.textContent = msg; }
        });
    }

    function formatDate(iso) {
        if (!iso) return '—';
        const [y, m, d] = iso.split('-');
        return `${d}/${m}/${y}`;
    }

    function formatNumber(n, digits = 2) {
        if (n === null || n === undefined || n === '') return '—';
        return Number(n).toLocaleString('fr-FR', { maximumFractionDigits: digits });
    }

    w.PG = { api, esc, toast, showFieldErrors, formatDate, formatNumber };
})(window);
