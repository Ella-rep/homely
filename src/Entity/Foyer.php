<?php

namespace App\Entity;

use App\Repository\FoyerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un foyer partagé entre plusieurs personnes (ex: un couple). Toutes les
 * pièces appartiennent à un foyer ; on rejoint un foyer existant avec son
 * code d'invitation, ou on en crée un nouveau à l'inscription.
 */
#[ORM\Entity(repositoryClass: FoyerRepository::class)]
class Foyer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $name = '';

    /** Code court à partager pour rejoindre le foyer (ex: "7K3PQR"). */
    #[ORM\Column(length: 8, unique: true)]
    private string $inviteCode = '';

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(mappedBy: 'foyer', targetEntity: User::class)]
    private Collection $users;

    /** @var Collection<int, Room> */
    #[ORM\OneToMany(mappedBy: 'foyer', targetEntity: Room::class)]
    private Collection $rooms;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->rooms = new ArrayCollection();
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

    public function getInviteCode(): string
    {
        return $this->inviteCode;
    }

    public function setInviteCode(string $inviteCode): static
    {
        $this->inviteCode = $inviteCode;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    /**
     * @return Collection<int, Room>
     */
    public function getRooms(): Collection
    {
        return $this->rooms;
    }
}
