<?php

namespace App\Satellite;

use App\Entity\CodeTerrain;
use App\Entity\ObservationSatellite;
use App\Entity\PredictionCulture;
use App\Repository\ObservationSatelliteRepository;
use App\Repository\PredictionCultureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Chaîne satellite complète pour un terrain :
 *   1. interroge TOUTES les sources disponibles (Agromonitoring, Copernicus, Earth Search, démo)
 *   2. enregistre les observations (une par source et par date)
 *   3. fusionne les courbes NDVI et lance la reconnaissance de culture (ClassificateurCultures)
 *   4. prévient l'agriculteur si la végétation chute brutalement
 */
class SatelliteService
{
    /** NDVI ignoré si les nuages dépassent ce % */
    private const NUAGES_MAX = 60;

    /** @var SatelliteProviderInterface[] */
    private array $providers;

    public function __construct(
        #[AutowireIterator(SatelliteProviderInterface::class)] iterable $providers,
        private EntityManagerInterface $em,
        private ObservationSatelliteRepository $observations,
        private PredictionCultureRepository $predictions,
        private ClassificateurCultures $classificateur,
        private \App\Robot\AlerteService $alertes,
        private LoggerInterface $logger,
    ) {
        $this->providers = iterator_to_array($providers, false);
    }

    /** @return array<string, bool> nom de la source => disponible */
    public function sources(): array
    {
        $out = [];
        foreach ($this->providers as $p) {
            $out[$p->getNom()] = $p->estDisponible();
        }
        return $out;
    }

    /**
     * @return array{nouvelles: int, erreurs: array<string, string>, sources: string[], prediction: ?PredictionCulture}
     */
    public function synchroniser(CodeTerrain $terrain, int $jours = 150): array
    {
        if (!$terrain->getGeometrie()) {
            throw new \InvalidArgumentException('Dessinez d\'abord le contour du terrain sur la carte.');
        }

        $fin = new \DateTimeImmutable('today');
        $nouvelles = 0;
        $erreurs = [];
        $utilisees = [];

        foreach ($this->providers as $p) {
            if (!$p->estDisponible()) {
                continue;
            }
            $derniere = $this->observations->findDerniereDate($terrain, $p->getNom());
            $debut = $derniere
                ? \DateTimeImmutable::createFromInterface($derniere)->modify('-10 days') // ré-interroge les derniers jours (images traitées en retard)
                : $fin->modify("-$jours days");

            try {
                $resultats = $p->fetch($terrain, $debut, $fin);
                $utilisees[] = $p->getNom();
            } catch (\Throwable $e) {
                $erreurs[$p->getNom()] = $e->getMessage();
                $this->logger->warning('Source satellite en erreur', ['source' => $p->getNom(), 'terrain' => $terrain->getCodeTerrain(), 'erreur' => $e->getMessage()]);
                continue;
            }

            foreach ($resultats as $r) {
                $date = \DateTime::createFromFormat('!Y-m-d', (string) ($r['date'] ?? ''));
                if (!$date) {
                    continue;
                }
                $obs = $this->observations->findOneBy(['terrain' => $terrain, 'source' => $p->getNom(), 'date' => $date]);
                if (!$obs) {
                    $obs = new ObservationSatellite($terrain, $p->getNom(), $date);
                    $this->em->persist($obs);
                    ++$nouvelles;
                }
                $obs->remplir($r);
            }
            $this->em->flush();
        }

        $terrain->setSatelliteSyncAt(new \DateTime());
        $this->em->flush();

        $prediction = $this->predire($terrain);
        $this->alerterSiChute($terrain);

        return ['nouvelles' => $nouvelles, 'erreurs' => $erreurs, 'sources' => $utilisees, 'prediction' => $prediction];
    }

    /**
     * Courbe NDVI fusionnée de la campagne en cours : jour de campagne => NDVI moyen des sources.
     * @return array<int, float>
     */
    public function serieNdvi(CodeTerrain $terrain, ?\DateTimeInterface $reference = null): array
    {
        $debut = ProfilsCultures::debutCampagne($reference ?? new \DateTime());
        $parJour = [];
        foreach ($this->observations->findPourTerrain($terrain, $debut) as $o) {
            if ($o->getNdvi() === null || ($o->getNuages() ?? 0) > self::NUAGES_MAX) {
                continue;
            }
            $parJour[ProfilsCultures::jour($o->getDate())][] = $o->getNdvi();
        }
        $serie = [];
        foreach ($parJour as $j => $vals) {
            $serie[$j] = array_sum($vals) / count($vals);
        }
        ksort($serie);
        return $serie;
    }

    public function predire(CodeTerrain $terrain): ?PredictionCulture
    {
        $serie = $this->serieNdvi($terrain);

        // Apprentissage : les autres terrains dont la culture principale est déclarée
        $exemples = [];
        foreach ($this->em->getRepository(CodeTerrain::class)->findAll() as $t) {
            if ($t === $terrain || !$t->getGeometrie() || !($c = $t->getCulturePrincipale())) {
                continue;
            }
            $cle = ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom());
            $s = $cle ? $this->serieNdvi($t) : [];
            if (count($s) >= 3) {
                $exemples[] = ['cle' => $cle, 'serie' => $s];
            }
        }

        $r = $this->classificateur->classer($serie, $exemples);
        if (!$r) {
            return null;
        }

        $declaree = ($c = $terrain->getCulturePrincipale()) ? ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom()) : null;
        $debutCampagne = ProfilsCultures::debutCampagne(new \DateTime());
        $jours = array_keys($serie);

        $p = (new PredictionCulture($terrain))
            ->setCultureCle($r['cle'])
            ->setCultureNom($r['nom'])
            ->setConfiance($r['confiance'])
            ->setProbabilites($r['probabilites'])
            ->setNbObservations(count($serie))
            ->setNbExemples($r['nbExemples'])
            ->setPeriodeDebut(\DateTime::createFromImmutable($debutCampagne->modify('+' . min($jours) . ' days')))
            ->setPeriodeFin(\DateTime::createFromImmutable($debutCampagne->modify('+' . max($jours) . ' days')))
            ->setCultureDeclaree($declaree)
            // on ne juge la déclaration que si le modèle est assez sûr (sinon : « trop tôt pour dire »)
            ->setConforme($declaree && ($r['cle'] === $declaree || $r['confiance'] >= 0.5) ? $declaree === $r['cle'] : null);

        $this->em->persist($p);
        $this->em->flush();
        return $p;
    }

    /** Alerte (email/SMS selon ses paramètres) à l'agriculteur si le NDVI baisse de plus de 0,12 en moins de 20 jours (hors récolte prévue). */
    private function alerterSiChute(CodeTerrain $terrain): void
    {
        $serie = $this->serieNdvi($terrain);
        if (count($serie) < 3) {
            return;
        }
        $jours = array_keys($serie);
        $dernierJour = end($jours);
        $dernier = $serie[$dernierJour];
        $recent = array_filter($serie, fn ($j) => $j >= $dernierJour - 20 && $j < $dernierJour, ARRAY_FILTER_USE_KEY);
        if (!$recent) {
            return;
        }
        $max = max($recent);
        $principale = $terrain->getCulturePrincipale();
        // une baisse pendant la période de récolte déclarée est normale
        if ($principale && $principale->getDateFin() && abs($principale->getDateFin()->getTimestamp() - time()) < 25 * 86400) {
            return;
        }
        if ($max - $dernier >= 0.12 && $max >= 0.3) {
            $titre = sprintf('Alerte satellite : la végétation baisse sur « %s »', $terrain->getNom());
            $this->alertes->envoyer(
                $terrain->getProprietaire(),
                $terrain,
                'satellite-' . $terrain->getCodeTerrain() . '-' . date('oW'), // au plus une alerte par semaine et par terrain
                $titre,
                sprintf("Le NDVI est passé de %.2f à %.2f en moins de 3 semaines. Vérifiez l'irrigation, les maladies ou un éventuel ravageur.", $max, $dernier)
            );
            $this->em->flush();
        }
    }
}
