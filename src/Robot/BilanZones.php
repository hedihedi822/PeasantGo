<?php

namespace App\Robot;

use App\Entity\CodeTerrain;
use App\Entity\Cultivation;
use App\Entity\Zone;
use App\Entity\ZoneStat;
use App\Repository\ZoneRepository;
use App\Repository\ZoneStatRepository;
use App\Satellite\ProfilsCultures;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bilan offre / demande d'une culture par zone (monde, pays, gouvernorat, localité) :
 *   production, population, consommation par habitant et totale, solde,
 *   et pour un solde positif : combien exporter et vers quelles zones proches en manque ;
 *   pour un solde négatif : combien importer et depuis quelles zones proches en excès.
 *
 * Production d'une saison (fekra 3 : estimation à partir de la progression) :
 *   production = dernière production connue × (surface déclarée sur PeasantGo cette campagne jusqu'à aujourd'hui
 *                / surface déclarée à la même date la campagne précédente)
 *   tant qu'il y a assez de déclarations pour comparer.
 */
class BilanZones
{
    private const MIN_TERRAINS_TENDANCE = 3;

    /** @var array<string, array> */
    private array $cache = [];

    public function __construct(
        private EntityManagerInterface $em,
        private ZoneRepository $zones,
        private ZoneStatRepository $stats,
        private EstimateurCout $couts,
    ) {
    }

    /**
     * Tableau complet d'une culture : monde, pays, gouvernorat et localité du terrain.
     * @return list<array<string, mixed>>
     */
    public function tableau(string $cle, ?CodeTerrain $terrain = null, ?Zone $gouvernorat = null): array
    {
        $gouvernorat ??= $terrain?->getGouvernorat();
        $lignes = [];
        if ($m = $this->zones->monde()) {
            $lignes[] = $this->ligne($m, $cle);
        }
        if ($tn = $this->zones->tunisie()) {
            $lignes[] = $this->ligne($tn, $cle);
        }
        if ($gouvernorat) {
            $lignes[] = $this->ligne($gouvernorat, $cle);
        }
        if ($terrain?->getLocalite()) {
            $lignes[] = $this->ligne($terrain->getLocalite(), $cle);
        }
        return $lignes;
    }

    public function ligne(Zone $z, string $cle): array
    {
        $k = $z->getId() . '|' . $cle;
        if (isset($this->cache[$k])) {
            return $this->cache[$k];
        }

        [$production, $srcProd, $detailProd] = $this->production($z, $cle);
        [$kgHab, $srcConso] = $this->consommationKgHab($z, $cle);
        $pop = $this->population($z);
        $consoT = $pop !== null && $kgHab !== null ? $pop * $kgHab / 1000 : null;
        if ($z->getNiveau() === 'monde') {
            // somme des pays suivis, chacun avec sa propre consommation
            $consoT = 0.0;
            foreach ($this->zones->parNiveau('pays') as $p) {
                $consoT += $this->ligne($p, $cle)['consoTotale'] ?? 0;
            }
            $kgHab = $pop ? round($consoT * 1000 / $pop, 1) : null;
            $srcConso = 'pays suivis';
        }
        $solde = $production !== null && $consoT !== null ? $production - $consoT : null;
        [$coutHa, $srcCout] = $this->valeur($z, $cle, 'getCoutHa');
        if ($coutHa === null && $z->getNiveau() !== 'monde' && ($z->getNiveau() !== 'pays' || $z->getCode() === 'TN')) {
            // pas de chiffre publié : coût calculé par le robot avec les prix des intrants de la zone (culture en sec)
            $coutHa = round($this->couts->parHectare($cle, $z, (int) date('Y'), false, 0)['parHa']);
            $srcCout = 'modele';
        }
        [$prixKg, $srcPrix] = $this->valeur($z, $cle, 'getPrixKg');

        $ligne = [
            'zoneId' => $z->getId(),
            'niveau' => $z->getNiveau(),
            'niveauLabel' => Zone::NIVEAUX[$z->getNiveau()] ?? $z->getNiveau(),
            'zone' => $z->getNom(),
            'population' => $pop,
            'production' => $production !== null ? round($production) : null,
            'sourceProduction' => $srcProd,
            'detailProduction' => $detailProd,
            'consoKgHab' => $kgHab,
            'sourceConso' => $srcConso,
            'consoTotale' => $consoT !== null ? round($consoT) : null,
            'solde' => $solde !== null ? round($solde) : null,
            'tauxCouverture' => $production !== null && $consoT ? round(100 * $production / $consoT) : null,
            'coutHa' => $coutHa,
            'sourceCout' => $srcCout,
            'prixKg' => $prixKg,
            'sourcePrix' => $srcPrix,
            'export' => [],
            'import' => [],
        ];
        $this->cache[$k] = $ligne; // avant les partenaires (évite la récursion)

        if ($solde !== null && abs($solde) > 0.5) {
            $partenaires = $this->partenaires($z, $cle, $solde > 0 ? -1 : 1);
            $reste = abs($solde);
            $liste = [];
            foreach ($partenaires as $p) {
                if ($reste <= 0) {
                    break;
                }
                $q = min($reste, abs($p['solde']));
                $liste[] = ['zone' => $p['zone'], 'distanceKm' => $p['distanceKm'], 'quantite' => round($q)];
                $reste -= $q;
            }
            $ligne[$solde > 0 ? 'export' : 'import'] = $liste;
            if ($reste > 0.5) {
                $ligne[$solde > 0 ? 'resteExport' : 'resteImport'] = round($reste);
            }
        }

        return $this->cache[$k] = $ligne;
    }

    /** Solde national (Tunisie) : > 0 = surplus exportable. */
    public function soldePays(string $cle): ?array
    {
        $tn = $this->zones->tunisie();
        return $tn ? $this->ligne($tn, $cle) : null;
    }

    /**
     * Zones du même niveau (pour une localité : les gouvernorats) avec un solde du signe voulu, les plus proches d'abord.
     * @param int $signe 1 = cherche des zones en excès (pour importer), -1 = en manque (pour exporter)
     */
    public function partenaires(Zone $z, string $cle, int $signe): array
    {
        $niveau = $z->getNiveau() === 'localite' ? 'gouvernorat' : $z->getNiveau();
        if ($niveau === 'monde') {
            return [];
        }
        $out = [];
        foreach ($this->zones->parNiveau($niveau) as $o) {
            if ($o->getId() === $z->getId() || ($z->getNiveau() === 'localite' && $o->getId() === $z->getParent()?->getId())) {
                continue;
            }
            $l = $this->ligne($o, $cle);
            if ($l['solde'] === null || $l['solde'] * $signe <= 0) {
                continue;
            }
            $out[] = ['zone' => $o->getNom(), 'zoneId' => $o->getId(), 'solde' => $l['solde'], 'distanceKm' => $z->distanceKm($o) !== null ? round($z->distanceKm($o)) : null, 'coutHa' => $l['coutHa'], 'prixKg' => $l['prixKg']];
        }
        usort($out, fn ($a, $b) => ($a['distanceKm'] ?? INF) <=> ($b['distanceKm'] ?? INF));
        return $out;
    }

    // ------------------------------------------------------------------

    /** @return array{0: ?float, 1: string, 2: array} production (t), source, détail */
    private function production(Zone $z, string $cle): array
    {
        $niveau = $z->getNiveau();

        if ($niveau === 'monde') {
            // Somme des pays suivis (on n'invente pas de total mondial)
            $total = 0.0;
            $n = 0;
            foreach ($this->zones->parNiveau('pays') as $p) {
                [$v] = $this->production($p, $cle);
                if ($v !== null) {
                    $total += $v;
                    ++$n;
                }
            }
            return [$n ? $total : null, 'pays suivis', ['nbPays' => $n]];
        }

        $stat = $this->meilleure($z, $cle, fn (ZoneStat $s) => $s->getProductionT());
        $tendance = in_array($niveau, ['pays', 'gouvernorat'], true) ? $this->tendance($z, $cle) : null;
        $detail = ['tendance' => $tendance];

        if ($stat) {
            $v = $stat->getProductionT();
            if ($tendance && $tendance['facteur'] !== null) {
                $v *= $tendance['facteur'];
            }
            return [$v, $stat->getSource() . ($tendance && $tendance['facteur'] !== null ? '+tendance' : ''), $detail + ['annee' => $stat->getAnnee()]];
        }

        // Pas de chiffre direct : part de la zone parente
        $parent = $niveau === 'localite' ? ($z->getParent() ?? $this->zones->tunisie()) : ($niveau === 'gouvernorat' ? $this->zones->tunisie() : null);
        if (!$parent) {
            return [null, 'inconnue', $detail];
        }
        [$vp] = $this->production($parent, $cle);
        if ($vp === null) {
            return [null, 'inconnue', $detail];
        }
        $part = $this->part($z, $parent, $cle);
        if ($part === null) {
            return [null, 'inconnue', $detail + ['raison' => 'population de la localité inconnue']];
        }
        return [$vp * $part, 'modele', $detail + ['partDeLaZoneParente' => round($part, 4), 'zoneParente' => $parent->getNom()]];
    }

    /**
     * Part d'une zone dans la production de sa zone parente :
     * part des surfaces déclarées sur PeasantGo si assez de terrains, sinon part de population
     * (gouvernorats marqués comme producteurs dans le catalogue « Région » favorisés).
     */
    private function part(Zone $z, Zone $parent, string $cle): ?float
    {
        $sZone = $this->surfaceDeclaree($z, $cle, $this->debutCampagne(), new \DateTimeImmutable());
        $sParent = $this->surfaceDeclaree($parent, $cle, $this->debutCampagne(), new \DateTimeImmutable());
        if ($sParent['nbTerrains'] >= self::MIN_TERRAINS_TENDANCE * 2 && $sParent['surface'] > 0) {
            return $sZone['surface'] / $sParent['surface'];
        }
        if ($z->getNiveau() === 'gouvernorat') {
            // répartition indicative entre les gouvernorats producteurs connus
            $producteurs = array_map([ZoneRepository::class, 'normaliser'], Referentiel::ZONES_PRODUCTION[$cle] ?? []);
            $nb = count($producteurs);
            $autres = 24 - $nb;
            if ($nb === 0) {
                $pz = $this->population($z);
                $pp = $this->population($parent);
                return $pz && $pp ? $pz / $pp : 0;
            }
            $poidsAutres = 0.01; // les autres gouvernorats se partagent environ 0,01 × leur nombre du total
            $estProducteur = in_array(ZoneRepository::normaliser($z->getNom()), $producteurs, true);
            $total = $nb + $autres * $poidsAutres;
            return ($estProducteur ? 1 : $poidsAutres) / $total;
        }
        // localité : part de population dans le gouvernorat
        $pz = $this->population($z);
        $pp = $this->population($parent);
        if (!$pz || !$pp) {
            return null;
        }
        return min(1, $pz / $pp);
    }

    /**
     * Progression de la campagne : surface déclarée depuis le 1er septembre jusqu'à aujourd'hui,
     * comparée à la même période de la campagne précédente, et projection de la surface finale.
     */
    public function tendance(Zone $z, string $cle): ?array
    {
        $debut = $this->debutCampagne();
        $auj = new \DateTimeImmutable('today');
        $now = $this->surfaceDeclaree($z, $cle, $debut, $auj);
        $avant = $this->surfaceDeclaree($z, $cle, $debut->modify('-1 year'), $auj->modify('-1 year'));
        $avantTotal = $this->surfaceDeclaree($z, $cle, $debut->modify('-1 year'), $debut->modify('-1 day'));

        if ($now['nbTerrains'] === 0 && $avantTotal['nbTerrains'] === 0) {
            return null;
        }
        $facteur = null;
        if ($now['nbTerrains'] >= self::MIN_TERRAINS_TENDANCE && $avant['nbTerrains'] >= self::MIN_TERRAINS_TENDANCE && $avant['surface'] > 0) {
            $facteur = max(0.5, min(2.0, $now['surface'] / $avant['surface']));
        }
        // part de la surface de l'an dernier déjà déclarée à cette date → surface finale projetée
        $f = $avantTotal['surface'] > 0 ? $avant['surface'] / $avantTotal['surface'] : null;
        return [
            'surfaceDeclaree' => round($now['surface'], 2),
            'terrains' => $now['nbTerrains'],
            'surfaceMemeDateAnPasse' => round($avant['surface'], 2),
            'surfaceFinaleAnPasse' => round($avantTotal['surface'], 2),
            'surfaceFinaleProjetee' => $f && $f > 0.05 ? round($now['surface'] / $f, 2) : null,
            'facteur' => $facteur !== null ? round($facteur, 3) : null,
        ];
    }

    /** Surface déclarée (cultures non annulées) d'une culture dans une zone, sur une période de création. */
    private function surfaceDeclaree(Zone $z, string $cle, \DateTimeInterface $de, \DateTimeInterface $a): array
    {
        $k = 'surf|' . $z->getId() . '|' . $cle . '|' . $de->format('Ymd') . '|' . $a->format('Ymd');
        if (isset($this->cache[$k])) {
            return $this->cache[$k];
        }
        $qb = $this->em->createQueryBuilder()
            ->select('c', 't')->from(Cultivation::class, 'c')->join('c.terrain', 't')
            ->andWhere('c.actif = true')
            ->andWhere("c.origine != 'historique'") // la saison passée déclarée après coup ne fausse pas la progression
            ->andWhere("t.justificatifStatut != 'refuse'")
            ->andWhere('c.createdAt >= :de')->andWhere('c.createdAt <= :a')
            ->setParameter('de', $de)->setParameter('a', \DateTimeImmutable::createFromInterface($a)->setTime(23, 59, 59));
        if ($z->getNiveau() === 'localite') {
            $qb->andWhere('t.localite = :z')->setParameter('z', $z);
        } elseif ($z->getNiveau() === 'gouvernorat') {
            $qb->andWhere('t.gouvernorat = :z')->setParameter('z', $z);
        }
        $surface = 0.0;
        $terrains = [];
        foreach ($qb->getQuery()->getResult() as $c) {
            /** @var Cultivation $c */
            if (ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom()) === $cle) {
                $surface += $c->getTaille(); // hectares, ou nombre d'animaux pour l'élevage
                $terrains[$c->getTerrain()->getCodeTerrain()] = true;
            }
        }
        return $this->cache[$k] = ['surface' => $surface, 'nbTerrains' => count($terrains)];
    }

    /** @return array{0: ?float, 1: string} */
    private function consommationKgHab(Zone $z, string $cle): array
    {
        for ($x = $z; $x; $x = $x->getParent()) {
            $s = $this->meilleure($x, $cle, fn (ZoneStat $s) => $s->getConsommationKgHab());
            if ($s) {
                return [$s->getConsommationKgHab(), $s->getSource()];
            }
        }
        $c = Referentiel::existe($cle) ? Referentiel::get($cle)['conso'] : null;
        return [$c, 'referentiel'];
    }

    /** @return array{0: ?float, 1: string} valeur la plus fiable de la zone puis de ses parents */
    private function valeur(Zone $z, string $cle, string $getter): array
    {
        for ($x = $z; $x; $x = $x->getParent()) {
            $s = $this->meilleure($x, $cle, fn (ZoneStat $s) => $s->$getter());
            if ($s) {
                return [$s->$getter(), $s->getSource() . ($x !== $z ? ' (' . $x->getNom() . ')' : '')];
            }
        }
        return [null, 'inconnue'];
    }

    private function meilleure(Zone $z, string $cle, callable $valeur): ?ZoneStat
    {
        foreach ($this->stats->pour($z, $cle) as $s) {
            if ($valeur($s) !== null) {
                return $s;
            }
        }
        return null;
    }

    private function population(Zone $z): ?int
    {
        if ($z->getPopulation() !== null) {
            return $z->getPopulation();
        }
        if ($z->getNiveau() === 'monde') {
            $t = 0;
            foreach ($this->zones->parNiveau('pays') as $p) {
                $t += (int) $p->getPopulation();
            }
            return $t ?: null;
        }
        return null;
    }

    private function debutCampagne(): \DateTimeImmutable
    {
        return ProfilsCultures::debutCampagne(new \DateTime());
    }
}
