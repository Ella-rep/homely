<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\RoomTaskCompleteInput;
use App\Api\RoomTaskCompleteProcessor;
use App\Repository\RoomTaskRepository;
use App\Service\Dirtiness;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tâche récurrente choisie dans une pièce (Room x Task du catalogue), avec sa fréquence
 * et sa dernière réalisation. Statut / pourcentage / libellé d'échéance sont calculés côté
 * backend (voir Dirtiness) : le front ne fait qu'afficher ces champs.
 */
#[ORM\Entity(repositoryClass: RoomTaskRepository::class)]
#[ORM\Table(name: 'room_task')]
#[ORM\UniqueConstraint(name: 'UNIQ_ROOM_TASK_ROOM_TASK', columns: ['room_id', 'task_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/room_tasks/{id}/complete',
            input: RoomTaskCompleteInput::class,
            output: RoomTask::class,
            processor: RoomTaskCompleteProcessor::class,
            normalizationContext: ['groups' => ['room_task:read']],
            name: 'complete',
        ),
    ],
    normalizationContext: ['groups' => ['room_task:read']],
    denormalizationContext: ['groups' => ['room_task:write']],
    order: ['createdAt' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['room' => 'exact', 'task' => 'exact'])]
class RoomTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['room_task:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'roomTasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['room_task:read', 'room_task:write'])]
    #[Assert\NotNull]
    private ?Room $room = null;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['room_task:read', 'room_task:write'])]
    #[Assert\NotNull]
    private ?Task $task = null;

    /** Fréquence de nettoyage souhaitée, en jours. */
    #[ORM\Column]
    #[Groups(['room_task:read', 'room_task:write'])]
    #[Assert\Positive]
    private int $frequencyDays = 7;

    #[ORM\Column(nullable: true)]
    #[Groups(['room_task:read'])]
    #[ApiProperty(writable: false)]
    private ?DateTimeImmutable $lastDoneAt = null;

    #[ORM\Column]
    #[Groups(['room_task:read'])]
    private DateTimeImmutable $createdAt;

    /**
     * RG4 : suppression en cascade des TaskLog quand la tâche de la pièce est supprimée, pas de soft delete.
     *
     * @var Collection<int, TaskLog>
     */
    #[ORM\OneToMany(mappedBy: 'roomTask', targetEntity: TaskLog::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['doneAt' => 'DESC'])]
    private Collection $logs;

    public function __construct()
    {
        $this->logs = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): static
    {
        $this->task = $task;

        return $this;
    }

    /** Libellé affiché : celui de la tâche du catalogue. */
    public function getLabel(): string
    {
        return $this->task?->getLabel() ?? '';
    }

    public function getFrequencyDays(): int
    {
        return $this->frequencyDays;
    }

    public function setFrequencyDays(int $frequencyDays): static
    {
        $this->frequencyDays = $frequencyDays;

        return $this;
    }

    public function getLastDoneAt(): ?DateTimeImmutable
    {
        return $this->lastDoneAt;
    }

    public function setLastDoneAt(?DateTimeImmutable $lastDoneAt): static
    {
        $this->lastDoneAt = $lastDoneAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, TaskLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(TaskLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setRoomTask($this);
        }

        return $this;
    }

    #[Groups(['room_task:read'])]
    #[ApiProperty]
    public function getStatus(): string
    {
        return Dirtiness::status($this);
    }

    #[Groups(['room_task:read'])]
    #[ApiProperty]
    public function getProgressPercent(): int
    {
        return Dirtiness::progressPercent($this);
    }

    #[Groups(['room_task:read'])]
    #[ApiProperty]
    public function getDueLabel(): string
    {
        return Dirtiness::dueLabel($this);
    }
}
