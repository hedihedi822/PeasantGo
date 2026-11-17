<?php

namespace App\Command;

use App\Repository\ZoneRepository;
use App\Robot\Classement;
use App\Robot\RobotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prépare chaque nuit les estimations des 24 gouvernorats (terrain type de 1 ha) :
 * l'accueil des visiteurs et le classement des cultures s'affichent ensuite instantanément.
 */
#[AsCommand(name: 'app:robot:classement', description: 'Calcule à l\'avance les estimations par gouvernorat (accueil visiteur, classement)')]
class RobotClassementCommand extends Command
{
    public function __construct(private RobotService $robot, private ZoneRepository $zones, private Classement $classement)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        foreach ($this->zones->parNiveau('gouvernorat') as $g) {
            $l = $this->robot->estimerZone($g);
            $best = $l[0] ?? null;
            $rows[] = [$g->getNom(), count($l), $best ? sprintf('%s (%s DT)', $best['nom'], number_format($best['benefice'], 0, ',', ' ')) : '—'];
        }
        $io->table(['Gouvernorat', 'Lignes', 'Plus rentable'], $rows);
        $pays = $this->classement->pays();
        $io->success(sprintf('Classement Tunisie : %s en tête.', $pays[0]['nom'] ?? '—'));
        return Command::SUCCESS;
    }
}
