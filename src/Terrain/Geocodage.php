<?php

namespace App\Terrain;

use App\IA\GeminiClient;
use App\Meteo\MeteoService;
use App\Repository\ZoneRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Le robot trouve un terrain à partir de son adresse écrite librement
 * (« route de Kélibia km 5, Menzel Temime, Nabeul », « 9rib men Testour ») :
 *  1. l'IA (si une clé Gemini est configurée) remet l'adresse en forme : lieu-dit, délégation, gouvernorat ;
 *  2. OpenStreetMap (Nominatim) cherche l'adresse précise, puis Open-Meteo cherche la localité ;
 *  3. à défaut, le centre du gouvernorat reconnu dans le texte.
 * Il propose ensuite un contour carré de la surface déclarée, que l'agriculteur ajuste sur la carte.
 */
class Geocodage
{
    private const NOMINATIM = 'https://nominatim.openstreetmap.org/search';

    public function __construct(
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private MeteoService $meteo,
        private ZoneRepository $zones,
        private GeminiClient $gemini,
        private LoggerInterface $logger,
        #[Autowire('%env(default::METEO_DEMO)%')] private ?string $demo = null,
    ) {
    }

    /**
     * @return array{lat: float, lon: float, nom: string, gouvernorat: ?string, precision: string, source: string, lu: ?array}|null
     */
    public function localiser(string $adresse): ?array
    {
        $adresse = trim(mb_substr($adresse, 0, 300));
        if ($adresse === '') {
            return null;
        }
        $lu = $this->lireAdresse($adresse);
        $requetes = array_values(array_unique(array_filter([
            $lu['requete'] ?? null,
            $adresse,
            isset($lu['localite']) ? trim($lu['localite'] . ', ' . ($lu['gouvernorat'] ?? '')) : null,
        ])));

        if (!$this->estDemo()) {
            foreach ($requetes as $q) {
                if ($r = $this->nominatim($q)) {
                    return $r + ['lu' => $lu];
                }
            }
            foreach (array_filter([$lu['localite'] ?? null, $adresse]) as $q) {
                try {
                    foreach ($this->meteo->geocoder($q) as $g) {
                        return ['lat' => $g['lat'], 'lon' => $g['lon'], 'nom' => $g['nom'], 'gouvernorat' => $g['admin1'], 'precision' => 'localite', 'source' => 'Open-Meteo', 'lu' => $lu];
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning('Géocodage Open-Meteo impossible', ['erreur' => $e->getMessage()]);
                }
            }
        }

        $gouv = $this->zones->gouvernoratParNom($lu['gouvernorat'] ?? $adresse);
        if ($gouv && $gouv->getLatitude() !== null) {
            return ['lat' => $gouv->getLatitude(), 'lon' => $gouv->getLongitude(), 'nom' => $gouv->getNom(), 'gouvernorat' => $gouv->getNom(), 'precision' => 'gouvernorat', 'source' => 'centre du gouvernorat', 'lu' => $lu];
        }
        return null;
    }

    /**
     * Contour carré centré sur un point, de la surface demandée (GeoJSON Polygon, [lon, lat]).
     */
    public static function carre(float $lat, float $lon, float $hectares): array
    {
        $cote = sqrt(max(0.01, $hectares) * 10000); // mètres
        $dLat = $cote / 2 / 111320;
        $dLon = $cote / 2 / (111320 * max(0.2, cos(deg2rad($lat))));
        $ring = [
            [$lon - $dLon, $lat - $dLat], [$lon + $dLon, $lat - $dLat],
            [$lon + $dLon, $lat + $dLat], [$lon - $dLon, $lat + $dLat], [$lon - $dLon, $lat - $dLat],
        ];
        return ['type' => 'Polygon', 'coordinates' => [array_map(fn ($p) => [round($p[0], 7), round($p[1], 7)], $ring)]];
    }

    /** L'IA remet en forme l'adresse (français, derja, fautes) ; sans clé, découpe simple. */
    private function lireAdresse(string $adresse): ?array
    {
        if ($this->gemini->estDisponible()) {
            try {
                return $this->cache->get('adr_' . md5(mb_strtolower($adresse)), function (ItemInterface $item) use ($adresse) {
                    $item->expiresAfter(180 * 86400);
                    $r = $this->gemini->json(
                        'Tu aides à localiser des terrains agricoles en Tunisie. Tu réponds uniquement en JSON.',
                        "Adresse écrite par un agriculteur (français, arabe ou derja tunisienne) :\n« $adresse »\n"
                        . 'Réponds : {"lieu_dit": "...|null", "localite": "ville ou délégation", "gouvernorat": "un des 24 gouvernorats", "requete": "adresse propre pour une recherche OpenStreetMap"}'
                    );
                    return array_filter(array_map(fn ($v) => is_string($v) && $v !== '' && $v !== 'null' ? $v : null, $r)) + ['source' => 'IA'];
                });
            } catch (\Throwable $e) {
                $this->logger->warning('Lecture IA de l\'adresse impossible', ['erreur' => $e->getMessage()]);
            }
        }
        $parts = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $adresse))));
        $gouv = $this->zones->gouvernoratParNom($adresse);
        return ['localite' => $parts[0] ?? $adresse, 'gouvernorat' => $gouv?->getNom(), 'source' => 'texte'];
    }

    private function nominatim(string $q): ?array
    {
        try {
            $r = $this->cache->get('nomi_' . md5(mb_strtolower($q)), function (ItemInterface $item) use ($q) {
                $item->expiresAfter(90 * 86400);
                // Règles d'usage Nominatim : identifiant d'application, 1 requête/s au plus, résultats mis en cache
                return $this->http->request('GET', self::NOMINATIM, [
                    'query' => ['q' => $q, 'format' => 'jsonv2', 'countrycodes' => 'tn', 'limit' => 1, 'addressdetails' => 1, 'accept-language' => 'fr'],
                    'headers' => ['User-Agent' => 'PeasantGo.AI/1.0 (plateforme agricole tunisienne)'],
                    'timeout' => 15,
                ])->toArray();
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Nominatim indisponible', ['erreur' => $e->getMessage()]);
            return null;
        }
        $x = $r[0] ?? null;
        if (!$x) {
            return null;
        }
        $a = $x['address'] ?? [];
        return [
            'lat' => (float) $x['lat'], 'lon' => (float) $x['lon'],
            'nom' => $a['village'] ?? $a['town'] ?? $a['city'] ?? $a['municipality'] ?? $a['county'] ?? $x['name'] ?? $q,
            'gouvernorat' => isset($a['state']) ? preg_replace('/^(Gouvernorat (de |d\')?)/u', '', $a['state']) : null,
            'precision' => in_array($x['addresstype'] ?? '', ['road', 'house', 'farm', 'farmyard', 'hamlet', 'isolated_dwelling'], true) ? 'adresse' : 'localite',
            'source' => 'OpenStreetMap',
        ];
    }

    private function estDemo(): bool
    {
        return in_array(strtolower((string) $this->demo), ['1', 'true', 'yes', 'on'], true);
    }
}
