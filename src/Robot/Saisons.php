<?php

namespace App\Robot;

use App\Entity\DateCultivation;
use App\Entity\ProduitsAgricole;
use App\Entity\Saison;
use App\Satellite\ProfilsCultures;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calendrier des cultures : prochaine saison de semis, périodes de cultivation et d'exploitation.
 * Les dates officielles du catalogue (DateCultivation, renseignées par l'équipe ou la veille) sont
 * prioritaires sur les dates par défaut du référentiel.
 */
class Saisons
{
    /** @var array<string, list<array{0:int,1:int}>>|null */
    private ?array $officielles = null;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Saison à prévoir à partir d'aujourd'hui (la prochaine dont le semis n'est pas dépassé de plus de 20 jours).
     * @return array{numeroSaison: int, saisonsAn: int, debutCultivation: \DateTime, finCultivation: \DateTime, debutExploitation: \DateTime, finExploitation: \DateTime, source: string}
     */
    public function prochaine(string $cle, \DateTimeInterface $aujourdhui): array
    {
        $ref = Referentiel::get($cle);
        $semis = $this->datesSemis($cle);
        $source = isset($this->officielles[$cle]) ? 'catalogue' : 'referentiel';
        $base = \DateTimeImmutable::createFromInterface($aujourdhui)->setTime(0, 0);

        $candidats = [];
        foreach ($semis as $i => [$m, $j]) {
            foreach ([(int) $base->format('Y') - 1, (int) $base->format('Y'), (int) $base->format('Y') + 1] as $y) {
                $d = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, min($j, 28)));
                if ($d >= $base->modify('-20 days')) {
                    $candidats[] = [$d, $i];
                }
            }
        }
        usort($candidats, fn ($a, $b) => $a[0] <=> $b[0]);
        [$debut, $i] = $candidats[0];

        $fin = $debut->modify('+' . $ref['cycle'] . ' days');
        if (isset($ref['exploitation'])) {
            // élevage : production pendant l'élevage (lait, œufs)
            [$d1, $d2] = $ref['exploitation'];
            return [
                'numeroSaison' => $i + 1,
                'saisonsAn' => count($semis),
                'debutCultivation' => \DateTime::createFromImmutable($debut),
                'finCultivation' => \DateTime::createFromImmutable($fin),
                'debutExploitation' => \DateTime::createFromImmutable($debut->modify("+$d1 days")),
                'finExploitation' => \DateTime::createFromImmutable($debut->modify("+$d2 days")),
                'source' => $source,
            ];
        }
        return [
            'numeroSaison' => $i + 1,
            'saisonsAn' => count($semis),
            'debutCultivation' => \DateTime::createFromImmutable($debut),
            'finCultivation' => \DateTime::createFromImmutable($fin),
            'debutExploitation' => \DateTime::createFromImmutable($fin->modify('-' . min(15, (int) ($ref['recolte'] / 2)) . ' days')),
            'finExploitation' => \DateTime::createFromImmutable($fin->modify('+' . $ref['recolte'] . ' days')),
            'source' => $source,
        ];
    }

    /** @return list<array{0:int,1:int}> [mois, jour] de chaque saison */
    public function datesSemis(string $cle): array
    {
        $this->chargerOfficielles();
        return $this->officielles[$cle] ?? Referentiel::get($cle)['semis'];
    }

    /** Dates de semis du catalogue (DateCultivation ↔ Saison ↔ ProduitsAgricole), par clé de culture. */
    private function chargerOfficielles(): void
    {
        if ($this->officielles !== null) {
            return;
        }
        $this->officielles = [];
        $produits = [];
        foreach ($this->em->getRepository(ProduitsAgricole::class)->findAll() as $p) {
            $cle = ProfilsCultures::depuisDeclaration(null, $p->getSousType()) ?? ProfilsCultures::depuisDeclaration(null, $p->getType());
            if ($cle && Referentiel::existe($cle)) {
                $produits[$p->getIdP()] = $cle;
            }
        }
        $saisons = [];
        foreach ($this->em->getRepository(Saison::class)->findAll() as $s) {
            $saisons[$s->getIdP() . '-' . $s->getIdS()] = $s->getNumeroSaisonParAn();
        }
        $parCle = [];
        foreach ($this->em->getRepository(DateCultivation::class)->findAll() as $d) {
            $cle = $produits[$d->getIdP()] ?? null;
            if (!$cle) {
                continue;
            }
            $n = $saisons[$d->getIdP() . '-' . $d->getIdS()] ?? 1;
            $date = $d->getDateDebutCultivation();
            $parCle[$cle][$n] = [(int) $date->format('n'), (int) $date->format('j')];
        }
        foreach ($parCle as $cle => $parN) {
            ksort($parN);
            $this->officielles[$cle] = array_values($parN);
        }
    }
}
