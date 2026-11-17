<?php

namespace App\Satellite;

use App\Entity\CodeTerrain;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Copernicus Data Space Ecosystem (ESA / UE) – Sentinel Hub Statistical API sur Sentinel-2 L2A.
 * Gratuit (compte + client OAuth) : https://dataspace.copernicus.eu
 * Calcule NDVI, EVI et NDMI moyens sur le contour du terrain, par période de 5 jours,
 * en masquant nuages / ombres / neige grâce à la couche SCL.
 * Identifiants : CDSE_CLIENT_ID et CDSE_CLIENT_SECRET dans .env.local
 */
class CopernicusProvider implements SatelliteProviderInterface
{
    private const TOKEN_URL = 'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token';
    private const STATS_URL = 'https://sh.dataspace.copernicus.eu/api/v1/statistics';

    private const EVALSCRIPT = <<<'JS'
//VERSION=3
function setup() {
  return {
    input: [{ bands: ["B02", "B04", "B08", "B11", "SCL", "dataMask"] }],
    output: [
      { id: "indices", bands: 3, sampleType: "FLOAT32" },
      { id: "dataMask", bands: 1 }
    ]
  };
}
function evaluatePixel(s) {
  // SCL : 3 ombre, 8-9 nuages, 10 cirrus, 11 neige → pixels ignorés
  var clair = [3, 8, 9, 10, 11].indexOf(s.SCL) === -1;
  var ndvi = (s.B08 - s.B04) / (s.B08 + s.B04);
  var evi = 2.5 * (s.B08 - s.B04) / (s.B08 + 6 * s.B04 - 7.5 * s.B02 + 1);
  var ndmi = (s.B08 - s.B11) / (s.B08 + s.B11);
  var ok = s.dataMask && clair && isFinite(ndvi) ? 1 : 0;
  return { indices: [ndvi, evi, ndmi], dataMask: [ok] };
}
JS;

    public function __construct(
        private HttpClientInterface $http,
        private CacheItemPoolInterface $cache,
        #[Autowire('%env(default::CDSE_CLIENT_ID)%')] private ?string $clientId = null,
        #[Autowire('%env(default::CDSE_CLIENT_SECRET)%')] private ?string $clientSecret = null,
    ) {
    }

    public function getNom(): string
    {
        return 'cdse';
    }

    public function estDisponible(): bool
    {
        return $this->clientId && $this->clientSecret;
    }

    public function fetch(CodeTerrain $terrain, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $body = [
            'input' => [
                'bounds' => [
                    'geometry' => $terrain->getGeometrie(),
                    'properties' => ['crs' => 'http://www.opengis.net/def/crs/OGC/1.3/CRS84'],
                ],
                'data' => [['type' => 'sentinel-2-l2a', 'dataFilter' => ['maxCloudCoverage' => 70]]],
            ],
            'aggregation' => [
                'timeRange' => ['from' => $debut->format('Y-m-d\T00:00:00\Z'), 'to' => $fin->format('Y-m-d\T23:59:59\Z')],
                'aggregationInterval' => ['of' => 'P5D'],
                'evalscript' => self::EVALSCRIPT,
                // ~10 m exprimés en degrés (CRS84)
                'resx' => 0.0001,
                'resy' => 0.0001,
            ],
        ];

        $res = $this->http->request('POST', self::STATS_URL, [
            'auth_bearer' => $this->token(),
            'json' => $body,
            'timeout' => 60,
        ])->toArray();

        $out = [];
        foreach ($res['data'] ?? [] as $interval) {
            $bands = $interval['outputs']['indices']['bands'] ?? null;
            $st = $bands['B0']['stats'] ?? null;
            if (!$st || ($st['sampleCount'] ?? 0) === 0 || ($st['sampleCount'] - ($st['noDataCount'] ?? 0)) <= 0 || !is_numeric($st['mean'] ?? null)) {
                continue; // période entièrement nuageuse
            }
            $total = $st['sampleCount'];
            $valides = $total - ($st['noDataCount'] ?? 0);
            $out[] = [
                'date' => substr($interval['interval']['from'], 0, 10),
                'ndvi' => $st['mean'],
                'ndviMin' => $st['min'] ?? null,
                'ndviMax' => $st['max'] ?? null,
                'evi' => $bands['B1']['stats']['mean'] ?? null,
                'ndmi' => $bands['B2']['stats']['mean'] ?? null,
                'nuages' => round(100 * (1 - $valides / $total), 1),
            ];
        }
        return $out;
    }

    private function token(): string
    {
        $item = $this->cache->getItem('cdse_token_' . md5((string) $this->clientId));
        if ($item->isHit()) {
            return $item->get();
        }
        $res = $this->http->request('POST', self::TOKEN_URL, [
            'body' => ['grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret],
            'timeout' => 20,
        ])->toArray();

        $item->set($res['access_token'])->expiresAfter(max(60, (int) ($res['expires_in'] ?? 600) - 60));
        $this->cache->save($item);
        return $res['access_token'];
    }
}
