<?php

namespace App\Terrain;

use App\Entity\CodeTerrain;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Le terrain « avec lequel » l'utilisateur est entré (choisi sur la page Mes terrains).
 * Stocké en session ; revérifié à chaque lecture (il doit appartenir à l'utilisateur connecté).
 */
class TerrainActif
{
    public const SESSION = 'terrain_actif';

    private ?CodeTerrain $cache = null;
    private bool $charge = false;

    public function __construct(private RequestStack $requests, private EntityManagerInterface $em, private Security $security)
    {
    }

    public function estChoisi(): bool
    {
        $s = $this->requests->getSession();
        return $s->has(self::SESSION);
    }

    public function get(): ?CodeTerrain
    {
        if ($this->charge) {
            return $this->cache;
        }
        $this->charge = true;
        $id = (int) $this->requests->getSession()->get(self::SESSION, 0);
        $user = $this->security->getUser();
        if (!$id || !$user instanceof Utilisateur) {
            return null;
        }
        $t = $this->em->find(CodeTerrain::class, $id);
        if (!$t || $t->getProprietaire()?->getId() !== $user->getId()) {
            $this->requests->getSession()->remove(self::SESSION);
            return null;
        }
        return $this->cache = $t;
    }

    public function choisir(?CodeTerrain $t): void
    {
        $this->requests->getSession()->set(self::SESSION, $t?->getCodeTerrain() ?? 0);
        $this->cache = $t;
        $this->charge = true;
    }

    public function oublier(): void
    {
        $this->requests->getSession()->remove(self::SESSION);
        $this->cache = null;
        $this->charge = true;
    }
}
