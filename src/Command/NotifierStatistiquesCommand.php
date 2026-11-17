<?php

namespace App\Command;

use App\Repository\NotificationRepository;
use App\Service\StatistiquesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * À lancer chaque jour (cron) :
 *   0 7 * * * cd /chemin/PeasantGo && php bin/console app:statistiques:notifier
 * Compare les statistiques avec la dernière publication et notifie TOUS les utilisateurs
 * (cloche du site + email) s'il y a des changements.
 */
#[AsCommand(name: 'app:statistiques:notifier', description: 'Envoie à tous les utilisateurs les nouvelles statistiques (site + email)')]
class NotifierStatistiquesCommand extends Command
{
    public function __construct(private StatistiquesService $stats, private NotificationRepository $notifications)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('seuil', null, InputOption::VALUE_REQUIRED, 'Variation minimale en % pour signaler un changement', '5')
            ->addOption('sans-email', null, InputOption::VALUE_NONE, 'Notification dans le site uniquement')
            ->addOption('forcer', null, InputOption::VALUE_NONE, 'Envoyer même sans changement')
            ->addOption('simulation', null, InputOption::VALUE_NONE, 'Afficher le message sans rien envoyer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $r = $this->stats->publier(
            (float) $input->getOption('seuil'),
            !$input->getOption('sans-email'),
            (bool) $input->getOption('forcer'),
            (bool) $input->getOption('simulation'),
        );

        if ($r['statut'] === 'aucun') {
            $io->note('Aucun changement significatif : rien envoyé.');
            return Command::SUCCESS;
        }

        $io->section($r['titre']);
        $io->writeln($r['message']);

        if ($r['statut'] === 'simulation') {
            $io->note('Simulation : rien n\'a été envoyé.');
            return Command::SUCCESS;
        }

        $e = $r['envoi'];
        $io->success(sprintf('%d notification(s) dans le site, %d email(s) envoyé(s)%s.', $e['notifications'], $e['emails'], $e['echecs'] ? sprintf(', %d échec(s)', $e['echecs']) : ''));

        $purge = $this->notifications->purger(90);
        if ($purge) {
            $io->writeln("$purge ancienne(s) notification(s) lue(s) supprimée(s).");
        }

        return Command::SUCCESS;
    }
}
