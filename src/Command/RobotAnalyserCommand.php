<?php

namespace App\Command;

use App\Entity\CodeTerrain;
use App\Robot\RobotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Recalcule le tableau du robot pour tous les terrains (cron chaque nuit, après la veille et la synchro satellite). */
#[AsCommand(name: 'app:robot:analyser', description: 'Calcule réussite, production, coût, prix et bénéfice de chaque culture pour chaque terrain')]
class RobotAnalyserCommand extends Command
{
    public function __construct(private RobotService $robot, private EntityManagerInterface $em, private \App\Repository\ZoneRepository $zones)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('terrain', null, InputOption::VALUE_REQUIRED, 'Code d\'un seul terrain')
            ->addOption('sans-ia', null, InputOption::VALUE_NONE, 'Ne pas appeler l\'IA (conseil et image)')
            ->addOption('zone', null, InputOption::VALUE_REQUIRED, 'Afficher les estimations d\'un terrain type de 1 ha dans un gouvernorat (ex. Nabeul), sans rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($nom = $input->getOption('zone')) {
            $z = $this->zones->gouvernoratParNom($nom);
            if (!$z) {
                $io->error("Gouvernorat inconnu : $nom");
                return Command::FAILURE;
            }
            $rows = array_map(fn ($l) => [
                $l['nom'], $l['numeroSaison'] . '/' . $l['saisonsAn'], $l['cultivation'][0] . ' → ' . $l['cultivation'][1], round($l['tauxReussite']) . ' %',
                number_format($l['productionEstimee'], 2, ',', ' ') . ' t' . (isset($l['details']['taille']) ? ' (' . $l['details']['taille'] . ' ' . $l['details']['unite'] . ')' : ''),
                $l['exportation'] ? 'oui' : 'non', number_format($l['cout'], 0, ',', ' '), number_format($l['prix'], 3, ',', ' '), number_format($l['benefice'], 0, ',', ' '), $l['recommande'] ? '★' : '',
            ], $this->robot->estimerZone($z));
            $io->title('Terrain type de 1 ha – ' . $z->getNom());
            $io->table(['Culture', 'Saison', 'Cultivation', 'Réussite', 'Production', 'Export', 'Coût DT', 'Prix DT/kg', 'Bénéfice DT', 'Reco'], $rows);
            return Command::SUCCESS;
        }
        $repo = $this->em->getRepository(CodeTerrain::class);
        $ids = $input->getOption('terrain') ? [(int) $input->getOption('terrain')] : array_map(fn ($t) => $t->getCodeTerrain(), $repo->findAll());

        $rows = [];
        foreach ($ids as $id) {
            $t = $repo->find($id);
            if (!$t) {
                continue;
            }
            try {
                $r = $this->robot->analyserTerrain($t, !$input->getOption('sans-ia'));
                $best = $r['estimations'][0] ?? null;
                $rows[] = [$id, $t->getNom(), $t->getGouvernorat()?->getNom() ?? '—', count($r['estimations']),
                    $best ? sprintf('%s (%d %%, %s DT)', $best->getCultureNom(), round($best->getTauxReussite()), number_format($best->getBenefice(), 0, ',', ' ')) : '—',
                    $r['erreurs'] ? mb_substr(implode(' | ', $r['erreurs']), 0, 80) : ''];
            } catch (\Throwable $e) {
                $rows[] = [$id, $t->getNom(), '—', 0, '—', $e->getMessage()];
            }
            $this->em->clear();
        }
        $io->table(['Terrain', 'Nom', 'Gouvernorat', 'Cultures', 'Meilleure culture', 'Remarques'], $rows);
        return Command::SUCCESS;
    }
}
