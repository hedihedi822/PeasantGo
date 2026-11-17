<?php

namespace App\IA;

use App\Entity\AnalyseIA;
use App\Entity\CodeTerrain;
use App\Entity\EstimationTerrain;
use App\Entity\ObservationSatellite;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * L'« IA » du robot :
 *  - conseil()        : rédige le conseil de culture à partir des chiffres CALCULÉS par le robot
 *                       (l'IA explique et hiérarchise, elle n'invente pas les chiffres) ;
 *  - analyserImage()  : lit la dernière image satellite du terrain (état de la végétation, anomalies) ;
 *  - extraire()       : transforme le texte d'une page web (site officiel, marché, vendeur d'intrants)
 *                       en données structurées (prix, intrants, statistiques) pour la veille.
 * Sans clé Gemini, des règles simples produisent un résultat équivalent mais moins riche.
 */
class AnalyseurIA
{
    private const CONSIGNE = "Tu es l'agronome du robot PeasantGo.AI, au service des agriculteurs tunisiens. "
        . "Tu réponds en français simple et concret. Tu n'inventes JAMAIS de chiffre : tu n'utilises que ceux fournis. "
        . "Ton but : aider l'agriculteur à cultiver ce qui sera rentable et à éviter de produire ce que le marché a déjà en excès.";

    public function __construct(
        private GeminiClient $gemini,
        private EntityManagerInterface $em,
        private HttpClientInterface $http,
        private LoggerInterface $logger,
    ) {
    }

    public function disponible(): bool
    {
        return $this->gemini->estDisponible();
    }

    /** @param EstimationTerrain[] $estimations */
    public function conseil(CodeTerrain $t, array $estimations, array $meteo, ?array $satellite): AnalyseIA
    {
        $donnees = [
            'terrain' => ['nom' => $t->getNom(), 'surface_ha' => $t->getSurface(), 'localite' => $t->getLocalite()?->getNom(), 'gouvernorat' => $t->getGouvernorat()?->getNom(), 'irrigue' => $t->isIrrigue()],
            'cultures' => array_map(fn (EstimationTerrain $e) => [
                'culture' => $e->getCultureNom(), 'semis' => $e->getDebutCultivation()->format('d/m/Y'), 'recolte' => $e->getDebutExploitation()->format('d/m/Y'),
                'taux_reussite_pct' => $e->getTauxReussite(), 'production_t' => $e->getProductionEstimee(), 'cout_dt' => $e->getCout(),
                'prix_dt_kg' => $e->getPrix(), 'benefice_dt' => $e->getBenefice(), 'exportation' => $e->isExportation(),
                'risque_climatique' => $e->getDetails()['climat']['principalRisque'] ?? null,
                'couverture_marche_gouvernorat_pct' => $e->getDetails()['prix']['couverture'] ?? null,
            ], array_slice($estimations, 0, 8)),
            'meteo_7_jours' => array_slice($meteo, 0, 7),
            'satellite' => $satellite,
        ];

        if ($this->disponible()) {
            try {
                $r = $this->gemini->json(self::CONSIGNE,
                    "Voici les résultats du robot pour ce terrain (JSON). Rédige un conseil.\n"
                    . "Réponds en JSON : {\"resume\": \"3 phrases max\", \"conseils\": [\"...\"], \"risques\": [\"...\"], \"derja\": \"une phrase de résumé en derja tunisienne (lettres latines)\"}.\n"
                    . "Classe les cultures par bénéfice et parle du risque de saturation du marché quand la couverture dépasse 100 %. Mentionne la météo des prochains jours si elle impose d'agir (pluie, gel, chaleur).\n\n"
                    . json_encode($donnees, JSON_UNESCAPED_UNICODE));
                return $this->enregistrer($t, 'conseil', (string) ($r['resume'] ?? ''), $r, $this->gemini->getModele());
            } catch (\Throwable $e) {
                $this->logger->warning('Gemini conseil indisponible', ['erreur' => $e->getMessage()]);
            }
        }
        return $this->enregistrer($t, 'conseil', ...$this->conseilRegles($donnees));
    }

    public function analyserImage(CodeTerrain $t, ObservationSatellite $obs, array $serieNdvi): AnalyseIA
    {
        $stats = ['date' => $obs->getDate()->format('Y-m-d'), 'source' => $obs->getSource(), 'ndvi' => $obs->getNdvi(), 'ndvi_min' => $obs->getNdviMin(), 'ndvi_max' => $obs->getNdviMax(), 'nuages_pct' => $obs->getNuages(), 'courbe_ndvi_campagne' => $serieNdvi];

        if ($this->disponible()) {
            try {
                $images = [];
                foreach (array_filter([$obs->getImageUrl(), $obs->getNdviImageUrl()]) as $url) {
                    $resp = $this->http->request('GET', $url, ['timeout' => 30, 'max_duration' => 40]);
                    $mime = $resp->getHeaders()['content-type'][0] ?? 'image/png';
                    $data = $resp->getContent();
                    if (strlen($data) < 4 * 1024 * 1024 && str_starts_with($mime, 'image/')) {
                        $images[] = ['mime' => explode(';', $mime)[0], 'data' => $data];
                    }
                }
                $r = $this->gemini->json(self::CONSIGNE,
                    "Analyse l'état du champ à partir de l'image satellite (couleurs réelles et/ou NDVI) et des statistiques NDVI ci-dessous.\n"
                    . "Réponds en JSON : {\"etat\": \"bon|moyen|faible|sol nu\", \"couverture_vegetale_pct\": nombre, \"anomalies\": [\"...\"], \"resume\": \"2 phrases\", \"conseils\": [\"...\"]}.\n\n"
                    . json_encode($stats, JSON_UNESCAPED_UNICODE), $images);
                return $this->enregistrer($t, 'satellite', (string) ($r['resume'] ?? ''), $r + ['stats' => $stats, 'images' => count($images)], $this->gemini->getModele());
            } catch (\Throwable $e) {
                $this->logger->warning('Gemini image indisponible', ['erreur' => $e->getMessage()]);
            }
        }

        $n = $obs->getNdvi();
        [$etat, $phrase] = match (true) {
            $n === null => ['inconnu', "L'image est trop nuageuse pour juger la végétation."],
            $n < 0.2 => ['sol nu', 'Le sol est presque nu : semis récent, jachère ou culture récoltée.'],
            $n < 0.4 => ['faible', 'La végétation est encore peu développée ou clairsemée.'],
            $n < 0.6 => ['moyen', 'La végétation couvre une bonne partie du champ.'],
            default => ['bon', 'La végétation est dense et vigoureuse.'],
        };
        $vals = array_values($serieNdvi);
        $anomalies = [];
        if (count($vals) >= 3 && max(array_slice($vals, -4)) - end($vals) > 0.12) {
            $anomalies[] = 'Baisse récente du NDVI : vérifier irrigation, maladie ou ravageurs.';
        }
        if ($obs->getNdviMax() !== null && $obs->getNdviMin() !== null && $obs->getNdviMax() - $obs->getNdviMin() > 0.45) {
            $anomalies[] = 'Champ très hétérogène : certaines zones poussent beaucoup moins bien.';
        }
        return $this->enregistrer($t, 'satellite', $phrase . ($n !== null ? sprintf(' (NDVI moyen %.2f le %s)', $n, $obs->getDate()->format('d/m/Y')) : ''),
            ['etat' => $etat, 'anomalies' => $anomalies, 'stats' => $stats], 'regles');
    }

    /**
     * Extraction de données d'une page web pour la veille.
     * @return array{prix_marche: list<array>, intrants: list<array>, statistiques: list<array>}
     */
    public function extraire(string $texte, string $typeSource, string $url): array
    {
        $vide = ['prix_marche' => [], 'intrants' => [], 'statistiques' => []];
        if (!$this->disponible()) {
            return $this->extraireRegles($texte, $typeSource) + $vide;
        }
        $r = $this->gemini->json(
            "Tu extrais des données agricoles chiffrées de pages web tunisiennes (français ou arabe). Tu ne gardes que les chiffres écrits dans la page, jamais d'estimation.",
            "Page : $url (type : $typeSource)\nExtrais en JSON :\n"
            . "{\"prix_marche\": [{\"produit\": \"\", \"lieu\": \"\", \"prix_dt_kg\": 0, \"date\": \"AAAA-MM-JJ\", \"type\": \"gros|detail|producteur\"}],\n"
            . " \"intrants\": [{\"intrant\": \"semence|engrais|ammonitrate|dap|potasse|traitement|gasoil|eau|location tracteur|moisson|transport\", \"libelle\": \"\", \"unite\": \"kg|litre|m3|heure|ha|sac 50 kg|quintal\", \"prix_dt\": 0, \"lieu\": \"\", \"vendeur\": \"\", \"date\": \"AAAA-MM-JJ\"}],\n"
            . " \"statistiques\": [{\"produit\": \"\", \"zone\": \"Tunisie ou gouvernorat\", \"annee\": 0, \"production_t\": null, \"surface_ha\": null, \"consommation_kg_hab\": null}]}\n"
            . "Convertis les prix au kg (1 quintal = 100 kg, millimes ÷ 1000). Date inconnue : null.\n\nTEXTE :\n" . mb_substr($texte, 0, 30000));
        return array_intersect_key($r, $vide) + $vide;
    }

    // ------------------------------------------------------------------

    private function conseilRegles(array $d): array
    {
        $cultures = $d['cultures'];
        if (!$cultures) {
            return ['Pas encore assez de données pour conseiller ce terrain.', ['conseils' => [], 'risques' => []], 'regles'];
        }
        $top = array_values(array_filter($cultures, fn ($c) => $c['benefice_dt'] > 0));
        $conseils = [];
        foreach (array_slice($top, 0, 3) as $c) {
            $conseils[] = sprintf('%s : semis vers le %s, %d %% de réussite, environ %s t, prix estimé %s DT/kg, bénéfice ≈ %s DT%s.',
                $c['culture'], $c['semis'], round($c['taux_reussite_pct']), number_format($c['production_t'], 1, ',', ' '),
                number_format($c['prix_dt_kg'], 2, ',', ' '), number_format($c['benefice_dt'], 0, ',', ' '), $c['exportation'] ? ', exportable' : '');
        }
        $risques = [];
        foreach ($cultures as $c) {
            if (($c['couverture_marche_gouvernorat_pct'] ?? 0) > 130) {
                $risques[] = sprintf('%s : le gouvernorat produit déjà %d %% de sa consommation, risque de prix bas.', $c['culture'], $c['couverture_marche_gouvernorat_pct']);
            }
        }
        foreach ($d['meteo_7_jours'] as $j) {
            if ($j['tmin'] < 1) {
                $risques[] = sprintf('Risque de gel le %s (%.1f °C).', date('d/m', strtotime($j['date'])), $j['tmin']);
                break;
            }
        }
        $pluie = array_sum(array_column($d['meteo_7_jours'], 'pluie'));
        if ($pluie > 40) {
            $risques[] = sprintf('Fortes pluies prévues (%d mm sur 7 jours) : attendre pour semer ou traiter.', $pluie);
        }
        $resume = $top
            ? sprintf('Pour « %s », la culture la plus rentable estimée est %s (bénéfice ≈ %s DT).', $d['terrain']['nom'], $top[0]['culture'], number_format($top[0]['benefice_dt'], 0, ',', ' '))
            : sprintf('Aucune culture n\'est estimée rentable sur « %s » cette saison avec les données actuelles.', $d['terrain']['nom']);
        $derja = $top ? sprintf('A7sen 7aja tezra3ha fi %s hiya %s.', $d['terrain']['nom'], $top[0]['culture']) : '';
        return [$resume, ['conseils' => $conseils, 'risques' => array_slice(array_unique($risques), 0, 5), 'derja' => $derja], 'regles'];
    }

    /** Sans IA : repère les lignes du type « Tomate ... 1,250 DT/kg » ou « ... 1250 millimes ». */
    private function extraireRegles(string $texte, string $type): array
    {
        $out = ['prix_marche' => []];
        if ($type === 'intrants') {
            return $out;
        }
        if (preg_match_all('/([A-Za-zÀ-ÿ\' ]{3,30})\s*[:\-–]?\s*(\d{1,4}(?:[.,]\d{1,3})?)\s*(DT|dinars?|millimes?|md)\s*(?:\/|par|le)?\s*(kg|kilo)/iu', $texte, $m, PREG_SET_ORDER)) {
            foreach (array_slice($m, 0, 50) as $x) {
                $prix = (float) str_replace(',', '.', $x[2]);
                if (preg_match('/^(millimes?|md)$/i', $x[3])) {
                    $prix /= 1000;
                }
                $out['prix_marche'][] = ['produit' => trim($x[1]), 'lieu' => null, 'prix_dt_kg' => $prix, 'date' => null, 'type' => 'gros'];
            }
        }
        return $out;
    }

    private function enregistrer(CodeTerrain $t, string $type, string $resume, array $details, string $modele): AnalyseIA
    {
        $a = new AnalyseIA($t, $type, $resume, $details, $modele);
        $this->em->persist($a);
        $this->em->flush();
        return $a;
    }
}
