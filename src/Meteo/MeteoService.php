<?php

namespace App\Meteo;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Météo via Open-Meteo (https://open-meteo.com) – sans clé (usage non commercial gratuit).
 *  - prevision()  : 16 jours (températures, pluie, évapotranspiration, vent)
 *  - historique() : archives journalières ERA5 (depuis 1940)
 *  - geocoder()   : nom de lieu → coordonnées, population, gouvernorat
 * Les réponses sont mises en cache (3 h pour les prévisions, 30 jours pour l'historique).
 * METEO_DEMO=1 génère une météo méditerranéenne simulée (tests sans internet).
 */
class MeteoService
{
    private const FORECAST = 'https://api.open-meteo.com/v1/forecast';
    private const ARCHIVE = 'https://archive-api.open-meteo.com/v1/archive';
    private const GEOCODING = 'https://geocoding-api.open-meteo.com/v1/search';
    private const VARIABLES = 'temperature_2m_max,temperature_2m_min,precipitation_sum,et0_fao_evapotranspiration';

    public function __construct(
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        #[Autowire('%env(default::METEO_DEMO)%')] private ?string $demo = null,
        #[Autowire('%env(default::OPEN_METEO_API_KEY)%')] private ?string $apiKey = null,
    ) {
    }

    public function estDemo(): bool
    {
        return filter_var($this->demo, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return list<array{date: string, tmax: float, tmin: float, pluie: float, et0: ?float, vent: ?float, code: ?int}>
     */
    public function prevision(float $lat, float $lon): array
    {
        $lat = round($lat, 2);
        $lon = round($lon, 2);
        if ($this->estDemo()) {
            return $this->simuler($lat, $lon, new \DateTimeImmutable('today'), new \DateTimeImmutable('+15 days'), true);
        }
        return $this->cache->get("meteo_prev_{$lat}_{$lon}", function (ItemInterface $item) use ($lat, $lon) {
            $item->expiresAfter(3 * 3600);
            $r = $this->http->request('GET', self::FORECAST, ['query' => $this->q([
                'latitude' => $lat, 'longitude' => $lon, 'forecast_days' => 16, 'timezone' => 'auto',
                'daily' => self::VARIABLES . ',wind_speed_10m_max,weather_code',
            ]), 'timeout' => 20])->toArray();
            return $this->lignes($r['daily'] ?? []);
        });
    }

    /** Journées observées entre deux dates (archives). */
    public function historique(float $lat, float $lon, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $lat = round($lat, 1);
        $lon = round($lon, 1);
        $d = $debut->format('Y-m-d');
        $f = $fin->format('Y-m-d');
        if ($this->estDemo()) {
            return $this->simuler($lat, $lon, \DateTimeImmutable::createFromInterface($debut), \DateTimeImmutable::createFromInterface($fin), false);
        }
        return $this->cache->get("meteo_hist_{$lat}_{$lon}_{$d}_{$f}", function (ItemInterface $item) use ($lat, $lon, $d, $f) {
            $item->expiresAfter(30 * 86400);
            $r = $this->http->request('GET', self::ARCHIVE, ['query' => $this->q([
                'latitude' => $lat, 'longitude' => $lon, 'start_date' => $d, 'end_date' => $f,
                'timezone' => 'auto', 'daily' => self::VARIABLES,
            ]), 'timeout' => 60])->toArray();
            return $this->lignes($r['daily'] ?? []);
        });
    }

    /**
     * @return list<array{nom: string, lat: float, lon: float, population: ?int, admin1: ?string, pays: ?string}>
     */
    public function geocoder(string $lieu, string $pays = 'TN'): array
    {
        $lieu = trim(preg_split('/[,;]/', $lieu)[0] ?? $lieu);
        if ($lieu === '') {
            return [];
        }
        if ($this->estDemo()) {
            return [];
        }
        $cle = 'geo_' . md5(mb_strtolower($lieu) . $pays);
        return $this->cache->get($cle, function (ItemInterface $item) use ($lieu, $pays) {
            $item->expiresAfter(90 * 86400);
            $r = $this->http->request('GET', self::GEOCODING, ['query' => [
                'name' => $lieu, 'count' => 5, 'language' => 'fr', 'format' => 'json', 'countryCode' => $pays,
            ], 'timeout' => 15])->toArray();
            return array_map(fn ($x) => [
                'nom' => $x['name'], 'lat' => (float) $x['latitude'], 'lon' => (float) $x['longitude'],
                'population' => isset($x['population']) ? (int) $x['population'] : null,
                'admin1' => $x['admin1'] ?? null, 'pays' => $x['country_code'] ?? null,
            ], $r['results'] ?? []);
        });
    }

    private function q(array $q): array
    {
        if ($this->apiKey) {
            $q['apikey'] = $this->apiKey; // offre commerciale Open-Meteo
        }
        return $q;
    }

    private function lignes(array $daily): array
    {
        $out = [];
        foreach ($daily['time'] ?? [] as $i => $date) {
            if (!isset($daily['temperature_2m_max'][$i], $daily['temperature_2m_min'][$i])) {
                continue;
            }
            $out[] = [
                'date' => $date,
                'tmax' => (float) $daily['temperature_2m_max'][$i],
                'tmin' => (float) $daily['temperature_2m_min'][$i],
                'pluie' => (float) ($daily['precipitation_sum'][$i] ?? 0),
                'et0' => isset($daily['et0_fao_evapotranspiration'][$i]) ? (float) $daily['et0_fao_evapotranspiration'][$i] : null,
                'vent' => isset($daily['wind_speed_10m_max'][$i]) ? (float) $daily['wind_speed_10m_max'][$i] : null,
                'code' => isset($daily['weather_code'][$i]) ? (int) $daily['weather_code'][$i] : null,
            ];
        }
        return $out;
    }

    /** Climat méditerranéen simulé (plus chaud et plus sec vers le sud). */
    private function simuler(float $lat, float $lon, \DateTimeImmutable $debut, \DateTimeImmutable $fin, bool $prevision): array
    {
        $out = [];
        $sud = max(0, min(1, (37.3 - $lat) / 6.0)); // 0 = Bizerte, 1 = Tataouine
        $pluieAn = 600 - 480 * $sud;
        for ($d = $debut; $d <= $fin; $d = $d->modify('+1 day')) {
            $doy = (int) $d->format('z');
            mt_srand((int) $d->format('Ymd') + (int) round($lat * 100) * 7);
            $saison = cos(2 * M_PI * ($doy - 210) / 365); // 1 fin juillet, -1 fin janvier
            $tmoy = 19 + 3 * $sud + 8.5 * $saison + mt_rand(-25, 25) / 10;
            $amplitude = 9 + 3 * $sud;
            $pluieJour = 0.0;
            $probaPluie = 0.18 * (1 - $saison) / 2 + 0.01;
            if (mt_rand(0, 1000) / 1000 < $probaPluie) {
                $pluieJour = round(-log(max(1e-6, mt_rand(1, 1000) / 1000)) * $pluieAn / (365 * 0.09 * 1.0), 1);
            }
            $out[] = [
                'date' => $d->format('Y-m-d'),
                'tmax' => round($tmoy + $amplitude / 2, 1),
                'tmin' => round($tmoy - $amplitude / 2, 1),
                'pluie' => $pluieJour,
                'et0' => round(max(0.5, 3.5 + 2.8 * $saison + $sud), 1),
                'vent' => $prevision ? round(mt_rand(80, 350) / 10, 1) : null,
                'code' => $prevision ? ($pluieJour > 0 ? 61 : ($saison > 0 ? 0 : 2)) : null,
            ];
        }
        return $out;
    }
}
