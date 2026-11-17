<?php

namespace App\Command;

use App\Robot\Apprentissage;
use App\Robot\VeilleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Lit les sites suivis (officiels, marchés, vendeurs d'intrants) puis réapprend des récoltes déclarées. */
#[AsCommand(name: 'app:robot:veille', description: 'Veille des sites configurés + apprentissage à partir des récoltes')]
class RobotVeilleCommand extends Command
{
    public function __construct(private VeilleService $veille, private Apprentissage $apprentissage)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $v = $this->veille->lancer();
        $io->writeln(sprintf('Veille : %d page(s) lue(s), %d nouvelle(s) – %d prix, %d prix d\'intrants, %d statistiques.', $v['pages'], $v['nouvelles'], $v['prix'], $v['intrants'], $v['statistiques']));
        foreach ($v['erreurs'] as $e) {
            $io->warning($e);
        }
        $a = $this->apprentissage->apprendre();
        $io->success(sprintf('Apprentissage : %d récolte(s) déclarée(s), %d calibration(s) mise(s) à jour.', $a['recoltes'], $a['calibrations']));
        return Command::SUCCESS;
    }
}
