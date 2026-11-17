<?php

namespace App\Satellite;

use App\Entity\CodeTerrain;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Earth Search (Element 84, catalogue STAC public des données Sentinel-2 sur AWS) – sans clé.
 * Donne la liste des passages Sentinel-2 au-dessus du terrain, la couverture nuageuse
 * et un aperçu (vignette) de chaque scène. Ne calcule pas le NDVI (voir CDSE / Agromonitoring).
 * Désactivable avec EARTHSEARCH_ENABLED=0.
 */
class EarthSearchProvider implements SatelliteProviderInterface
{
    private const URL = 'https://earth-search.aws.element84.com/v1/search';

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(default::EARTHSEARCH_ENABLED)%')] private ?string $enabled = null,
    ) {
    }

    public function getNom(): string
    {
        return 'earthsearch';
    }

    public function estDisponible(): bool
    {
        return $this->enabled === null || $this->enabled === '' || filter_var($this->enabled, FILTER_VALIDATE_BOOLEAN);
    }

    public function fetch(CodeTerrain $terrain, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $res = $this->http->request('POST', self::URL, [
            'json' => [
                'collections' => ['sentinel-2-l2a'],
                'intersects' => $terrain->getGeometrie(),
                'datetime' => $debut->format('Y-m-d\T00:00:00\Z') . '/' . $fin->format('Y-m-d\T23:59:59\Z'),
                'limit' => 100,
                'sortby' => [['field' => 'properties.datetime', 'direction' => 'asc']],
            ],
            'headers' => ['Accept' => 'application/geo+json'],
            'timeout' => 30,
        ])->toArray();

        $parDate = [];
        foreach ($res['features'] ?? [] as $f) {
            $date = substr($f['properties']['datetime'] ?? '', 0, 10);
            if (!$date) {
                continue;
            }
            $nuages = $f['properties']['eo:cloud_cover'] ?? null;
            if (isset($parDate[$date]) && ($parDate[$date]['nuages'] ?? 100) <= ($nuages ?? 100)) {
                continue;
            }
            $parDate[$date] = ['date' => $date, 'nuages' => $nuages, 'imageUrl' => $f['assets']['thumbnail']['href'] ?? null];
        }
        return array_values($parDate);
    }
}
