<?php

namespace App\Command;

use App\Entity\Admin;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un membre de l'équipe (accès /Equipe_PeasantGo) :
 *   php bin/console app:create-admin email@exemple.com
 */
#[AsCommand(name: 'app:create-admin', description: 'Crée un compte administrateur')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private UtilisateurRepository $utilisateurs,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('telephone', InputArgument::OPTIONAL, 'Téléphone', '+21670000001');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide.');
            return Command::FAILURE;
        }
        if ($this->utilisateurs->findOneByEmail($email)) {
            $io->error('Un compte existe déjà avec cet email.');
            return Command::FAILURE;
        }

        $password = $io->askHidden('Mot de passe', function (?string $p) {
            if (!$p || strlen($p) < 8) {
                throw new \RuntimeException('8 caractères minimum.');
            }
            return $p;
        });

        $admin = (new Admin())->setEmail($email)->setTelephone((string) $input->getArgument('telephone'))->setNom('PeasantGo')->setPrenom('Admin');
        $admin->setMotDePasse($this->hasher->hashPassword($admin, $password));
        $this->em->persist($admin);
        $this->em->flush();

        $io->success("Administrateur $email créé.");
        return Command::SUCCESS;
    }
}
