<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envoie une notification à TOUS les utilisateurs :
 *  1. dans le site (cloche de la barre de navigation) : toujours, pour tout le monde ;
 *  2. par email : à tout le monde par défaut, sauf ceux qui ont désactivé
 *     « Notifications par email » dans leurs paramètres (lien de désinscription obligatoire).
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array{notifications: int, emails: int, echecs: int} */
    public function envoyerATous(string $type, string $titre, string $message, ?string $lien = null, bool $email = true): array
    {
        $titre = mb_substr($titre, 0, 150);
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        // Une seule requête SQL, quel que soit le nombre d'utilisateurs
        $nb = $this->em->getConnection()->executeStatement(
            'INSERT INTO notification (utilisateur_id, type, titre, message, lien, lue, created_at)
             SELECT id, :type, :titre, :message, :lien, :lue, :now FROM utilisateur',
            ['type' => $type, 'titre' => $titre, 'message' => $message, 'lien' => $lien, 'lue' => false, 'now' => $now],
            ['lue' => ParameterType::BOOLEAN]
        );

        $emails = 0;
        $echecs = 0;
        if ($email) {
            $q = $this->em->createQuery('SELECT u FROM ' . Utilisateur::class . ' u');
            foreach ($q->toIterable() as $u) {
                /** @var Utilisateur $u */
                if (($u->getPreferences()['emailNotifications'] ?? true) === false) {
                    continue;
                }
                try {
                    $this->mailer->send((new TemplatedEmail())
                        ->to($u->getEmail())
                        ->subject('PeasantGo.AI – ' . $titre)
                        ->htmlTemplate('emails/notification.html.twig')
                        ->context([
                            'nom' => $u->getNomAffiche(),
                            'titre' => $titre,
                            'message' => $message,
                            'lien' => $lien ? $this->absolu($lien) : $this->urls->generate('app_home_user', [], UrlGeneratorInterface::ABSOLUTE_URL),
                            'parametres' => $this->urls->generate('app_parametres', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        ]));
                    ++$emails;
                } catch (\Throwable $e) {
                    ++$echecs;
                    $this->logger->error('Email de notification non envoyé', ['user' => $u->getId(), 'erreur' => $e->getMessage()]);
                }
                $this->em->detach($u);
            }
        }

        $this->logger->info('Notification envoyée à tous', ['type' => $type, 'titre' => $titre, 'notifications' => $nb, 'emails' => $emails]);

        return ['notifications' => $nb, 'emails' => $emails, 'echecs' => $echecs];
    }

    /** Notification pour un seul utilisateur (bienvenue, compte...). */
    public function envoyerA(Utilisateur $u, string $type, string $titre, string $message, ?string $lien = null): Notification
    {
        $n = (new Notification())->setUtilisateur($u)->setType($type)->setTitre(mb_substr($titre, 0, 150))->setMessage($message)->setLien($lien);
        $this->em->persist($n);
        return $n;
    }

    private function absolu(string $lien): string
    {
        if (preg_match('#^https?://#', $lien)) {
            return $lien;
        }
        $base = rtrim($this->urls->generate('app_home_no', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');
        return $base . '/' . ltrim($lien, '/');
    }
}
