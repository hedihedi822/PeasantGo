<?php

namespace App\Satellite;

use App\Entity\CodeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Agromonitoring (Agro API d'OpenWeather) – https://agromonitoring.com/api
 *  1. crée un polygone pour le terrain (une seule fois, id gardé dans code_terrain.agro_polygon_id)
 *  2. /ndvi/history  → NDVI moyen / min / max par image (Sentinel-2 et Landsat)
 *  3. /image/search  → liens des images (couleurs réelles + NDVI) et nuages
 * Clé : AGRO_API_KEY dans .env.local
 */
class AgromonitoringProvider implements SatelliteProviderInterface
{
    private const BASE = 'https://api.agromonitoring.com/agro/1.0';

    public function __construct(
        private HttpClientInterface $http,
        private EntityManagerInterface $em,
        #[Autowire('%env(default::AGRO_API_KEY)%')] private ?string $apiKey = null,
    ) {
    }

    public function getNom(): string
    {
        return 'agromonitoring';
    }

    public function estDisponible(): bool
    {
        return (bool) $this->apiKey;
    }

    public function fetch(CodeTerrain $terrain, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $polyId = $this->polygone($terrain);
        $q = ['polyid' => $polyId, 'start' => $debut->getTimestamp(), 'end' => $fin->getTimestamp(), 'appid' => $this->apiKey];

        $parDate = [];

        $ndvi = $this->http->request('GET', self::BASE . '/ndvi/history', ['query' => $q, 'timeout' => 30])->toArray();
        foreach ($ndvi as $r) {
            $date = gmdate('Y-m-d', (int) $r['dt']);
            $d = $r['data'] ?? [];
            // plusieurs images le même jour (S2 + Landsat) : on garde la moins nuageuse
            if (isset($parDate[$date]) && ($parDate[$date]['nuages'] ?? 100) <= ($r['cl'] ?? 100)) {
                continue;
            }
            $parDate[$date] = ['date' => $date, 'ndvi' => $d['mean'] ?? null, 'ndviMin' => $d['min'] ?? null, 'ndviMax' => $d['max'] ?? null, 'nuages' => $r['cl'] ?? null];
        }

        $images = $this->http->request('GET', self::BASE . '/image/search', ['query' => $q + ['clouds_max' => 80], 'timeout' => 30])->toArray();
        foreach ($images as $img) {
            $date = gmdate('Y-m-d', (int) $img['dt']);
            $parDate[$date] = ($parDate[$date] ?? ['date' => $date, 'nuages' => $img['cl'] ?? null]) + [
                'imageUrl' => $img['image']['truecolor'] ?? null,
                'ndviImageUrl' => $img['image']['ndvi'] ?? null,
            ];
        }

        ksort($parDate);
        return array_values($parDate);
    }

    private function polygone(CodeTerrain $terrain): string
    {
        if ($terrain->getAgroPolygonId()) {
            return $terrain->getAgroPolygonId();
        }
        $res = $this->http->request('POST', self::BASE . '/polygons', [
            'query' => ['appid' => $this->apiKey, 'duplicated' => 'true'],
            'json' => [
                'name' => sprintf('PeasantGo terrain %d', $terrain->getCodeTerrain()),
                'geo_json' => ['type' => 'Feature', 'properties' => new \stdClass(), 'geometry' => $terrain->getGeometrie()],
            ],
            'timeout' => 30,
        ])->toArray();

        $terrain->setAgroPolygonId((string) $res['id']);
        $this->em->flush();
        return (string) $res['id'];
    }
}
