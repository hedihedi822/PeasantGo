<?php
// src/Repository/OutilsRepository.php

namespace App\Repository;

use App\Entity\Outils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OutilsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Outils::class);
    }

    // Méthodes personnalisées équivalentes
    public function add(Outils $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByIdO(int $idO): ?Outils
    {
        return $this->find($idO);
    }

    public function findAllOutils(): array
    {
        return $this->findAll();
    }
}