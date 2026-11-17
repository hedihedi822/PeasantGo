<?php

namespace App\Entity;

use App\Repository\PageVeilleRepository;
use Doctrine\ORM\Mapping as ORM;

/** Page déjà lue par la veille (empreinte du contenu : on ne ré-analyse pas une page qui n'a pas changé). */
#[ORM\Entity(repositoryClass: PageVeilleRepository::class)]
#[ORM\Table(name: 'page_veille')]
#[ORM\UniqueConstraint(name: 'uniq_page_veille_url', columns: ['url_hash'])]
class PageVeille
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SourceVeille::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SourceVeille $source = null;

    #[ORM\Column(type: 'string', length: 1000)]
    private string $url = '';

    #[ORM\Column(name: 'url_hash', type: 'string', length: 64)]
    private string $urlHash = '';

    #[ORM\Column(name: 'contenu_hash', type: 'string', length: 64)]
    private string $contenuHash = '';

    #[ORM\Column(name: 'nb_extraits', type: 'integer', options: ['default' => 0])]
    private int $nbExtraits = 0;

    #[ORM\Column(name: 'lue_le', type: 'datetime')]
    private \DateTimeInterface $lueLe;

    public function __construct(SourceVeille $source, string $url)
    {
        $this->source = $source;
        $this->url = mb_substr($url, 0, 1000);
        $this->urlHash = hash('sha256', $url);
        $this->lueLe = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getSource(): ?SourceVeille { return $this->source; }
    public function getUrl(): string { return $this->url; }
    public function getContenuHash(): string { return $this->contenuHash; }
    public function setContenuHash(string $h): self { $this->contenuHash = $h; $this->lueLe = new \DateTime(); return $this; }
    public function getNbExtraits(): int { return $this->nbExtraits; }
    public function setNbExtraits(int $n): self { $this->nbExtraits = $n; return $this; }
    public function getLueLe(): \DateTimeInterface { return $this->lueLe; }
}
