<?php

namespace App\Robot;

/**
 * Référentiel agronomique des cultures pour la Tunisie (valeurs INDICATIVES).
 *
 * Ces valeurs servent de point de départ au robot. Elles sont corrigées automatiquement par :
 *  - les récoltes déclarées par les agriculteurs (ModeleCalibration : rendement, coût, prix réels) ;
 *  - les chiffres officiels collectés par la veille (ZoneStat, PrixMarche, PrixIntrant) ;
 *  - les dates officielles du catalogue (DateCultivation) quand elles existent.
 *
 * semis : liste des dates de semis/plantation possibles dans l'année [mois, jour] (une par saison)
 * cycle : durée semis → fin de culture (jours) ; recolte : durée de la période de récolte (jours)
 * tBase : température de base (°C) pour les degrés-jours ; gdd : degrés-jours nécessaires
 * tGel : température minimale qui abîme la culture ; tChaleur : température maximale de stress
 * eau : besoin en eau sur le cycle (mm) ; rendement : [pluvial, irrigué] en t/ha
 * intrants : [clé PrixIntrant, quantité par ha] ; mo : jours de main d'œuvre/ha ; tracteur : heures/ha
 * conso : consommation par habitant en Tunisie (kg/an, ordre de grandeur) ; prix : prix de référence DT/kg
 */
final class Referentiel
{
    public const CULTURES = [
        'ble' => ['nom' => 'Blé', 'type' => 'Céréales', 'semis' => [[11, 1]], 'cycle' => 210, 'recolte' => 30, 'tBase' => 0, 'gdd' => 2000, 'tGel' => -4, 'tChaleur' => 34, 'eau' => 450, 'rendement' => [1.8, 4.5], 'irrigationHabituelle' => false,
            'intrants' => [['semence_cereale', 180], ['ammonitrate', 150], ['dap', 100], ['traitement', 1]], 'mo' => 4, 'tracteur' => 6, 'moisson' => true, 'conso' => 180, 'prix' => 1.30],
        'orge' => ['nom' => 'Orge', 'type' => 'Céréales', 'semis' => [[11, 1]], 'cycle' => 190, 'recolte' => 30, 'tBase' => 0, 'gdd' => 1700, 'tGel' => -4, 'tChaleur' => 34, 'eau' => 380, 'rendement' => [1.5, 3.5], 'irrigationHabituelle' => false,
            'intrants' => [['semence_cereale', 150], ['ammonitrate', 100], ['dap', 80], ['traitement', 1]], 'mo' => 3, 'tracteur' => 5, 'moisson' => true, 'conso' => 30, 'prix' => 1.00],
        'poisChiche' => ['nom' => 'Pois chiche', 'type' => 'Légumineuses', 'semis' => [[1, 15]], 'cycle' => 150, 'recolte' => 25, 'tBase' => 4, 'gdd' => 1300, 'tGel' => -3, 'tChaleur' => 35, 'eau' => 350, 'rendement' => [0.8, 1.8], 'irrigationHabituelle' => false,
            'intrants' => [['semence_legumineuse', 120], ['dap', 80], ['traitement', 2]], 'mo' => 8, 'tracteur' => 5, 'moisson' => true, 'conso' => 3, 'prix' => 4.00],
        'pommesDeTerre' => ['nom' => 'Pomme de terre', 'type' => 'Légumes', 'semis' => [[1, 15], [8, 25]], 'cycle' => 110, 'recolte' => 25, 'tBase' => 4, 'gdd' => 1500, 'tGel' => -1, 'tChaleur' => 30, 'eau' => 500, 'rendement' => [8, 28], 'irrigationHabituelle' => true,
            'intrants' => [['plant_pomme_terre', 2500], ['ammonitrate', 250], ['dap', 200], ['potasse', 250], ['traitement', 3]], 'mo' => 40, 'tracteur' => 15, 'moisson' => false, 'conso' => 35, 'prix' => 1.20],
        'tomates' => ['nom' => 'Tomate', 'type' => 'Légumes', 'semis' => [[3, 1], [7, 15]], 'cycle' => 130, 'recolte' => 45, 'tBase' => 10, 'gdd' => 1400, 'tGel' => 1, 'tChaleur' => 37, 'eau' => 600, 'rendement' => [15, 60], 'irrigationHabituelle' => true,
            'intrants' => [['plant_maraichage', 20000], ['ammonitrate', 250], ['dap', 200], ['potasse', 300], ['traitement', 4]], 'mo' => 80, 'tracteur' => 12, 'moisson' => false, 'conso' => 40, 'prix' => 0.90],
        'piment' => ['nom' => 'Piment', 'type' => 'Légumes', 'semis' => [[3, 15]], 'cycle' => 150, 'recolte' => 60, 'tBase' => 12, 'gdd' => 1600, 'tGel' => 2, 'tChaleur' => 37, 'eau' => 650, 'rendement' => [10, 30], 'irrigationHabituelle' => true,
            'intrants' => [['plant_maraichage', 30000], ['ammonitrate', 250], ['dap', 200], ['potasse', 250], ['traitement', 4]], 'mo' => 90, 'tracteur' => 12, 'moisson' => false, 'conso' => 15, 'prix' => 2.00],
        'oignon' => ['nom' => 'Oignon', 'type' => 'Légumes', 'semis' => [[11, 1]], 'cycle' => 180, 'recolte' => 30, 'tBase' => 4, 'gdd' => 1700, 'tGel' => -3, 'tChaleur' => 35, 'eau' => 500, 'rendement' => [15, 40], 'irrigationHabituelle' => true,
            'intrants' => [['semence_maraichage', 4], ['ammonitrate', 200], ['dap', 150], ['potasse', 150], ['traitement', 3]], 'mo' => 50, 'tracteur' => 10, 'moisson' => false, 'conso' => 25, 'prix' => 1.00],
        'pasteque' => ['nom' => 'Pastèque', 'type' => 'Fruits', 'semis' => [[3, 15]], 'cycle' => 110, 'recolte' => 35, 'tBase' => 12, 'gdd' => 1200, 'tGel' => 2, 'tChaleur' => 40, 'eau' => 450, 'rendement' => [15, 35], 'irrigationHabituelle' => true,
            'intrants' => [['semence_maraichage', 3], ['ammonitrate', 150], ['dap', 150], ['potasse', 150], ['traitement', 2]], 'mo' => 35, 'tracteur' => 8, 'moisson' => false, 'conso' => 30, 'prix' => 0.60],
        'mais' => ['nom' => 'Maïs', 'type' => 'Céréales', 'semis' => [[4, 15]], 'cycle' => 140, 'recolte' => 25, 'tBase' => 10, 'gdd' => 1500, 'tGel' => 0, 'tChaleur' => 38, 'eau' => 600, 'rendement' => [3, 9], 'irrigationHabituelle' => true,
            'intrants' => [['semence_mais', 25], ['ammonitrate', 250], ['dap', 150], ['traitement', 2]], 'mo' => 6, 'tracteur' => 8, 'moisson' => true, 'conso' => 15, 'prix' => 1.00],
        'tournesol' => ['nom' => 'Tournesol', 'type' => 'Oléagineux', 'semis' => [[4, 1]], 'cycle' => 120, 'recolte' => 20, 'tBase' => 6, 'gdd' => 1500, 'tGel' => 0, 'tChaleur' => 38, 'eau' => 450, 'rendement' => [1.0, 2.5], 'irrigationHabituelle' => false,
            'intrants' => [['semence_oleagineux', 5], ['ammonitrate', 100], ['dap', 100], ['traitement', 1]], 'mo' => 5, 'tracteur' => 6, 'moisson' => true, 'conso' => 2, 'prix' => 2.00],
        'colza' => ['nom' => 'Colza', 'type' => 'Oléagineux', 'semis' => [[10, 15]], 'cycle' => 240, 'recolte' => 20, 'tBase' => 3, 'gdd' => 1800, 'tGel' => -6, 'tChaleur' => 33, 'eau' => 450, 'rendement' => [1.2, 2.8], 'irrigationHabituelle' => false,
            'intrants' => [['semence_oleagineux', 5], ['ammonitrate', 150], ['dap', 100], ['traitement', 2]], 'mo' => 4, 'tracteur' => 6, 'moisson' => true, 'conso' => 2, 'prix' => 1.80],
        'olivier' => ['nom' => 'Olivier (olives)', 'type' => 'Olives', 'semis' => [[11, 15]], 'cycle' => 365, 'recolte' => 75, 'tBase' => 7, 'gdd' => 2500, 'tGel' => -7, 'tChaleur' => 42, 'eau' => 400, 'rendement' => [1.5, 6], 'irrigationHabituelle' => false, 'perenne' => true,
            'intrants' => [['traitement', 1], ['ammonitrate', 80]], 'mo' => 15, 'tracteur' => 5, 'moisson' => false, 'conso' => 20, 'prix' => 2.50],
    ];

    /**
     * Élevage (valeurs INDICATIVES, par animal ou par ruche). Mêmes clés que les cultures pour réutiliser
     * les saisons, les coûts, les prix et les bilans de zone :
     * semis = dates de mise en place des bandes ; cycle = durée d'élevage (jours) ; recolte = durée de vente ;
     * exploitation = [début, fin] de la période de production en jours après la mise en place (sinon fin de cycle) ;
     * production = t de produit par animal (poids vif, lait, œufs, miel) ; affichage = [unité, kg par unité] ;
     * intrants = [clé PrixIntrant, quantité par animal] ; mo = jours de travail par animal ;
     * tChaleur / tFroid = stress thermique ; perteChaleur = perte de production par jour de stress ;
     * pluieBesoin = pluie utile (mm) sur les mois pluieMois avant la mise en place (pâturage, nectar) ;
     * unite = ce que l'agriculteur compte ; tailleDefaut = effectif utilisé quand rien n'est déclaré.
     */
    public const ANIMAUX = [
        'pouletChair' => ['nom' => 'Poulet de chair', 'type' => 'Élevage – volailles', 'famille' => 'animal', 'unite' => 'poulets', 'tailleDefaut' => 1000,
            'semis' => [[1, 1], [3, 1], [5, 1], [7, 1], [9, 1], [11, 1]], 'cycle' => 45, 'recolte' => 7,
            'production' => 0.0022, 'mortalite' => 0.05, 'affichage' => ['kg vif', 1], 'tChaleur' => 33, 'tFroid' => 3, 'perteChaleur' => 0.012, 'perteFroid' => 0.004,
            'intrants' => [['poussin', 1], ['aliment_chair', 4.0], ['veto_dt', 0.35], ['divers_dt', 0.45]], 'mo' => 0.02, 'tracteur' => 0, 'conso' => 20, 'prix' => 5.8],
        'pondeuses' => ['nom' => 'Poules pondeuses (œufs)', 'type' => 'Élevage – volailles', 'famille' => 'animal', 'unite' => 'poules', 'tailleDefaut' => 1000,
            'semis' => [[9, 1]], 'cycle' => 365, 'recolte' => 0, 'exploitation' => [10, 365],
            'production' => 0.0175, 'mortalite' => 0.06, 'affichage' => ['œufs', 0.06], 'tChaleur' => 32, 'tFroid' => 3, 'perteChaleur' => 0.002, 'perteFroid' => 0.001,
            'intrants' => [['poulette', 1], ['aliment_ponte', 42], ['veto_dt', 1.2], ['divers_dt', 1.5]], 'mo' => 0.08, 'tracteur' => 0, 'conso' => 10, 'prix' => 5.5],
        'agneaux' => ['nom' => 'Agneaux (engraissement)', 'type' => 'Élevage – ovins', 'famille' => 'animal', 'unite' => 'agneaux', 'tailleDefaut' => 50,
            'semis' => [[1, 15], [7, 1]], 'cycle' => 120, 'recolte' => 15, 'aid' => true,
            'production' => 0.040, 'mortalite' => 0.03, 'affichage' => ['kg vif', 1], 'tChaleur' => 36, 'perteChaleur' => 0.004, 'pluieBesoin' => 150, 'pluieMois' => 4,
            'intrants' => [['agneau_maigre', 20], ['aliment_ruminant', 150], ['foin', 60], ['veto_dt', 8]], 'mo' => 0.5, 'tracteur' => 0, 'conso' => 12, 'prix' => 16.5],
        'vachesLait' => ['nom' => 'Vaches laitières (lait)', 'type' => 'Élevage – bovins', 'famille' => 'animal', 'unite' => 'vaches', 'tailleDefaut' => 10,
            'semis' => [[10, 1]], 'cycle' => 305, 'recolte' => 0, 'exploitation' => [0, 305],
            'production' => 4.6, 'mortalite' => 0.02, 'affichage' => ['litres', 1.03], 'tChaleur' => 30, 'perteChaleur' => 0.0025,
            'intrants' => [['aliment_ruminant', 2000], ['foin', 3000], ['veto_dt', 150], ['divers_dt', 120]], 'mo' => 30, 'tracteur' => 0, 'conso' => 110, 'prix' => 1.31],
        'ruches' => ['nom' => 'Apiculture (miel)', 'type' => 'Élevage – abeilles', 'famille' => 'animal', 'unite' => 'ruches', 'tailleDefaut' => 20,
            'semis' => [[3, 1]], 'cycle' => 100, 'recolte' => 30,
            'production' => 0.008, 'mortalite' => 0.10, 'affichage' => ['kg', 1], 'tChaleur' => 38, 'perteChaleur' => 0.02, 'pluieBesoin' => 250, 'pluieMois' => 5,
            'intrants' => [['sucre', 8], ['veto_dt', 12], ['divers_dt', 10]], 'mo' => 1, 'tracteur' => 0, 'conso' => 0.3, 'prix' => 40.0],
    ];

    /** Aïd al-Adha (dates approximatives, à ±1 jour selon l'observation de la lune) : la demande d'agneaux y culmine. */
    public const AID_ADHA = [2025 => '2025-06-06', 2026 => '2026-05-27', 2027 => '2027-05-16', 2028 => '2028-05-05', 2029 => '2029-04-24', 2030 => '2030-04-13'];

    /**
     * Principaux gouvernorats producteurs (répartition indicative utilisée tant qu'il n'y a ni chiffre
     * officiel par gouvernorat ni assez de déclarations PeasantGo).
     */
    public const ZONES_PRODUCTION = [
        'ble' => ['Béja', 'Jendouba', 'Bizerte', 'Siliana', 'Le Kef', 'Zaghouan', 'Manouba'],
        'orge' => ['Kairouan', 'Siliana', 'Kasserine', 'Le Kef', 'Sidi Bouzid', 'Béja'],
        'poisChiche' => ['Béja', 'Bizerte', 'Jendouba', 'Le Kef'],
        'pommesDeTerre' => ['Nabeul', 'Jendouba', 'Bizerte', 'Béja', 'Kairouan', 'Manouba'],
        'tomates' => ['Kairouan', 'Sidi Bouzid', 'Nabeul', 'Ben Arous', 'Monastir'],
        'piment' => ['Kairouan', 'Sidi Bouzid', 'Nabeul', 'Ben Arous'],
        'oignon' => ['Nabeul', 'Kairouan', 'Sidi Bouzid'],
        'pasteque' => ['Kairouan', 'Sidi Bouzid', 'Kasserine', 'Nabeul', 'Mahdia'],
        'mais' => ['Jendouba', 'Béja'],
        'tournesol' => ['Béja', 'Jendouba', 'Bizerte'],
        'colza' => ['Béja', 'Bizerte', 'Jendouba'],
        'olivier' => ['Sfax', 'Sidi Bouzid', 'Kairouan', 'Mahdia', 'Sousse', 'Monastir', 'Médenine', 'Gafsa', 'Kasserine'],
        'pouletChair' => ['Sfax', 'Nabeul', 'Sousse', 'Monastir', 'Ben Arous', 'Zaghouan'],
        'pondeuses' => ['Sfax', 'Nabeul', 'Sousse', 'Monastir', 'Ben Arous', 'Kairouan'],
        'agneaux' => ['Kasserine', 'Sidi Bouzid', 'Kairouan', 'Siliana', 'Le Kef', 'Gafsa', 'Médenine', 'Tataouine'],
        'vachesLait' => ['Béja', 'Jendouba', 'Bizerte', 'Nabeul', 'Sfax', 'Mahdia', 'Manouba'],
        'ruches' => ['Nabeul', 'Bizerte', 'Béja', 'Jendouba', 'Zaghouan', 'Siliana'],
    ];

    /** SMAG journalier (DT) – décret n° 66 de 2026 */
    public const SMAG = [2025 => 20.320, 2026 => 21.336, 2027 => 22.400, 2028 => 23.520];

    /** SMIG mensuel 48 h (DT) – décret n° 67 de 2026 */
    public const SMIG = [2025 => 528.320, 2026 => 554.736, 2027 => 582.400, 2028 => 611.520];

    public static function existe(string $cle): bool
    {
        return isset(self::CULTURES[$cle]) || isset(self::ANIMAUX[$cle]);
    }

    public static function get(string $cle): array
    {
        return self::CULTURES[$cle] ?? self::ANIMAUX[$cle];
    }

    public static function nom(string $cle): string
    {
        return self::CULTURES[$cle]['nom'] ?? self::ANIMAUX[$cle]['nom'] ?? $cle;
    }

    public static function estAnimal(string $cle): bool
    {
        return isset(self::ANIMAUX[$cle]);
    }

    /** Cultures puis élevages. @return array<string, array> */
    public static function tout(): array
    {
        return self::CULTURES + self::ANIMAUX;
    }

    /** @return array<string, string> clé => nom, groupés végétal puis animal */
    public static function noms(): array
    {
        return array_map(fn ($c) => $c['nom'], self::tout());
    }

    /** Date de l'Aïd al-Adha la plus proche après une date (null si inconnue). */
    public static function aidApres(\DateTimeInterface $d): ?\DateTimeImmutable
    {
        foreach (self::AID_ADHA as $date) {
            $a = new \DateTimeImmutable($date);
            if ($a >= $d) {
                return $a;
            }
        }
        return null;
    }

    /** Valeur la plus proche pour une année (au-delà de la table : dernière valeur + 5 %/an). */
    public static function salaire(array $table, int $annee): float
    {
        if (isset($table[$annee])) {
            return $table[$annee];
        }
        $annees = array_keys($table);
        if ($annee < min($annees)) {
            return $table[min($annees)] / (1.05 ** (min($annees) - $annee));
        }
        return $table[max($annees)] * (1.05 ** ($annee - max($annees)));
    }

    public static function smag(int $annee): float
    {
        return self::salaire(self::SMAG, $annee);
    }

    public static function smig(int $annee): float
    {
        return self::salaire(self::SMIG, $annee);
    }
}
