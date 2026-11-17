<?php

namespace App\Satellite;

use App\Entity\CodeTerrain;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Une source d'images satellites.
 * fetch() renvoie une liste d'observations :
 *   [ ['date' => 'Y-m-d', 'ndvi' => ?float, 'ndviMin' => ?float, 'ndviMax' => ?float,
 *      'evi' => ?float, 'ndmi' => ?float, 'nuages' => ?float, 'imageUrl' => ?string, 'ndviImageUrl' => ?string], ... ]
 */
#[AutoconfigureTag]
interface SatelliteProviderInterface
{
    /** Identifiant court : agromonitoring, cdse, earthsearch, demo */
    public function getNom(): string;

    /** Vrai si la source est activée et configurée (clé API présente...). */
    public function estDisponible(): bool;

    /** @return list<array<string, mixed>> */
    public function fetch(CodeTerrain $terrain, \DateTimeInterface $debut, \DateTimeInterface $fin): array;
}
