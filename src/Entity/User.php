<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une personne du foyer, authentifiée par email + mot de passe.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /** Prénom / surnom affiché dans l'appli. */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    private string $pseudo = '';

    #[ORM\Column]
    private array $roles = [];

    /** Mot de passe hashé. */
    #[ORM\Column]
    private string $password = '';

    /** @var Collection<int, Foyer> Un utilisateur peut appartenir à plusieurs foyers. */
    #[ORM\ManyToMany(targetEntity: Foyer::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_foyer')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'foyer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $foyers;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->foyers = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Identifiant unique utilisé par le système de sécurité (email).
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPseudo(): string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection<int, Foyer>
     */
    public function getFoyers(): Collection
    {
        return $this->foyers;
    }

    public function addFoyer(Foyer $foyer): static
    {
        if (!$this->foyers->contains($foyer)) {
            $this->foyers->add($foyer);
        }

        return $this;
    }

    public function removeFoyer(Foyer $foyer): static
    {
        $this->foyers->removeElement($foyer);

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Rien de sensible en plus du hash de mot de passe à effacer ici.
     */
    public function eraseCredentials(): void
    {
    }
}
