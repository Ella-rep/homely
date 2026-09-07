<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use App\Repository\RoomRepository;
use App\Service\Dirtiness;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une pièce / zone de la maison (ex: Cuisine, Salle de bain).
 * Toute la logique de saleté agrégée est calculée ici via Dirtiness, jamais côté front.
 */
#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['room:read']],
    denormalizationContext: ['groups' => ['room:write']],
    order: ['position' => 'ASC'],
)]
#[ApiFilter(OrderFilter::class, properties: ['position', 'name'])]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['room:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Groups(['room:read', 'room:write', 'task:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $name = '';

    /** Couleur d'affichage de la tuile, ex: #F0A868 */
    #[ORM\Column(length: 7)]
    #[Groups(['room:read', 'room:write', 'task:read'])]
    #[Assert\NotBlank]
    #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
    private string $color = '#8AA9B8';

    #[ORM\Column]
    #[Groups(['room:read', 'room:write'])]
    private int $position = 0;

    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Task::class, cascade: ['persist'], orphanRemoval: false)]
    #[Groups(['room:read'])]
    #[ApiProperty(writable: false)]
    private Collection $tasks;

    #[ORM\ManyToOne(targetEntity: Foyer::class, inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ApiProperty(writable: false)]
    private ?Foyer $foyer = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFoyer(): ?Foyer
    {
        return $this->foyer;
    }

    public function setFoyer(?Foyer $foyer): static
    {
        $this->foyer = $foyer;

        return $this;
    }

    #[Groups(['room:read'])]
    #[ApiProperty]
    public function getStatus(): string
    {
        return Dirtiness::aggregateStatus($this->tasks->toArray());
    }

    #[Groups(['room:read'])]
    #[ApiProperty]
    public function getTaskCount(): int
    {
        return $this->tasks->count();
    }

    #[Groups(['room:read'])]
    #[ApiProperty]
    public function getOverdueCount(): int
    {
        return Dirtiness::countByStatus($this->tasks->toArray(), Dirtiness::STATUS_OVERDUE);
    }

    #[Groups(['room:read'])]
    #[ApiProperty]
    public function getDueSoonCount(): int
    {
        return Dirtiness::countByStatus($this->tasks->toArray(), Dirtiness::STATUS_DUE_SOON);
    }
}
