<?php

namespace App\Command;

use App\Entity\CodeTerrain;
use App\Satellite\SatelliteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * À lancer chaque nuit (cron), AVANT app:statistiques:notifier :
 *   30 5 * * * cd /chemin/PeasantGo && php bin/console app:satellite:sync
 * Récupère les nouvelles images de tous les terrains dessinés et met à jour la reconnaissance des cultures.
 */
#[AsCommand(name: 'app:satellite:sync', description: 'Récupère les images satellites de tous les terrains et reconnaît les cultures')]
class SatelliteSyncCommand extends Command
{
    public function __construct(private SatelliteService $satellite, private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('terrain', null, InputOption::VALUE_REQUIRED, 'Code d\'un seul terrain')
            ->addOption('jours', null, InputOption::VALUE_REQUIRED, 'Historique à récupérer pour un nouveau terrain (jours)', '150');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sources = $this->satellite->sources();
        $io->writeln('Sources : ' . implode(', ', array_map(fn ($n, $ok) => $n . ($ok ? ' ✔' : ' ✘ (non configurée)'), array_keys($sources), $sources)));
        if (!array_filter($sources)) {
            $io->error('Aucune source satellite disponible. Configurez AGRO_API_KEY et/ou CDSE_CLIENT_ID / CDSE_CLIENT_SECRET dans .env.local.');
            return Command::FAILURE;
        }

        $repo = $this->em->getRepository(CodeTerrain::class);
        $terrains = $input->getOption('terrain') ? array_filter([$repo->find((int) $input->getOption('terrain'))]) : $repo->findAll();
        $terrains = array_values(array_filter($terrains, fn (CodeTerrain $t) => $t->getGeometrie()));

        if (!$terrains) {
            $io->warning('Aucun terrain avec contour GPS. Les agriculteurs doivent dessiner leurs terrains dans « Profil agricole ».');
            return Command::SUCCESS;
        }

        $ids = array_map(fn (CodeTerrain $t) => $t->getCodeTerrain(), $terrains);
        $rows = [];
        foreach ($ids as $id) {
            $t = $repo->find($id); // rechargé après chaque clear() pour garder la mémoire basse
            try {
                $r = $this->satellite->synchroniser($t, (int) $input->getOption('jours'));
                $p = $r['prediction'];
                $rows[] = [
                    $t->getCodeTerrain(), $t->getNom(), $r['nouvelles'],
                    $p ? sprintf('%s (%d %%)', $p->getCultureNom(), round($p->getConfiance() * 100)) : 'pas assez d\'images',
                    $p?->getCultureDeclaree() ? ($p->isConforme() === null ? 'trop tôt' : ($p->isConforme() ? 'oui' : 'NON')) : '—',
                    $r['erreurs'] ? implode(' | ', array_map(fn ($k, $v) => "$k: " . mb_substr($v, 0, 60), array_keys($r['erreurs']), $r['erreurs'])) : '',
                ];
            } catch (\Throwable $e) {
                $rows[] = [$t->getCodeTerrain(), $t->getNom(), 0, '—', '—', $e->getMessage()];
            }
            $this->em->clear();
        }

        $io->table(['Terrain', 'Nom', 'Nouvelles images', 'Culture reconnue', 'Conforme', 'Erreurs'], $rows);
        return Command::SUCCESS;
    }
}
