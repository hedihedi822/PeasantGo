<?php

namespace App\Twig;

use App\Entity\CodeTerrain;
use App\Terrain\TerrainActif;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** terrain_actif() dans les templates : le terrain choisi sur « Mes terrains ». */
final class AppExtension extends AbstractExtension
{
    public function __construct(private TerrainActif $terrainActif)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('terrain_actif', fn (): ?CodeTerrain => $this->terrainActif->get())];
    }
}
