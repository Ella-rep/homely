<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tâche du catalogue, proposée pour un type de pièce (ex: "Aspirateur" pour Salon).
 * Ce n'est PAS une tâche planifiée dans une pièce : voir RoomTask.
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
#[ORM\UniqueConstraint(name: 'UNIQ_TASK_ROOM_TYPE_LABEL', columns: ['room_type_id', 'label'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['task:read']],
    order: ['label' => 'ASC'],
    paginationEnabled: false,
)]
#[ApiFilter(SearchFilter::class, properties: ['roomType' => 'exact'])]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['task:read', 'room_type:read', 'room_task:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RoomType::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['task:read'])]
    private ?RoomType $roomType = null;

    #[ORM\Column(length: 255)]
    #[Groups(['task:read', 'room_type:read', 'room_task:read'])]
    #[Assert\NotBlank]
    private string $label = '';

    /** Fréquence proposée par défaut (jours) quand la tâche est ajoutée à une pièce. */
    #[ORM\Column(options: ['default' => 7])]
    #[Groups(['task:read', 'room_type:read'])]
    #[Assert\Positive]
    private int $defaultFrequencyDays = 7;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDefaultFrequencyDays(): int
    {
        return $this->defaultFrequencyDays;
    }

    public function setDefaultFrequencyDays(int $defaultFrequencyDays): static
    {
        $this->defaultFrequencyDays = $defaultFrequencyDays;

        return $this;
    }
}
