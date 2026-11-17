<?php

namespace App\Robot;

use App\Entity\CodeTerrain;
use App\Entity\Zone;

/**
 * Lieu sur lequel le robot fait ses estimations : un vrai terrain, ou un terrain « type » de 1 ha
 * au centre d'une zone (accueil visiteur, classement des cultures par zone).
 */
final class Site
{
    /**
     * @param ?bool $irrigue null = pratique habituelle de chaque culture (irriguée ou en sec)
     * @param array<string, int> $tailles élevage : effectif par clé (sinon effectif type du référentiel)
     */
    public function __construct(
        public readonly float $lat,
        public readonly float $lon,
        public readonly ?Zone $localite,
        public readonly ?Zone $gouvernorat,
        public readonly float $surface,
        public readonly ?bool $irrigue,
        public readonly ?CodeTerrain $terrain = null,
        public readonly array $tailles = [],
    ) {
    }

    public static function depuisTerrain(CodeTerrain $t): self
    {
        [$lat, $lon] = $t->getPoint();
        $tailles = [];
        foreach ($t->getCultivations() as $c) {
            if ($c->isActif() && $c->getStatut() !== 'termine' && $c->getEffectif() && Referentiel::estAnimal($c->getCultureCle())) {
                $tailles[$c->getCultureCle()] = ($tailles[$c->getCultureCle()] ?? 0) + $c->getEffectif();
            }
        }
        return new self($lat, $lon, $t->getLocalite(), $t->getGouvernorat(), max(0.01, $t->getSurface()), $t->isIrrigue(), $t, $tailles);
    }

    /** Terrain type de 1 ha au centre de la zone (gouvernorat ou localité). */
    public static function typeDansZone(Zone $z): self
    {
        $gouv = $z->getNiveau() === 'localite' ? $z->getParent() : ($z->getNiveau() === 'gouvernorat' ? $z : null);
        $loc = $z->getNiveau() === 'localite' ? $z : null;
        return new self((float) $z->getLatitude(), (float) $z->getLongitude(), $loc, $gouv, 1.0, null);
    }

    public function irriguePour(array $ref): bool
    {
        return $this->irrigue ?? (bool) ($ref['irrigationHabituelle'] ?? false);
    }

    public function zonePrix(): ?Zone
    {
        return $this->localite ?? $this->gouvernorat;
    }
}
