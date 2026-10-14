<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TaskLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Historique en lecture seule des nettoyages effectués (créé uniquement par
 * RoomTaskCompleteProcessor / RoomController). Supprimé en cascade avec sa RoomTask (RG4).
 */
#[ORM\Entity(repositoryClass: TaskLogRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['tasklog:read']],
    order: ['doneAt' => 'DESC'],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: ['roomTask' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['doneAt'])]
class TaskLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['tasklog:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RoomTask::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['tasklog:read'])]
    private ?RoomTask $roomTask = null;

    #[ORM\Column]
    #[Groups(['tasklog:read'])]
    private DateTimeImmutable $doneAt;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['tasklog:read'])]
    private ?string $doneBy = null;

    public function __construct()
    {
        $this->doneAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoomTask(): ?RoomTask
    {
        return $this->roomTask;
    }

    public function setRoomTask(?RoomTask $roomTask): static
    {
        $this->roomTask = $roomTask;

        return $this;
    }

    public function getDoneAt(): DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function setDoneAt(DateTimeImmutable $doneAt): static
    {
        $this->doneAt = $doneAt;

        return $this;
    }

    public function getDoneBy(): ?string
    {
        return $this->doneBy;
    }

    public function setDoneBy(?string $doneBy): static
    {
        $this->doneBy = $doneBy;

        return $this;
    }
}
