<?php

namespace App\Satellite;

/**
 * Profils phénologiques de référence : évolution typique du NDVI d'une culture en Tunisie
 * au cours d'une campagne agricole (jour 0 = 1er septembre).
 *
 * Modèle « double logistique » :
 *   NDVI(t) = base + (pic - base) × [ 1/(1+e^-(t-montee)/k1)  -  1/(1+e^-(t-declin)/k2) ]
 *   montee = jour où la culture atteint la moitié de sa croissance (levée → couverture)
 *   declin = jour où elle a perdu la moitié de sa vigueur (maturité → récolte)
 *
 * Ces profils servent d'« a priori » au modèle ; ils sont ensuite corrigés par les vrais
 * terrains déclarés par les agriculteurs (voir ClassificateurCultures).
 */
final class ProfilsCultures
{
    /** cle => [nom, base, pic, montee, declin, k1, k2] */
    public const PROFILS = [
        'ble'           => ['Blé',             0.15, 0.75, 140, 255, 14, 12],
        'orge'          => ['Orge',            0.15, 0.68, 130, 240, 13, 11],
        'poisChiche'    => ['Pois chiche',     0.14, 0.55, 170, 270, 14, 10],
        'colza'         => ['Colza',           0.15, 0.72,  95, 265, 15, 12],
        'betterave'     => ['Betterave',       0.15, 0.80, 120, 290, 16, 14],
        'pommesDeTerre' => ['Pommes de terre', 0.15, 0.78, 175, 250, 10,  9],
        'tomates'       => ['Tomates',         0.16, 0.70, 225, 330, 12, 12],
        'pasteque'      => ['Pastèque',        0.16, 0.55, 240, 310, 10,  9],
        'mais'          => ['Maïs',            0.16, 0.80, 260, 355, 10, 10],
        'tournesol'     => ['Tournesol',       0.16, 0.66, 250, 320, 10,  9],
        'olivier'       => ['Olivier',         0.36, 0.42, 160, 260, 30, 30],
        'solNu'         => ['Sol nu / jachère', 0.13, 0.14, 150, 250, 30, 30],
    ];

    public static function nom(string $cle): string
    {
        return self::PROFILS[$cle][0] ?? $cle;
    }

    public static function existe(string $cle): bool
    {
        return isset(self::PROFILS[$cle]);
    }

    /** NDVI théorique de la culture au jour t de la campagne. */
    public static function ndvi(string $cle, float $t): float
    {
        [, $base, $pic, $montee, $declin, $k1, $k2] = self::PROFILS[$cle];
        $up = 1 / (1 + exp(-($t - $montee) / $k1));
        $down = 1 / (1 + exp(-($t - $declin) / $k2));
        return $base + ($pic - $base) * max(0, $up - $down);
    }

    /** Début (1er septembre) de la campagne contenant la date. */
    public static function debutCampagne(\DateTimeInterface $d): \DateTimeImmutable
    {
        $y = (int) $d->format('Y');
        if ((int) $d->format('n') < 9) {
            --$y;
        }
        return new \DateTimeImmutable("$y-09-01");
    }

    /** Jour de campagne (0 = 1er septembre). */
    public static function jour(\DateTimeInterface $d): int
    {
        return (int) self::debutCampagne($d)->diff(\DateTimeImmutable::createFromInterface($d))->format('%a');
    }

    /**
     * Retrouve le profil correspondant à une culture déclarée (clé du formulaire ou nom du catalogue).
     */
    public static function depuisDeclaration(?string $cle, ?string $nom = null): ?string
    {
        if ($cle && (self::existe($cle) || \App\Robot\Referentiel::existe($cle))) {
            return $cle;
        }
        $n = \Symfony\Component\String\u((string) $nom)->ascii()->lower()->toString();
        $regles = [
            'pois chiche' => 'poisChiche', 'pomme' => 'pommesDeTerre', 'ble' => 'ble', 'orge' => 'orge', 'tomate' => 'tomates',
            'pasteque' => 'pasteque', 'melon' => 'pasteque', 'olive' => 'olivier', 'olivier' => 'olivier', 'mais' => 'mais',
            'tournesol' => 'tournesol', 'betterave' => 'betterave', 'colza' => 'colza', 'jachere' => 'solNu',
            'piment' => 'piment', 'poivron' => 'piment', 'oignon' => 'oignon',
        ];
        foreach ($regles as $motif => $profil) {
            if (str_contains($n, $motif)) {
                return $profil;
            }
        }
        return null;
    }
}
