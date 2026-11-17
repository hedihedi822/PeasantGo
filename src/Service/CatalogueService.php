<?php

namespace App\Service;

use App\Entity\Cout;
use App\Entity\DateCultivation;
use App\Entity\EtatProduction;
use App\Entity\Exportation;
use App\Entity\Prix;
use App\Entity\Production;
use App\Entity\ProduitsAgricole;
use App\Entity\Region;
use App\Entity\Saison;
use App\Entity\TauxReussite;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Assemble les tableaux de la page d'accueil à partir des tables statistiques.
 *
 * Une ligne = un produit (ProduitsAgricole, id_p) pour une saison (Saison, id_s).
 * Les tables Taux / Exportation / EtatProduction / Cout / Prix / Production / DateCultivation
 * sont toutes indexées par le couple (id_p, id_s).
 *
 * Chaque valeur est fournie à 3 niveaux : "national" (valeur de base), "etat" et "region",
 * pour que le filtre État / Région de la page puisse basculer de l'un à l'autre.
 */
class CatalogueService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param bool $complet false pour un visiteur : les statistiques (taux, prix, coût, export...)
     *                      ne sont pas envoyées au navigateur, seulement les infos de base.
     * @return array{vegetal: array, animal: array, regions: string[], complet: bool}
     */
    public function getTableaux(bool $complet = true): array
    {
        $produits = $this->em->getRepository(ProduitsAgricole::class)->findBy([], ['type' => 'ASC', 'sousType' => 'ASC']);
        if (!$produits) {
            return ['vegetal' => [], 'animal' => [], 'regions' => [], 'complet' => $complet];
        }

        $taux = $this->indexer(TauxReussite::class);
        $export = $this->indexer(Exportation::class);
        $etat = $this->indexer(EtatProduction::class);
        $cout = $this->indexer(Cout::class);
        $prix = $this->indexer(Prix::class);
        $prod = $this->indexer(Production::class);
        $dates = $this->indexer(DateCultivation::class);

        $saisonsParProduit = [];
        foreach ($this->em->getRepository(Saison::class)->findBy([], ['annee' => 'DESC', 'numeroSaisonParAn' => 'ASC']) as $s) {
            $saisonsParProduit[$s->getIdP()][] = $s;
        }

        $regionsParProduit = [];
        $regions = [];
        foreach ($this->em->getRepository(Region::class)->findAll() as $r) {
            $regionsParProduit[$r->getIdP()][$r->getRegion()] = true;
            $regions[$r->getRegion()] = true;
        }

        $vegetal = [];
        $animal = [];
        foreach ($produits as $p) {
            $saisons = $saisonsParProduit[$p->getIdP()] ?? [null];
            foreach ($saisons as $s) {
                $key = $s ? $p->getIdP() . '-' . $s->getIdS() : null;
                /** @var TauxReussite|null $t */
                $t = $key ? ($taux[$key] ?? null) : null;
                /** @var Exportation|null $e */
                $e = $key ? ($export[$key] ?? null) : null;
                /** @var EtatProduction|null $ep */
                $ep = $key ? ($etat[$key] ?? null) : null;
                /** @var Cout|null $c */
                $c = $key ? ($cout[$key] ?? null) : null;
                /** @var Prix|null $px */
                $px = $key ? ($prix[$key] ?? null) : null;
                /** @var Production|null $pr */
                $pr = $key ? ($prod[$key] ?? null) : null;
                /** @var DateCultivation|null $dc */
                $dc = $key ? ($dates[$key] ?? null) : null;

                $row = [
                    'idP' => $p->getIdP(),
                    'idS' => $s?->getIdS(),
                    'categorie' => $p->getCategorie(),
                    'type' => $p->getType(),
                    'sousType' => $p->getSousType(),
                    'methode' => $p->getMethodeDeCultivation(),
                    'annee' => $s?->getAnnee(),
                    'numeroSaison' => $s?->getNumeroSaisonParAn(),
                    'saisonsParAn' => $p->getNombreSaisonTotalParAn(),
                    'productivite' => $p->getProductiviteParHectare(),
                    'regions' => array_keys($regionsParProduit[$p->getIdP()] ?? []),
                    'export' => $e ? ['existe' => $e->isExportExist(), 'quantite' => $e->getQuantiteExporte(), 'societe' => $e->getSocieteExport()] : null,
                    'niveaux' => [
                        'national' => [
                            'taux' => $t?->getTaux(),
                            'exces' => $ep?->getPourcenatgeExce(),
                            'cout' => $c?->getCoutTotalParHectare(),
                            'prix' => $px?->getPrixMoyenneExportation(),
                            'confiance' => $p->getPourcentageConfianceEstimation(),
                            'production' => $pr?->getProduction(),
                            'debutCultivation' => $dc?->getDateDebutCultivation()?->format('Y-m-d'),
                            'debutExploitation' => $dc?->getDateDebutExploitation()?->format('Y-m-d'),
                        ],
                        'etat' => [
                            'taux' => $t?->getTauxEtat(),
                            'exces' => $ep?->getPourcenatgeExce(),
                            'cout' => $c?->getCoutTotalParHectare(),
                            'prix' => $px?->getPrixMoyenneDeGrosParEtat(),
                            'confiance' => $p->getPourcentageConfianceEstimationParEtat(),
                            'production' => $pr?->getProductionParEtat(),
                            'debutCultivation' => $dc?->getDateDebutCultivationDansEtat()?->format('Y-m-d'),
                            'debutExploitation' => $dc?->getDateDebutExploitationDansEtat()?->format('Y-m-d'),
                        ],
                        'region' => [
                            'taux' => $t?->getTauxRegion(),
                            'exces' => $ep?->getPourcenatgeExce(),
                            'cout' => $c?->getCoutTotalParHectare(),
                            'prix' => $px?->getPrixMoyenneDeGrosParRegion(),
                            'confiance' => $p->getPourcentageConfianceEstimationParRegion(),
                            'production' => $pr?->getProductionParRegion(),
                            'debutCultivation' => $dc?->getDateDebutCultivationDansRegion()?->format('Y-m-d'),
                            'debutExploitation' => $dc?->getDateDebutExploitationDansRegion()?->format('Y-m-d'),
                        ],
                    ],
                ];

                if (!$complet) {
                    $row['export'] = null;
                    foreach ($row['niveaux'] as $niv => $vals) {
                        $row['niveaux'][$niv] = array_intersect_key($vals, ['debutCultivation' => 1, 'debutExploitation' => 1]);
                    }
                }

                if ($this->estAnimal($p)) {
                    $animal[] = $row;
                } else {
                    $vegetal[] = $row;
                }
            }
        }

        $regions = array_keys($regions);
        sort($regions);

        return ['vegetal' => $vegetal, 'animal' => $animal, 'regions' => $regions, 'complet' => $complet];
    }

    private function estAnimal(ProduitsAgricole $p): bool
    {
        $cat = mb_strtolower($p->getCategorie());
        return str_starts_with($cat, 'anim');
    }

    /** Charge une table (id_p, id_s) et l'indexe par "idP-idS". */
    private function indexer(string $class): array
    {
        $out = [];
        foreach ($this->em->getRepository($class)->findAll() as $o) {
            $out[$o->getIdP() . '-' . $o->getIdS()] = $o;
        }
        return $out;
    }
}
