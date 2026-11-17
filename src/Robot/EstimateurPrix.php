<?php

namespace App\Robot;

use App\Entity\PrixMarche;
use App\Entity\Zone;
use App\Entity\CodeTerrain;
use App\Repository\EstimationTerrainRepository;
use App\Repository\ModeleCalibrationRepository;
use App\Repository\PrixMarcheRepository;

/**
 * Prix de vente estimé (DT/kg) d'une culture DANS LA LOCALITÉ du terrain, au moment de la récolte.
 *
 * 1. Offre / demande : taux de couverture r = production / consommation du gouvernorat
 *    (la localité seule est trop petite pour un marché).
 * 2. Si la zone MANQUE du produit (r < 1) : il viendra des zones en excès les plus proches.
 *    Prix ≈ coût de production là-bas + transport jusqu'ici + marge, majoré selon l'ampleur du manque.
 * 3. Si la zone est EN EXCÈS (r ≥ 1) : il faut écouler vers les zones en manque.
 *    Prix ≈ prix de référence − transport vers le débouché le plus proche, baissé selon l'ampleur de l'excès,
 *    sans descendre sous le coût de production local.
 * 4. Salaires : la part main d'œuvre suit le SMIG de l'année de récolte.
 * 5. Historique : prix réellement observés (veille, récoltes déclarées) à la même période les années
 *    précédentes, dans la zone et dans les autres zones (corrigés de la distance et du SMIG).
 * 6. Correction apprise : biais moyen entre nos estimations passées et les prix de vente déclarés.
 */
class EstimateurPrix
{
    private const MARGE = 0.15;
    private const PART_MAIN_OEUVRE = 0.35;
    private const RAYON_HISTO_KM = 150;
    /** part des agriculteurs qui suivent une recommandation (hypothèse prudente, corrigée par les déclarations réelles) */
    private const ADOPTION_RECOMMANDATION = 0.25;

    public function __construct(
        private BilanZones $bilans,
        private EstimateurCout $couts,
        private PrixMarcheRepository $prixMarche,
        private ModeleCalibrationRepository $calibrations,
        private EstimationTerrainRepository $estimations,
    ) {
    }

    /**
     * @param float $coutKgLocal coût de production local (DT/kg)
     * @return array{prix: float, details: array}
     */
    public function estimer(string $cle, ?Zone $localite, ?Zone $gouvernorat, \DateTimeInterface $dateRecolte, float $coutKgLocal, ?CodeTerrain $terrain = null): array
    {
        $ref = Referentiel::get($cle);
        $annee = (int) $dateRecolte->format('Y');
        $details = ['etapes' => []];

        // Prix de référence : dernier prix moyen connu (gouvernorat → pays) sinon référentiel
        $refPrix = (float) $ref['prix'];
        $srcRef = 'référentiel';
        foreach ([$gouvernorat, $gouvernorat?->getParent()] as $z) {
            if ($z && ($l = $this->bilans->ligne($z, $cle)) && $l['prixKg']) {
                $refPrix = (float) $l['prixKg'];
                $srcRef = $l['sourcePrix'];
                break;
            }
        }
        $details['prixReference'] = ['valeur' => round($refPrix, 3), 'source' => $srcRef];

        // 1-3. Offre / demande.
        //  - Le niveau des prix suit le bilan NATIONAL (les produits circulent dans tout le pays) :
        //    manque national → rareté, excès national → pression à la baisse.
        //  - La situation du gouvernorat ajoute le transport : s'il manque, le produit vient de la zone
        //    excédentaire la plus proche (coût rendu) ; s'il est en excès, il faut l'envoyer vers la zone
        //    en manque la plus proche (le transport est retiré du prix payé au producteur).
        //  - Anti-saturation : la production déjà recommandée aux voisins compte comme offre à venir.
        $modele = $refPrix;
        [$tkm] = $this->couts->prixIntrant('transport_tkm', $gouvernorat);
        $reco = $gouvernorat ? $this->estimations->offreRecommandee($cle, $gouvernorat, $terrain) : ['terrains' => 0, 'tonnes' => 0];
        $extra = $reco['tonnes'] * self::ADOPTION_RECOMMANDATION;
        if ($reco['terrains'] > 0) {
            $details['offreRecommandee'] = ['terrains' => $reco['terrains'], 'tonnes' => round($reco['tonnes']), 'prisEnCompte' => round($extra)];
            $details['etapes'][] = sprintf('%d terrain(s) voisin(s) ont reçu la même recommandation (%s t) : %d %% de cette production est comptée comme offre à venir.', $reco['terrains'], number_format($reco['tonnes'], 0, ',', ' '), self::ADOPTION_RECOMMANDATION * 100);
        }

        $pays = $gouvernorat?->getParent();
        $bn = $pays ? $this->bilans->ligne($pays, $cle) : null;
        $rNat = $bn && $bn['tauxCouverture'] !== null && $bn['consoTotale'] ? $bn['tauxCouverture'] / 100 + $extra / $bn['consoTotale'] : null;
        $details['couvertureNationale'] = $rNat !== null ? round($rNat * 100) : null;
        if ($rNat !== null && $rNat < 1) {
            $f = min(1.5, 1 + 0.5 * (1 - $rNat));
            $modele = $refPrix * $f;
            $details['etapes'][] = sprintf('La Tunisie ne couvre que %d %% de sa consommation : rareté × %.2f.', round($rNat * 100), $f);
        } elseif ($rNat !== null) {
            $f = 1 - 0.25 * min(1, $rNat - 1);
            $modele = $refPrix * $f;
            $details['etapes'][] = sprintf('La Tunisie produit %d %% de sa consommation : pression sur les prix × %.2f.', round($rNat * 100), $f);
        }

        if ($gouvernorat) {
            $b = $this->bilans->ligne($gouvernorat, $cle);
            $r = $b['tauxCouverture'] !== null && $b['consoTotale'] ? $b['tauxCouverture'] / 100 + $extra / $b['consoTotale'] : null;
            $details['couverture'] = $r !== null ? round($r * 100) : null;
            if ($r !== null && $r < 1) {
                foreach ($this->bilans->partenaires($gouvernorat, $cle, 1) as $f) {
                    if ($f['distanceKm'] === null) {
                        continue;
                    }
                    $transport = $f['distanceKm'] * $tkm / 1000; // DT/kg
                    $rendu = $modele * 0.8 * (1 + self::MARGE) + $transport; // prix bord champ là-bas + marge + transport
                    $details['etapes'][] = sprintf('Le gouvernorat couvre %d %% de ses besoins : le produit vient de %s (%d km, transport %.3f DT/kg), rendu ≈ %.3f DT/kg.', round($r * 100), $f['zone'], $f['distanceKm'], $transport, $rendu);
                    $modele = max($modele, $rendu);
                    break;
                }
            } elseif ($r !== null && $r > 1) {
                $debouches = $this->bilans->partenaires($gouvernorat, $cle, -1);
                $transport = isset($debouches[0]['distanceKm']) ? $debouches[0]['distanceKm'] * $tkm / 1000 : 0;
                $modele = max($coutKgLocal * 1.05, $modele - $transport * 0.5);
                $details['etapes'][] = $debouches
                    ? sprintf('Le gouvernorat produit %d %% de ses besoins : le surplus part vers %s (%d km), une partie du transport (%.3f DT/kg) est retirée du prix producteur.', round($r * 100), $debouches[0]['zone'], $debouches[0]['distanceKm'], $transport * 0.5)
                    : sprintf('Le gouvernorat produit %d %% de ses besoins.', round($r * 100));
            }
        }

        // 4. Salaires (SMIG de l'année de récolte vs année du prix de référence)
        $indice = Referentiel::smig($annee) / Referentiel::smig((int) date('Y'));
        $modele *= 1 + self::PART_MAIN_OEUVRE * ($indice - 1);
        $details['indiceSalaires'] = round($indice, 3);

        // 5. Historique observé
        $histo = $this->historique($cle, $localite ?? $gouvernorat, $dateRecolte, $tkm);
        $details['historique'] = $histo;
        $poidsHisto = $histo['n'] ? min(0.6, $histo['n'] / ($histo['n'] + 4)) : 0;
        $prix = $poidsHisto > 0 ? $poidsHisto * $histo['prix'] * $indice + (1 - $poidsHisto) * $modele : $modele;
        $details['modele'] = round($modele, 3);
        $details['poidsHistorique'] = round($poidsHisto, 2);

        // 6. Correction apprise
        $cal = $this->calibrations->pour($cle, $gouvernorat);
        if ($cal && $cal->getNbPrix() > 0) {
            $w = $cal->getNbPrix() / ($cal->getNbPrix() + 3);
            $f = 1 + $w * ($cal->getFacteurPrix() - 1);
            $prix *= $f;
            $details['correctionApprise'] = ['facteur' => round($f, 3), 'nbPrix' => $cal->getNbPrix()];
        }

        return ['prix' => round(max(0.05, $prix), 3), 'details' => $details];
    }

    /**
     * Prix observés à la même période (± 45 jours) les années passées :
     * pondérés par la proximité (rayon 150 km) et corrigés du transport depuis leur lieu.
     */
    private function historique(string $cle, ?Zone $ici, \DateTimeInterface $date, float $tkm): array
    {
        $rows = $this->prixMarche->historique($cle, (new \DateTimeImmutable())->modify('-6 years'));
        $doy = (int) $date->format('z');
        $somme = $poids = 0.0;
        $n = 0;
        $exemples = [];
        foreach ($rows as $p) {
            /** @var PrixMarche $p */
            $ecart = abs((int) $p->getDate()->format('z') - $doy);
            $ecart = min($ecart, 365 - $ecart);
            if ($ecart > 45) {
                continue;
            }
            $dist = ($ici && $p->getZone()) ? $ici->distanceKm($p->getZone()) : null;
            $w = 1 / (1 + $ecart / 15);
            $prix = $p->getPrixKg();
            if ($dist !== null) {
                if ($dist > self::RAYON_HISTO_KM) {
                    $w *= 0.3;
                    $prix += $dist * $tkm / 1000 * 0.5; // un autre marché : on corrige d'une partie du transport
                }
            } else {
                $w *= 0.5;
            }
            $smig = Referentiel::smig((int) $p->getDate()->format('Y'));
            $prix *= 1 + self::PART_MAIN_OEUVRE * (Referentiel::smig((int) date('Y')) / $smig - 1);
            $somme += $w * $prix;
            $poids += $w;
            ++$n;
            if (count($exemples) < 5) {
                $exemples[] = ['date' => $p->getDate()->format('Y-m-d'), 'lieu' => $p->getZone()?->getNom() ?? $p->getLieuTexte(), 'prix' => $p->getPrixKg(), 'source' => $p->getSource()];
            }
        }
        return ['n' => $n, 'prix' => $poids > 0 ? round($somme / $poids, 3) : null, 'exemples' => $exemples];
    }
}
