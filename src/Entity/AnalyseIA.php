<?php

namespace App\Entity;

use App\Repository\AnalyseIARepository;
use Doctrine\ORM\Mapping as ORM;

/** Analyse rédigée par l'IA (image satellite, conseil de culture...) pour un terrain. */
#[ORM\Entity(repositoryClass: AnalyseIARepository::class)]
#[ORM\Table(name: 'analyse_ia')]
#[ORM\Index(columns: ['code_terrain', 'type', 'created_at'], name: 'idx_analyse_terrain_type')]
class AnalyseIA
{
    public const TYPES = ['satellite' => 'Image satellite', 'conseil' => 'Conseil de culture'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CodeTerrain::class)]
    #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: false, onDelete: 'CASCADE')]
    private ?CodeTerrain $terrain = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = 'conseil';

    #[ORM\Column(type: 'text')]
    private string $resume = '';

    #[ORM\Column(type: 'json')]
    private array $details = [];

    /** Modèle utilisé (ex. gemini-flash-latest) ou "regles" quand aucune IA n'est configurée */
    #[ORM\Column(type: 'string', length: 60)]
    private string $modele = 'regles';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(CodeTerrain $t, string $type, string $resume, array $details, string $modele)
    {
        $this->terrain = $t;
        $this->type = $type;
        $this->resume = $resume;
        $this->details = $details;
        $this->modele = $modele;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTerrain(): ?CodeTerrain { return $this->terrain; }
    public function getType(): string { return $this->type; }
    public function getResume(): string { return $this->resume; }
    public function getDetails(): array { return $this->details; }
    public function getModele(): string { return $this->modele; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function toArray(): array
    {
        return ['type' => $this->type, 'resume' => $this->resume, 'details' => $this->details, 'modele' => $this->modele, 'date' => $this->createdAt->format('Y-m-d H:i')];
    }
}
