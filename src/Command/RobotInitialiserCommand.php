<?php

namespace App\Command;

use App\Entity\SourceVeille;
use App\Entity\Zone;
use App\Entity\ZoneStat;
use App\Repository\ZoneRepository;
use App\Robot\Referentiel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prépare le robot : zones (monde, pays, 24 gouvernorats avec la population du recensement INS 2024),
 * sites de veille officiels, et chiffres de DÉMONSTRATION (production et consommation par pays)
 * marqués source « demo » – à remplacer par la veille ou l'admin.
 */
#[AsCommand(name: 'app:robot:initialiser', description: 'Crée les zones, les sources de veille et les chiffres de démonstration du robot')]
class RobotInitialiserCommand extends Command
{
    /** Recensement général de la population 2024 (INS, Tunisie) + chef-lieu */
    private const GOUVERNORATS = [
        ['Tunis', 1075306, 36.806, 10.181], ['Ariana', 668552, 36.862, 10.195], ['Ben Arous', 722828, 36.753, 10.231],
        ['Manouba', 418354, 36.808, 10.097], ['Nabeul', 863172, 36.456, 10.737], ['Zaghouan', 201065, 36.402, 10.143],
        ['Bizerte', 607388, 37.274, 9.873], ['Béja', 311417, 36.725, 9.182], ['Jendouba', 404352, 36.501, 8.780],
        ['Le Kef', 237686, 36.174, 8.705], ['Siliana', 216242, 36.085, 9.371], ['Sousse', 762281, 35.826, 10.636],
        ['Monastir', 599769, 35.778, 10.826], ['Mahdia', 449985, 35.504, 11.062], ['Sfax', 1047468, 34.740, 10.760],
        ['Kairouan', 600803, 35.678, 10.096], ['Kasserine', 492741, 35.168, 8.836], ['Sidi Bouzid', 489991, 35.038, 9.485],
        ['Gabès', 410847, 33.881, 10.098], ['Médenine', 537255, 33.354, 10.505], ['Tataouine', 162654, 32.929, 10.451],
        ['Gafsa', 388776, 34.425, 8.784], ['Tozeur', 120036, 33.919, 8.134], ['Kébili', 183201, 33.705, 8.969],
    ];

    /** Pays suivis : [code, nom, population (ordre de grandeur 2024), lat, lon] */
    private const PAYS = [
        ['TN', 'Tunisie', 11972169, 34.0, 9.5], ['DZ', 'Algérie', 46700000, 36.75, 3.06], ['LY', 'Libye', 7400000, 32.89, 13.19],
        ['MA', 'Maroc', 37000000, 34.02, -6.84], ['EG', 'Égypte', 114000000, 30.04, 31.24], ['IT', 'Italie', 59000000, 41.90, 12.50],
        ['FR', 'France', 68500000, 48.86, 2.35], ['ES', 'Espagne', 48600000, 40.42, -3.70],
    ];

    /** Production (t) et consommation (kg/hab/an) de DÉMONSTRATION par pays : [cle => [production, kg/hab]] */
    private const DEMO = [
        'TN' => ['ble' => [1100000, 180], 'orge' => [550000, 30], 'poisChiche' => [25000, 3], 'pommesDeTerre' => [450000, 35], 'tomates' => [1350000, 40],
            'piment' => [400000, 15], 'oignon' => [420000, 25], 'pasteque' => [500000, 30], 'mais' => [10000, 15], 'tournesol' => [3000, 2], 'colza' => [10000, 2], 'olivier' => [1300000, 20],
            'pouletChair' => [300000, 20], 'pondeuses' => [120000, 10], 'agneaux' => [110000, 12], 'vachesLait' => [1400000, 110], 'ruches' => [3000, 0.3]],
        'DZ' => ['ble' => [3000000, 230], 'tomates' => [1600000, 35], 'pommesDeTerre' => [4500000, 100], 'olivier' => [900000, 20], 'pasteque' => [1800000, 40], 'piment' => [700000, 15], 'oignon' => [1500000, 30],
            'pouletChair' => [500000, 12], 'pondeuses' => [400000, 9], 'agneaux' => [600000, 14], 'vachesLait' => [3500000, 140], 'ruches' => [6000, 0.15]],
        'LY' => ['ble' => [200000, 150], 'tomates' => [200000, 40], 'pommesDeTerre' => [350000, 50], 'olivier' => [150000, 15], 'pasteque' => [250000, 40], 'piment' => [60000, 10], 'oignon' => [200000, 30]],
        'MA' => ['ble' => [4500000, 200], 'tomates' => [1400000, 30], 'pommesDeTerre' => [1900000, 50], 'olivier' => [1900000, 20], 'pasteque' => [900000, 25], 'piment' => [250000, 6], 'oignon' => [1000000, 25],
            'pouletChair' => [800000, 22], 'pondeuses' => [350000, 9], 'agneaux' => [550000, 15], 'vachesLait' => [2500000, 70], 'ruches' => [5000, 0.15]],
        'EG' => ['ble' => [9500000, 180], 'tomates' => [6500000, 60], 'pommesDeTerre' => [6500000, 50], 'olivier' => [1000000, 8], 'pasteque' => [1500000, 15], 'piment' => [800000, 7], 'oignon' => [3500000, 30]],
        'IT' => ['ble' => [7000000, 140], 'tomates' => [6000000, 60], 'pommesDeTerre' => [1300000, 40], 'olivier' => [1900000, 25], 'pasteque' => [500000, 10], 'piment' => [200000, 4], 'oignon' => [450000, 8]],
        'FR' => ['ble' => [30000000, 120], 'tomates' => [700000, 15], 'pommesDeTerre' => [8000000, 50], 'olivier' => [30000, 3], 'pasteque' => [30000, 3], 'piment' => [30000, 2], 'oignon' => [450000, 8]],
        'ES' => ['ble' => [6000000, 120], 'tomates' => [3800000, 30], 'pommesDeTerre' => [2000000, 50], 'olivier' => [6000000, 30], 'pasteque' => [1200000, 20], 'piment' => [1400000, 10], 'oignon' => [1300000, 25]],
    ];

    private const SOURCES = [
        ['Institut National de la Statistique', 'https://www.ins.tn', 'officiel', 'statistique,prix,production,agricole,indice'],
        ['Ministère de l\'Agriculture', 'http://www.agriculture.tn', 'officiel', 'statistique,production,récolte,campagne,prix,céréales'],
        ['ONAGRI – Observatoire National de l\'Agriculture', 'http://www.onagri.nat.tn', 'officiel', 'prix,marché,production,bulletin,conjoncture,récolte'],
    ];

    public function __construct(private EntityManagerInterface $em, private ZoneRepository $zones)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sans-demo', null, InputOption::VALUE_NONE, 'Ne pas créer les chiffres de démonstration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $monde = $this->zones->monde() ?? (new Zone())->setNiveau('monde')->setCode('WORLD')->setNom('Monde (pays suivis)');
        $this->em->persist($monde);

        $pays = [];
        foreach (self::PAYS as [$code, $nom, $pop, $lat, $lon]) {
            $z = $this->zones->findOneByCode('pays', $code) ?? (new Zone())->setNiveau('pays')->setCode($code);
            $z->setNom($nom)->setParent($monde)->setLatitude($lat)->setLongitude($lon)->setPopulation($pop)
                ->setAnneePopulation(2024)->setSourcePopulation($code === 'TN' ? 'INS – RGPH 2024' : 'ordre de grandeur 2024 (à mettre à jour)');
            $this->em->persist($z);
            $pays[$code] = $z;
        }

        foreach (self::GOUVERNORATS as [$nom, $pop, $lat, $lon]) {
            $code = 'TN-' . strtoupper((new \Symfony\Component\String\Slugger\AsciiSlugger())->slug($nom)->toString());
            $z = $this->zones->findOneByCode('gouvernorat', $code) ?? (new Zone())->setNiveau('gouvernorat')->setCode($code);
            $z->setNom($nom)->setParent($pays['TN'])->setLatitude($lat)->setLongitude($lon)->setPopulation($pop)->setAnneePopulation(2024)->setSourcePopulation('INS – RGPH 2024');
            $this->em->persist($z);
        }
        $this->em->flush();
        $io->success(sprintf('%d pays et %d gouvernorats prêts.', count(self::PAYS), count(self::GOUVERNORATS)));

        $repoSrc = $this->em->getRepository(SourceVeille::class);
        foreach (self::SOURCES as [$nom, $url, $type, $mots]) {
            if (!$repoSrc->findOneBy(['url' => $url])) {
                $this->em->persist((new SourceVeille())->setNom($nom)->setUrl($url)->setType($type)->setMotsCles($mots));
            }
        }

        if (!$input->getOption('sans-demo')) {
            $repoStat = $this->em->getRepository(ZoneStat::class);
            $n = 0;
            foreach (self::DEMO as $code => $cultures) {
                foreach ($cultures as $cle => [$prod, $kg]) {
                    if (!Referentiel::existe($cle) || $repoStat->findOneBy(['zone' => $pays[$code], 'cultureCle' => $cle, 'annee' => 2024, 'source' => 'demo'])) {
                        continue;
                    }
                    $this->em->persist((new ZoneStat($pays[$code], $cle, 2024, 'demo'))->setProductionT($prod)->setConsommationKgHab($kg)
                        ->setPrixKg($code === 'TN' ? Referentiel::get($cle)['prix'] : null));
                    ++$n;
                }
            }
            $io->note("$n chiffres de démonstration (source « demo ») : remplacez-les par les chiffres officiels (admin → Statistiques par zone) ou laissez la veille les compléter.");
        }
        $this->em->flush();
        return Command::SUCCESS;
    }
}
