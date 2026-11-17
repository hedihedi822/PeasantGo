<?php

namespace App\Satellite;

use App\Entity\CodeTerrain;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Source de DÉMONSTRATION (SATELLITE_DEMO=1) : génère une courbe NDVI réaliste à partir de la culture
 * déclarée sur le terrain (+ bruit et nuages), pour tester toute la chaîne sans clé API.
 * Ne jamais activer en production.
 */
class DemoProvider implements SatelliteProviderInterface
{
    public function __construct(#[Autowire('%env(default::SATELLITE_DEMO)%')] private ?string $demo = null)
    {
    }

    public function getNom(): string
    {
        return 'demo';
    }

    public function estDisponible(): bool
    {
        return filter_var($this->demo, FILTER_VALIDATE_BOOLEAN);
    }

    public function fetch(CodeTerrain $terrain, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $c = $terrain->getCulturePrincipale();
        $cle = $c ? ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom()) : null;
        $cles = array_keys(ProfilsCultures::PROFILS);
        $cle ??= $cles[$terrain->getCodeTerrain() % count($cles)];

        mt_srand((int) $terrain->getCodeTerrain() * 7919);
        $densite = 0.8 + mt_rand(0, 40) / 100;
        $decalage = mt_rand(-10, 10);
        $base = ProfilsCultures::PROFILS[$cle][1];

        $out = [];
        for ($d = \DateTimeImmutable::createFromInterface($debut); $d <= $fin; $d = $d->modify('+5 days')) {
            mt_srand((int) $terrain->getCodeTerrain() * 31 + (int) $d->format('Ymd'));
            $nuages = mt_rand(0, 100) < 25 ? mt_rand(60, 100) : mt_rand(0, 15);
            $t = ProfilsCultures::jour($d) - $decalage;
            $ndvi = $base + $densite * (ProfilsCultures::ndvi($cle, $t) - $base) + (mt_rand(-30, 30) / 1000);
            $out[] = [
                'date' => $d->format('Y-m-d'),
                'nuages' => $nuages,
                'ndvi' => $nuages > 60 ? null : max(0.02, min(0.95, $ndvi)),
                'evi' => $nuages > 60 ? null : max(0.0, min(0.9, $ndvi * 0.85)),
                'ndmi' => $nuages > 60 ? null : round($ndvi * 0.5 - 0.1, 3),
            ];
        }
        return $out;
    }
}
