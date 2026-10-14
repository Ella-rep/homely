<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\RoomTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Type de pièce du catalogue (Cuisine, Salon, ...). Données de référence insérées
 * par migration, en lecture seule côté API : chaque type porte son catalogue de tâches.
 */
#[ORM\Entity(repositoryClass: RoomTypeRepository::class)]
#[ORM\Table(name: 'room_type')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['room_type:read']],
    order: ['id' => 'ASC'],
    paginationEnabled: false,
)]
class RoomType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['room_type:read', 'room:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['room_type:read', 'room:read'])]
    private string $label = '';

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(mappedBy: 'roomType', targetEntity: Task::class)]
    #[ORM\OrderBy(['label' => 'ASC'])]
    #[Groups(['room_type:read'])]
    private Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
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

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }
}
