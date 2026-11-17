/*
 * Tableau du robot PeasantGo.AI : le même rendu pour les visiteurs et les membres
 * (mêmes colonnes ; chez le visiteur, certaines cases sont verrouillées).
 * Colonnes : Type, Culture, N° saison, Saisons/an, Période cultivation, Période exploitation,
 *            Taux de réussite, Production estimée, Exportation, Coût, Prix, Bénéfice estimé, (détail)
 */
window.PGRobot = (function () {
    const { esc, formatNumber } = window.PG;
    const t = k => (window.PG_T && window.PG_T[k]) || k;
    const css = document.createElement('style');
    css.textContent = `.robot-table th { white-space: nowrap; font-size: .82rem; } .robot-table td { font-size: .9rem; vertical-align: middle; }
        .robot-table .culture-link { font-weight: 600; color: var(--primary-color); text-decoration: none; }
        .robot-table .reco { background: #eaf6ee; } .robot-table .reco > td { background: transparent; } .badge-reco { background: var(--primary-color); }
        .robot-table .barre { height: 6px; border-radius: 3px; background: #e9ecef; overflow: hidden; min-width: 60px; } .robot-table .barre > div { height: 100%; }`;
    document.head.appendChild(css);
    const NB_COLONNES = 13;
    const LOCK = `<span class="text-muted" title="${t('Créez un compte gratuit pour voir cette valeur')}"><i class="fas fa-lock"></i></span>`;

    const jourMois = iso => { const [, m, d] = iso.split('-'); return `${d}/${m}`; };
    const periode = p => `${jourMois(p[0])} → ${jourMois(p[1])}`;
    const couleur = v => v >= 70 ? '#2e7d32' : v >= 45 ? '#ef6c00' : '#c62828';
    const singulier = u => u.replace(/^kg vif$/, 'kg').replace(/s$/, '');
    const estAnimal = l => (l.details && l.details.famille) === 'animal';

    function production(l) {
        if (l.productionEstimee === null) return LOCK;
        if (estAnimal(l)) {
            const [unite, kg] = l.details.affichage || ['kg', 1];
            const q = l.productionEstimee * 1000 / kg;
            return `<strong>${formatNumber(q, q > 100 ? 0 : 1)} ${esc(t(unite))}</strong><br><small class="text-muted">${l.details.taille} ${esc(t(l.details.unite))}</small>`;
        }
        const ha = l.details.surface;
        return `<strong>${formatNumber(l.productionEstimee, 1)} ${t('t')}</strong><br><small class="text-muted">${formatNumber(l.details.rendementHa, 1)} ${t('t/ha')}${ha ? ' · ' + formatNumber(ha) + ' ' + t('ha') : ''}</small>`;
    }
    function prix(l) {
        if (l.prix === null) return LOCK;
        if (estAnimal(l)) {
            const [unite, kg] = l.details.affichage || ['kg', 1];
            return `${formatNumber(l.prix * kg, kg < 0.5 ? 3 : 2)} ${t('DT')}/${esc(t(singulier(unite)))}`;
        }
        return `${formatNumber(l.prix, 2)} ${t('DT/kg')}`;
    }
    function cout(l) {
        if (l.verrou) {
            const n = estAnimal(l) ? l.details.taille : (l.details.surface || 1);
            return `${formatNumber(l.cout / n, 0)} ${t('DT')}/${estAnimal(l) ? esc(t(singulier(l.details.unite))) : t('ha')}`;
        }
        return `${formatNumber(l.cout, 0)} ${t('DT')}`;
    }
    function taux(l) {
        if (l.tauxReussite === null) return LOCK;
        return `<div class="d-flex align-items-center gap-2"><div class="barre"><div style="width:${l.tauxReussite}%;background:${couleur(l.tauxReussite)}"></div></div><strong style="color:${couleur(l.tauxReussite)}">${Math.round(l.tauxReussite)}%</strong></div>`;
    }
    function benefice(l) {
        if (l.benefice === null) return LOCK;
        return `<strong class="${l.benefice >= 0 ? 'text-success' : 'text-danger'}">${l.benefice >= 0 ? '+' : ''}${formatNumber(l.benefice, 0)} ${t('DT')}</strong>`;
    }

    /**
     * @param lignes  liste de lignes (format EstimationTerrain::toArray)
     * @param opts    { urlZones: '/robot/zones/CLE' (équipe seulement), detail: 'nomDeGroupe' (bouton Détail), vide: 'texte' }
     */
    function lignes(liste, opts = {}) {
        if (!liste.length) return `<tr><td colspan="${NB_COLONNES}" class="text-center text-muted py-3">${esc(opts.vide || t('Aucune ligne.'))}</td></tr>`;
        return liste.map((l, i) => `<tr class="${l.recommande ? 'reco' : ''}">
            <td>${esc(t(l.details.type || ''))}</td>
            <td>${opts.urlZones ? `<a class="culture-link" href="${opts.urlZones.replace('CLE', l.cle)}">${esc(t(l.nom))}</a>` : `<strong>${esc(t(l.nom))}</strong>`}${l.recommande ? ` <span class="badge badge-reco">${t('Recommandé')}</span>` : ''}</td>
            <td>${l.numeroSaison}</td>
            <td>${l.saisonsAn}</td>
            <td>${periode(l.cultivation)}</td>
            <td>${periode(l.exploitation)}</td>
            <td>${taux(l)}</td>
            <td>${production(l)}</td>
            <td>${l.exportation ? `<span class="badge bg-success">${t('Oui')}</span>` : `<span class="badge bg-secondary">${t('Non')}</span>`}</td>
            <td>${cout(l)}</td>
            <td>${prix(l)}</td>
            <td>${benefice(l)}</td>
            <td>${opts.detail && !l.verrou ? `<button type="button" class="btn btn-sm btn-outline-success text-nowrap" data-detail="${opts.detail}:${i}" title="${t('Détail du calcul')}"><i class="fas fa-calculator me-1"></i>${t('Détail')}</button>` : ''}</td>
        </tr>`).join('');
    }

    const ENTETE = `<thead>
        <tr><th rowspan="2">${t('Type')}</th><th rowspan="2">${t('Culture / élevage')}</th><th colspan="4" class="text-center">${t('Saison')}</th>
            <th rowspan="2">${t('Taux de réussite')}</th><th rowspan="2">${t('Production estimée')}</th><th rowspan="2">${t('Exportation')}</th>
            <th rowspan="2">${t('Coût')}</th><th rowspan="2">${t('Prix')}</th><th rowspan="2">${t('Bénéfice estimé')}</th><th rowspan="2"></th></tr>
        <tr><th>${t('N° saison')}</th><th>${t('Saisons/an')}</th><th>${t('Période cultivation')}</th><th>${t('Période exploitation')}</th></tr>
    </thead>`;

    return { lignes, ENTETE, NB_COLONNES, LOCK, periode, jourMois, singulier, estAnimal, t };
})();
