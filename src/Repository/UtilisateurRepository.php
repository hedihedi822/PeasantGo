<?php

namespace App\Repository;

use App\Entity\Admin;
use App\Entity\RegularUtilisateur;
use App\Entity\Societe;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<Utilisateur> */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    private const TYPES = [
        'admin' => Admin::class,
        'societe' => Societe::class,
        'regular' => RegularUtilisateur::class,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /** Re-hache automatiquement le mot de passe si l'algorithme change. */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setMotDePasse($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?Utilisateur
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findOneByTelephone(string $telephone): ?Utilisateur
    {
        $tel = preg_replace('/[\s.\-()]/', '', $telephone);
        return $this->findOneBy(['telephone' => $tel]);
    }

    public function findSocieteByCode(string $code): ?Societe
    {
        return $this->getEntityManager()->getRepository(Societe::class)
            ->findOneBy(['codeSociete' => strtoupper(trim($code))]);
    }

    /** Utilisateurs d'un type : "admin", "societe" ou "regular". */
    public function findByType(string $type): array
    {
        $class = self::TYPES[$type] ?? null;
        return $class ? $this->getEntityManager()->getRepository($class)->findAll() : [];
    }

    public function countByType(string $type): int
    {
        $class = self::TYPES[$type] ?? null;
        return $class ? $this->getEntityManager()->getRepository($class)->count([]) : 0;
    }

    public function searchUsers(?string $email = null, ?string $telephone = null, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($email !== null) {
            $qb->andWhere('u.email LIKE :email')->setParameter('email', '%' . $email . '%');
        }
        if ($telephone !== null) {
            $qb->andWhere('u.telephone = :telephone')->setParameter('telephone', $telephone);
        }
        if ($type !== null && isset(self::TYPES[$type])) {
            $qb->andWhere('u INSTANCE OF :cls')->setParameter('cls', $this->getEntityManager()->getClassMetadata(self::TYPES[$type]));
        }

        return $qb->getQuery()->getResult();
    }
}
