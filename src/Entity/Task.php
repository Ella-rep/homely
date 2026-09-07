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
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Api\TaskCompleteInput;
use App\Api\TaskCompleteProcessor;
use App\Repository\TaskRepository;
use App\Service\Dirtiness;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une tâche récurrente rattachée à une pièce (ex: "Passer l'aspirateur" tous les 7 jours).
 * Statut / pourcentage / libellé d'échéance sont calculés côté backend (voir Dirtiness) :
 * le front ne fait qu'afficher ces champs, jamais de calcul de date.
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/tasks/{id}/complete',
            input: TaskCompleteInput::class,
            output: Task::class,
            processor: TaskCompleteProcessor::class,
            normalizationContext: ['groups' => ['task:read']],
            name: 'complete',
        ),
    ],
    normalizationContext: ['groups' => ['task:read']],
    denormalizationContext: ['groups' => ['task:write']],
    order: ['createdAt' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['room' => 'exact'])]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['task:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotNull]
    private ?Room $room = null;

    /** Fréquence de nettoyage souhaitée, en jours. */
    #[ORM\Column]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\Positive]
    private int $frequencyDays = 7;

    #[ORM\Column(nullable: true)]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private ?DateTimeImmutable $lastDoneAt = null;

    #[ORM\Column]
    #[Groups(['task:read'])]
    private DateTimeImmutable $createdAt;

    /**
     * RG4 : suppression en cascade des TaskLog quand la tâche est supprimée, pas de soft delete.
     *
     * @var Collection<int, TaskLog>
     */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskLog::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
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
            $log->setTask($this);
        }

        return $this;
    }

    #[Groups(['task:read'])]
    #[ApiProperty]
    public function getStatus(): string
    {
        return Dirtiness::status($this);
    }

    #[Groups(['task:read'])]
    #[ApiProperty]
    public function getProgressPercent(): int
    {
        return Dirtiness::progressPercent($this);
    }

    #[Groups(['task:read'])]
    #[ApiProperty]
    public function getDueLabel(): string
    {
        return Dirtiness::dueLabel($this);
    }
}
