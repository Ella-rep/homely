<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\RoomRepository;
use App\Service\Dirtiness;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une pièce / zone du foyer (ex: Cuisine, Salle de bain), rattachée à un type de pièce (RoomType).
 * Toute la logique de saleté agrégée est calculée ici via Dirtiness, jamais côté front.
 */
#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'room')]
#[ORM\UniqueConstraint(name: 'UNIQ_ROOM_FOYER_LABEL', columns: ['foyer_id', 'label'])]
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
#[ApiFilter(OrderFilter::class, properties: ['position', 'label'])]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['room:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['room:read', 'room:write', 'room_task:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $label = '';

    #[ORM\ManyToOne(targetEntity: RoomType::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['room:read', 'room:write'])]
    #[Assert\NotNull]
    private ?RoomType $roomType = null;

    /** Couleur d'affichage de la tuile, ex: #F0A868 */
    #[ORM\Column(length: 7)]
    #[Groups(['room:read', 'room:write', 'room_task:read'])]
    #[Assert\NotBlank]
    #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
    private string $color = '#8AA9B8';

    #[ORM\Column]
    #[Groups(['room:read', 'room:write'])]
    private int $position = 0;

    /** @var Collection<int, RoomTask> */
    #[ORM\OneToMany(mappedBy: 'room', targetEntity: RoomTask::class, cascade: ['persist'], orphanRemoval: false)]
    #[Groups(['room:read'])]
    #[ApiProperty(writable: false)]
    private Collection $roomTasks;

    #[ORM\ManyToOne(targetEntity: Foyer::class, inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ApiProperty(writable: false)]
    private ?Foyer $foyer = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->roomTasks = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getRoomType(): ?RoomType
    {
        return $this->roomType;
    }

    public function setRoomType(?RoomType $roomType): static
    {
        $this->roomType = $roomType;

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
     * @return Collection<int, RoomTask>
     */
    public function getRoomTasks(): Collection
    {
        return $this->roomTasks;
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
        return Dirtiness::aggregateStatus($this->roomTasks->toArray());
    }

    #[Groups(['room:read'])]
    #[ApiProperty]
    public function getTaskCount(): int
    {
        return $this->roomTasks->count();
    }

    #[Groups(['room:read'])]
    #[ApiProperty]
    public function getOverdueCount(): int
    {
        return Dirtiness::countByStatus($this->roomTasks->toArray(), Dirtiness::STATUS_OVERDUE);
    }

    #[Groups(['room:read'])]
    #[ApiProperty]
    public function getDueSoonCount(): int
    {
        return Dirtiness::countByStatus($this->roomTasks->toArray(), Dirtiness::STATUS_DUE_SOON);
    }
}
