/*
 * PeasantGo.AI – carte des terrains (Leaflet) et panneau satellite du profil agricole.
 * Utilise les variables/fonctions de profil.html.twig : terrains, remplacerTerrain().
 */
(function (w) {
    'use strict';
    const { api, esc, toast, formatDate } = w.PG;

    // ------------------------------------------------------------------ Carte
    let map = null, drawn = null, terrainCourant = null;

    function surfaceHa(latlngs) {
        if (w.L && L.GeometryUtil && L.GeometryUtil.geodesicArea) {
            return L.GeometryUtil.geodesicArea(latlngs) / 10000;
        }
        return null;
    }

    function majSurface() {
        const el = document.getElementById('mapSurface');
        const layer = drawn && drawn.getLayers()[0];
        if (!layer) { el.textContent = ''; return; }
        const ha = surfaceHa(layer.getLatLngs()[0]);
        const t = terrains.find(x => x.id === terrainCourant);
        el.textContent = ha !== null ? `Surface dessinée : ${ha.toFixed(2)} ha (déclarée : ${t ? t.surface : '?'} ha)` : '';
    }

    function initCarte() {
        if (map) return;
        if (!w.L) { toast('La carte n\'a pas pu être chargée (connexion internet requise).', 'danger'); return; }
        map = L.map('terrainMap', { center: [34.0, 9.5], zoom: 6 });
        const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19, attribution: 'Imagerie © Esri, Maxar, Earthstar Geographics',
        }).addTo(map);
        const plan = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' });
        L.control.layers({ 'Satellite': satellite, 'Plan': plan }).addTo(map);

        drawn = new L.FeatureGroup().addTo(map);
        map.addControl(new L.Control.Draw({
            draw: { polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: '#2c7d4b' } }, polyline: false, rectangle: { shapeOptions: { color: '#2c7d4b' } }, circle: false, marker: false, circlemarker: false },
            edit: { featureGroup: drawn },
        }));
        map.on(L.Draw.Event.CREATED, e => { drawn.clearLayers(); drawn.addLayer(e.layer); majSurface(); });
        map.on(L.Draw.Event.EDITED, majSurface);
        map.on(L.Draw.Event.DELETED, majSurface);

        document.getElementById('mapLocate').addEventListener('click', () => map.locate({ setView: true, maxZoom: 16 }));
        map.on('locationerror', () => toast('Position introuvable (autorisez la géolocalisation).', 'warning'));
        const chercher = async () => {
            const q = document.getElementById('mapSearch').value.trim();
            if (!q) return;
            try {
                const r = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=tn&q=' + encodeURIComponent(q));
                const res = await r.json();
                if (res[0]) map.setView([res[0].lat, res[0].lon], 14); else toast('Lieu introuvable.', 'warning');
            } catch (e) { toast('Recherche indisponible.', 'danger'); }
        };
        document.getElementById('mapSearchBtn').addEventListener('click', chercher);
        document.getElementById('mapSearch').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); chercher(); } });
        document.getElementById('mapClear').addEventListener('click', async () => {
            if (!confirm('Effacer le contour de ce terrain ?')) return;
            await enregistrer(null);
        });
        document.getElementById('mapSave').addEventListener('click', () => {
            const layer = drawn.getLayers()[0];
            if (!layer) { toast('Dessinez d\'abord le contour.', 'warning'); return; }
            const ring = layer.getLatLngs()[0].map(p => [p.lng, p.lat]);
            ring.push(ring[0]);
            enregistrer({ type: 'Polygon', coordinates: [ring] });
        });
    }

    async function enregistrer(geometrie) {
        try {
            const res = await api(`/api/terrains/${terrainCourant}/geometrie`, { method: 'POST', body: { geometrie } });
            bootstrap.Modal.getInstance(document.getElementById('mapModal')).hide();
            remplacerTerrain(res.terrain);
            toast(geometrie ? 'Contour enregistré.' : 'Contour effacé.');
            if (res.avertissement) toast(res.avertissement, 'warning');
        } catch (e) { toast(e.message, 'danger'); }
    }

    w.ouvrirCarte = function (id) {
        if (!w.L || !w.bootstrap) {
            toast('La carte n\'a pas pu être chargée (connexion internet requise).', 'danger');
            return;
        }
        terrainCourant = id;
        const t = terrains.find(x => x.id === id);
        document.getElementById('mapTerrainNom').textContent = '« ' + t.nom + ' »';
        const modalEl = document.getElementById('mapModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalEl.addEventListener('shown.bs.modal', function once() {
            modalEl.removeEventListener('shown.bs.modal', once);
            initCarte();
            if (!map) return;
            map.invalidateSize();
            drawn.clearLayers();
            if (t.geometrie) {
                const poly = L.polygon(t.geometrie.coordinates[0].map(([lon, lat]) => [lat, lon]), { color: '#2c7d4b' });
                drawn.addLayer(poly);
                map.fitBounds(poly.getBounds(), { maxZoom: 17 });
            } else if (t.localisation) {
                document.getElementById('mapSearch').value = t.localisation;
            }
            majSurface();
        });
        modal.show();
    };

    // ------------------------------------------------------------------ Panneau satellite
    function graphique(obs, profils, noms, campagne) {
        const pts = obs.filter(o => o.ndvi !== null && (o.nuages === null || o.nuages <= 60));
        if (!pts.length) return '<p class="text-muted small">Pas encore d\'image exploitable (nuages ou terrain récent).</p>';
        const W = 560, H = 190, P = 30;
        const t0 = new Date(campagne).getTime(), t1 = t0 + 365 * 864e5;
        const x = d => P + (new Date(d).getTime() - t0) / (t1 - t0) * (W - 2 * P);
        const y = v => H - P + 8 - v * (H - 2 * P);
        const couleurs = ['#1f77b4', '#ff7f0e', '#9467bd'];
        let svg = `<svg class="ndvi-chart" viewBox="0 0 ${W} ${H}" width="100%" role="img" aria-label="Courbe NDVI">`;
        [0, 0.2, 0.4, 0.6, 0.8].forEach(v => { svg += `<line x1="${P}" x2="${W - P}" y1="${y(v)}" y2="${y(v)}" stroke="#eee"/><text x="2" y="${y(v) + 3}">${v.toFixed(1)}</text>`; });
        ['Sep', 'Nov', 'Jan', 'Mar', 'Mai', 'Jul'].forEach((m, i) => {
            const d = new Date(t0); d.setMonth(d.getMonth() + i * 2);
            svg += `<text x="${x(d)}" y="${H - 4}">${m}</text>`;
        });
        Object.entries(profils).forEach(([cle, serie], i) => {
            svg += `<polyline fill="none" stroke="${couleurs[i]}" stroke-width="1.5" stroke-dasharray="4 3" opacity=".75" points="${serie.map(([d, v]) => `${x(d)},${y(v)}`).join(' ')}"/>`;
        });
        svg += `<polyline fill="none" stroke="#2c7d4b" stroke-width="2.5" points="${pts.map(o => `${x(o.date)},${y(o.ndvi)}`).join(' ')}"/>`;
        pts.forEach(o => { svg += `<circle cx="${x(o.date)}" cy="${y(o.ndvi)}" r="3" fill="#2c7d4b"><title>${formatDate(o.date)} : NDVI ${o.ndvi.toFixed(2)} (${esc(o.source)})</title></circle>`; });
        svg += '</svg>';
        const legende = `<div class="small mt-1"><span style="color:#2c7d4b">━</span> NDVI mesuré &nbsp; ${Object.keys(profils).map((c, i) => `<span style="color:${couleurs[i]}">┅</span> profil ${esc(noms[c] || c)}`).join(' &nbsp; ')}</div>`;
        return svg + legende;
    }

    function render(id, d) {
        const zone = document.querySelector(`.sat-zone[data-sat="${id}"] .sat-content`);
        if (!zone) return;
        const sources = Object.entries(d.sources).map(([n, ok]) => `<span class="badge ${ok ? 'bg-success' : 'bg-secondary'} me-1" title="${ok ? 'activée' : 'non configurée'}">${esc(n)}</span>`).join('');
        const p = d.prediction;
        let pred = '<p class="text-muted small mb-0">La reconnaissance démarre dès 3 images sans nuages.</p>';
        if (p) {
            const pct = Math.round(p.confiance * 100);
            const niveau = pct >= 70 ? 'success' : pct >= 40 ? 'warning' : 'secondary';
            pred = `<div class="mb-2"><strong>Culture reconnue : ${esc(p.nom)}</strong> <span class="badge bg-${niveau}">confiance ${pct} %</span>
                ${p.conforme === true ? '<span class="badge bg-success ms-1"><i class="fas fa-check"></i> conforme à votre déclaration</span>' : ''}
                ${p.conforme === false ? `<span class="badge bg-danger ms-1">différente de votre déclaration (${esc(d.noms[p.declaree] || p.declaree)})</span>` : ''}</div>
                ${Object.entries(p.probabilites).map(([c, v]) => `<div class="d-flex align-items-center small mb-1"><span style="width:130px">${esc(d.noms[c] || c)}</span><div class="proba-bar flex-grow-1 me-2"><div style="width:${Math.round(v * 100)}%"></div></div><span style="width:40px">${Math.round(v * 100)}%</span></div>`).join('')}
                <p class="small text-muted mt-1 mb-0">${p.nbObservations} image(s) du ${formatDate(p.periode[0])} au ${formatDate(p.periode[1])} · ${p.nbExemples} terrain(s) déclaré(s) utilisés pour l'apprentissage.${pct < 40 ? ' En début de saison les cultures se ressemblent encore : la confiance augmentera avec les prochaines images.' : ''}</p>`;
        }
        const imgs = d.observations.filter(o => o.imageUrl || o.ndviImageUrl).slice(-6).reverse();
        const vignettes = imgs.length ? `<div class="d-flex flex-wrap gap-2 mt-2">${imgs.map(o => `<a href="${esc(o.ndviImageUrl || o.imageUrl)}" target="_blank" rel="noopener" title="${formatDate(o.date)} – ${esc(o.source)}${o.nuages !== null ? ' – nuages ' + Math.round(o.nuages) + '%' : ''}"><img class="sat-thumb" src="${esc(o.imageUrl || o.ndviImageUrl)}" alt="Image du ${formatDate(o.date)}" loading="lazy"></a>`).join('')}</div>` : '';
        zone.innerHTML = `
            <div class="small mb-2">Sources : ${sources} ${d.synchro ? `<span class="text-muted ms-2">dernière mise à jour ${esc(d.synchro)}</span>` : ''}</div>
            <div class="row g-3">
                <div class="col-lg-7">${graphique(d.observations, d.profils, d.noms, d.campagne)}</div>
                <div class="col-lg-5">${pred}</div>
            </div>${vignettes}`;
    }

    w.chargerSatellite = async function (id) {
        try { render(id, await api(`/api/terrains/${id}/satellite`)); } catch (e) { /* silencieux */ }
    };

    w.actualiserSatellite = async function (id, btn) {
        btn.disabled = true;
        const html = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Satellites…';
        try {
            const res = await api(`/api/terrains/${id}/satellite`, { method: 'POST' });
            toast(res.message);
            Object.entries(res.erreurs || {}).forEach(([s, m]) => toast(`${s} : ${m}`, 'warning'));
            await w.chargerSatellite(id);
        } catch (e) { toast(e.message, e.status === 429 ? 'info' : 'danger'); }
        btn.disabled = false;
        btn.innerHTML = html;
    };
})(window);
