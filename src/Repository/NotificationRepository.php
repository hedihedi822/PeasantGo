<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return Notification[] */
    public function findRecentes(Utilisateur $u, int $limit = 15): array
    {
        return $this->findBy(['utilisateur' => $u], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    public function countNonLues(Utilisateur $u): int
    {
        return $this->count(['utilisateur' => $u, 'lue' => false]);
    }

    public function marquerToutLu(Utilisateur $u): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.lue', ':vrai')
            ->where('n.utilisateur = :u')
            ->andWhere('n.lue = :faux')
            ->setParameter('vrai', true)
            ->setParameter('faux', false)
            ->setParameter('u', $u)
            ->getQuery()
            ->execute();
    }

    /** Supprime les notifications lues de plus de N jours (ménage). */
    public function purger(int $jours = 90): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.lue = :vrai')
            ->andWhere('n.createdAt < :limite')
            ->setParameter('vrai', true)
            ->setParameter('limite', new \DateTime("-$jours days"))
            ->getQuery()
            ->execute();
    }
}
