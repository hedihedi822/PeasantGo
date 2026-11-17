<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\Consommation;
use App\Entity\Cout;
use App\Entity\DateCultivation;
use App\Entity\EtatProduction;
use App\Entity\Exportation;
use App\Entity\Formation;
use App\Entity\Outils;
use App\Entity\Prix;
use App\Entity\Production;
use App\Entity\ProduitsAgricole;
use App\Entity\Region;
use App\Entity\Saison;
use App\Entity\TauxReussite;
use App\Entity\TerreDesignee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Remplit la base avec des données de démonstration :
 *   php bin/console app:seed
 * Ne touche pas aux tables déjà remplies (sauf avec --force).
 */
#[AsCommand(name: 'app:seed', description: 'Insère un compte admin et des données de démonstration (produits, saisons, formations...)')]
class SeedCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email du compte admin', 'admin@peasantgo.tn')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Mot de passe du compte admin (généré aléatoirement si absent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // ---- Compte admin ----
        // Identifiant de connexion : « PeasantGo.AI » (nom du compte) ou l'email
        $email = (string) $input->getOption('admin-email');
        $admin = $this->em->getRepository(Admin::class)->findOneBy(['email' => $email]);
        if (!$admin) {
            $admin = (new Admin())->setEmail($email)->setTelephone('+21670000000');
            $this->em->persist($admin);
        }
        $admin->setNom('PeasantGo.AI')->setPrenom('');
        $adminPassword = (string) ($input->getOption('admin-password') ?: 'Pg!'.bin2hex(random_bytes(6)).'aZ1');
        $admin->setMotDePasse($this->hasher->hashPassword($admin, $adminPassword));
        $io->success(sprintf('Compte admin : identifiant « PeasantGo.AI » (ou %s), mot de passe « %s » (noté une seule fois : changez-le)', $email, $adminPassword));

        // ---- Catalogue ----
        if ($this->em->getRepository(ProduitsAgricole::class)->count([]) === 0) {
            $this->seedCatalogue();
            $io->success('Catalogue de démonstration inséré (produits, saisons, statistiques, régions).');
        } else {
            $io->note('Catalogue déjà rempli : ignoré.');
        }

        // ---- Formations ----
        if ($this->em->getRepository(Formation::class)->count([]) === 0) {
            $this->seedFormations();
            $io->success('Formations vidéo insérées.');
        }

        // ---- Outils ----
        if ($this->em->getRepository(Outils::class)->count([]) === 0) {
            foreach ([['Tracteur', 'Indispensable', 85000], ['Motoculteur', 'Utile', 4500], ['Pulvérisateur', 'Indispensable', 900], ['Goutte-à-goutte (1 ha)', 'Recommandé', 3200]] as [$nom, $imp, $prix]) {
                $this->em->persist((new Outils())->setNomOutils($nom)->setImportanceOutils($imp)->setPrixOutils($prix));
            }
        }

        $this->em->flush();

        // Zones, sources de veille et chiffres de démonstration du robot
        $this->getApplication()?->find('app:robot:initialiser')->run(new \Symfony\Component\Console\Input\ArrayInput([]), $output);

        $io->writeln('Terminé.');

        return Command::SUCCESS;
    }

    private function seedCatalogue(): void
    {
        // [idP, catégorie, type, sous-type, méthode, saisons/an, productivité t/ha, confiance (nat, état, région), excès?]
        $produits = [
            [1, 'Végétale', 'Céréales', 'Blé dur', 'Traditionnel', 1, 3, [92, 88, 80], false],
            [2, 'Végétale', 'Céréales', 'Orge', 'Traditionnel', 1, 2, [90, 85, 78], false],
            [3, 'Végétale', 'Légumes', 'Tomate', 'Sous serre', 2, 60, [88, 82, 75], true],
            [4, 'Végétale', 'Légumes', 'Pomme de terre', 'Irrigué', 2, 28, [86, 80, 72], true],
            [5, 'Végétale', 'Fruits', 'Pastèque', 'Plein champ', 1, 35, [84, 79, 70], false],
            [6, 'Végétale', 'Olives', 'Olive à huile (Chemlali)', 'Bio', 1, 2, [95, 91, 86], false],
            [7, 'Végétale', 'Légumineuses', 'Pois chiche', 'Traditionnel', 1, 1, [80, 74, 68], false],
            [8, 'Animale', 'Bovin', 'Holstein', 'Élevage laitier', 1, 7, [91, 87, 81], false],
            [9, 'Animale', 'Ovin', 'Barbarine', 'Pâturage', 1, 1, [85, 80, 73], false],
            [10, 'Animale', 'Volaille', 'Poulet de chair', 'Élevage intensif', 6, 2, [89, 84, 77], true],
        ];

        // [idP => [taux nat/état/région, %excès, coût DT/ha, prix export/gros état/gros région, production t (nat/état/région), début cultivation (mm-dd), début exploitation (mm-dd), export?, qté export t, société]]
        $stats = [
            1 => [[78, 74, 70], 0, 1900, [1.15, 0.95, 0.90], [1200000, 1200000, 150000], '11-15', '06-01', false, 0, ''],
            2 => [[75, 70, 66], 0, 1500, [0.85, 0.70, 0.68], [600000, 600000, 70000], '11-01', '05-20', false, 0, ''],
            3 => [[85, 81, 79], 18, 9500, [1.80, 1.10, 1.00], [1300000, 1300000, 210000], '02-15', '05-15', true, 45000, 'Tunisian Fresh Export'],
            4 => [[82, 78, 75], 12, 7200, [1.20, 0.95, 0.90], [450000, 450000, 90000], '01-20', '05-01', true, 8000, 'AgroMed SARL'],
            5 => [[80, 76, 72], 8, 4800, [0.90, 0.60, 0.55], [500000, 500000, 120000], '03-01', '06-15', true, 15000, 'Sahel Fruits'],
            6 => [[90, 88, 86], 5, 2600, [12.50, 10.80, 10.50], [340000, 340000, 95000], '11-01', '11-15', true, 210000, 'CHO Group'],
            7 => [[70, 66, 60], 0, 1700, [3.20, 2.80, 2.70], [25000, 25000, 4000], '12-15', '06-10', false, 0, ''],
            8 => [[88, 84, 80], 6, 5200, [1.35, 1.20, 1.15], [1400000, 1400000, 230000], '01-01', '01-01', false, 0, ''],
            9 => [[83, 79, 75], 0, 1800, [24.00, 21.00, 20.50], [60000, 60000, 12000], '09-01', '03-15', false, 0, ''],
            10 => [[86, 83, 80], 22, 3900, [6.20, 5.40, 5.20], [140000, 140000, 26000], '01-01', '02-15', true, 3000, 'Poulina'],
        ];

        $regions = [
            ['Béja', 9000, [1, 2, 7, 8]], ['Jendouba', 8100, [1, 2, 4, 8]], ['Kairouan', 3100, [3, 5, 9]],
            ['Nabeul', 8000, [3, 4, 5]], ['Sfax', 3000, [6, 9, 10]], ['Sidi Bouzid', 9100, [3, 5, 6]],
            ['Sousse', 4000, [6, 10]], ['Bizerte', 7000, [1, 4, 8]],
        ];

        $annee = (int) date('Y');
        $idS = 0;
        foreach ($produits as [$idP, $cat, $type, $sous, $methode, $nbSaisons, $prodHa, $conf, $exces]) {
            $this->em->persist((new ProduitsAgricole())
                ->setIdP($idP)->setCategorie($cat)->setType($type)->setSousType($sous)
                ->setMethodeDeCultivation($methode)->setNombreSaisonTotalParAn($nbSaisons)
                ->setProductiviteParHectare($prodHa)
                ->setPourcentageConfianceEstimation($conf[0])
                ->setPourcentageConfianceEstimationParEtat($conf[1])
                ->setPourcentageConfianceEstimationParRegion($conf[2])
                ->setExceProduction($exces));

            [$taux, $pExces, $cout, $prix, $prod, $debC, $debE, $export, $qte, $soc] = $stats[$idP];

            // Une saison par an (2 pour les produits à 2 saisons, plafonné à 2 pour la démo)
            for ($n = 1; $n <= min($nbSaisons, 2); ++$n) {
                ++$idS;
                $this->em->persist((new Saison())->setIdS($idS)->setIdP($idP)->setAnnee($annee)->setNumeroSaisonParAn($n));

                $decal = $n === 2 ? '+5 months' : '+0 days';
                $dC = (new \DateTime("$annee-$debC"))->modify($decal);
                $dE = (new \DateTime("$annee-$debE"))->modify($decal);
                if ($dE < $dC) {
                    $dE->modify('+1 year');
                }

                $this->em->persist((new TauxReussite())->setIdP($idP)->setIdS($idS)->setTaux($taux[0])->setTauxEtat($taux[1])->setTauxRegion($taux[2]));
                $this->em->persist((new EtatProduction())->setIdP($idP)->setIdS($idS)->setExceProduction($pExces > 10)->setPourcenatgeExce($pExces));
                $this->em->persist((new Cout())->setIdP($idP)->setIdS($idS)->setListDesBesoinsParHectare('Semences, engrais, irrigation, main d\'œuvre')->setCoutEssentielParHectare(round($cout * 0.7))->setCoutTotalParHectare($cout));
                $this->em->persist((new Prix())->setIdP($idP)->setIdS($idS)->setPrixMoyenneExportation($prix[0])->setPrixMoyenneDeGrosParEtat($prix[1])->setPrixMoyenneDeGrosParRegion($prix[2]));
                $this->em->persist((new Production())->setIdP($idP)->setIdS($idS)->setProduction($prod[0] / $n)->setProductionParEtat($prod[1] / $n)->setProductionParRegion($prod[2] / $n));
                $this->em->persist((new Exportation())->setIdP($idP)->setIdS($idS)->setExportExist($export)->setQuantiteExporte($qte / $n)->setSocieteExport($soc));
                $this->em->persist((new Consommation())->setIdP($idP)->setIdS($idS)->setConsommation($prod[0] * 0.8 / $n)->setConsommationParEtat($prod[1] * 0.8 / $n)->setConsommationParRegion($prod[2] * 0.8 / $n));
                $this->em->persist((new DateCultivation())->setIdP($idP)->setIdS($idS)
                    ->setDateDebutCultivation($dC)->setDateDebutCultivationDansEtat(clone $dC)->setDateDebutCultivationDansRegion((clone $dC)->modify('+7 days'))
                    ->setDateDebutExploitation($dE)->setDateDebutExploitationDansEtat(clone $dE)->setDateDebutExploitationDansRegion((clone $dE)->modify('+7 days')));
                $this->em->persist((new TerreDesignee())->setIdP($idP)->setIdS($idS)->setPourcentageTerreDesignee(round(100 / count($produits), 1))->setPourcentageTerreDesigneeDansEtat(round(100 / count($produits), 1))->setPourcentageTerreDesigneeDansRegion(round(100 / count($produits) + 2, 1)));
            }
        }

        $code = 0;
        foreach ($regions as [$nom, $cp, $ids]) {
            foreach ($ids as $idP) {
                $this->em->persist((new Region())
                    ->setCodeRegion(++$code)->setEtat('Tunisie')->setRegion($nom)->setCodePostal($cp)->setAnnee($annee)
                    ->setNombreBebe(12000)->setNombreEnfant(45000)->setNombreAdolescent(38000)->setNombreJeune(60000)
                    ->setNombreAdulte(150000)->setNombreAgee(40000)->setPourcentageCroissanceDemog(0.9)
                    ->setIdP($idP)->setConsommationParJour(round(1.5 + $idP / 10, 2)));
            }
        }
    }

    private function seedFormations(): void
    {
        $this->em->persist((new Formation())
            ->setType('Végétale')->setSousType('Pastèque')->setCategorie('Fruits')
            ->setTitle('Guide Complet de la Culture de la pastèque 🍉')
            ->setDescription("Cultiver des pastèques succulentes avec notre guide complet ! Dans cette vidéo, nous vous dévoilons les étapes essentielles pour cultiver avec succès des pastèques dans votre propre jardin. Apprenez tout sur le choix des variétés, la préparation du sol, la plantation et l'arrosage afin de garantir une récolte abondante de fruits juteux. Que vous soyez novice en jardinage ou un expert chevronné, ce guide vous fournira toutes les informations nécessaires pour devenir un maître de la culture de la pastèque.")
            ->setDuration(4)->setDifficulty('debutant')
            ->setFormationVideo('/Video/culture-pasteque.mp4'));

        $this->em->persist((new Formation())
            ->setType('Végétale')->setSousType('Maïs')->setCategorie('Céréales')
            ->setTitle('Comment réussir la culture du MAÏS 🌽')
            ->setDescription("Sommaire :\n00:00 Présentation de la plante\n02:36 Semis en contenant\n03:49 Semis en pleine terre\n07:21 Plantation\n12:50 Récolte")
            ->setDuration(17)->setDifficulty('intermediaire')
            ->setFormationVideo('/Video/culture-mais.mp4'));
    }
}
