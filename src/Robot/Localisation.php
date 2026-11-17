<?php

namespace App\Robot;

use App\Entity\CodeTerrain;
use App\Entity\Zone;
use App\Meteo\MeteoService;
use App\Repository\ZoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Rattache un terrain à sa localité et à son gouvernorat :
 *  1. point = centre du contour GPS, sinon géocodage de la localité saisie (Open-Meteo, GeoNames) ;
 *  2. gouvernorat = celui donné par le géocodage, sinon le plus proche du point, sinon trouvé dans le texte ;
 *  3. localité = zone créée à la demande (nom, coordonnées, population GeoNames quand elle existe).
 */
class Localisation
{
    public function __construct(
        private MeteoService $meteo,
        private ZoneRepository $zones,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function localiser(CodeTerrain $t, bool $forcer = false): void
    {
        if (!$forcer && $t->getGouvernorat() && $t->getLocalite() && $t->getPoint()) {
            return;
        }
        $texte = trim((string) ($t->getLocalisation() ?: $t->getNom()));
        $geo = null;
        if ($texte !== '') {
            try {
                foreach ($this->meteo->geocoder($texte) as $r) {
                    $geo = $r;
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Géocodage impossible', ['lieu' => $texte, 'erreur' => $e->getMessage()]);
            }
        }

        if ($geo && !$t->getCentre()) {
            $t->setCoordonnees($geo['lat'], $geo['lon']);
        }
        $point = $t->getPoint();

        $gouv = ($geo && $geo['admin1']) ? $this->zones->gouvernoratParNom($geo['admin1']) : null;
        $gouv ??= $texte !== '' ? $this->zones->gouvernoratParNom($texte) : null;
        $gouv ??= $point ? $this->zones->plusProche('gouvernorat', $point[0], $point[1]) : null;
        if (!$point && $gouv) {
            $t->setCoordonnees($gouv->getLatitude(), $gouv->getLongitude());
            $point = $t->getPoint();
        }
        $t->setGouvernorat($gouv);

        $nom = $geo['nom'] ?? trim(preg_split('/[,;]/', $texte)[0] ?? '');
        if ($nom !== '' && $gouv) {
            $code = $gouv->getCode() . '-' . strtoupper((new AsciiSlugger())->slug($nom));
            $loc = $this->zones->findOneByCode('localite', $code);
            if (!$loc) {
                $loc = (new Zone())->setNiveau('localite')->setCode(mb_substr($code, 0, 80))->setNom(mb_substr($nom, 0, 120))->setParent($gouv);
                $this->em->persist($loc);
            }
            if ($geo) {
                $loc->setLatitude($geo['lat'])->setLongitude($geo['lon']);
                if ($geo['population']) {
                    $loc->setPopulation($geo['population'])->setSourcePopulation('GeoNames (Open-Meteo)');
                }
            } elseif ($loc->getLatitude() === null && $point) {
                $loc->setLatitude($point[0])->setLongitude($point[1]);
            }
            $t->setLocalite($loc);
        }
        $this->em->flush();
    }
}
