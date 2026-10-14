<?php

namespace App\Service;

use App\Entity\Foyer;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Foyer actif de l'utilisateur connecté. Un utilisateur peut appartenir à
 * plusieurs foyers : l'id du foyer actif est mémorisé en session et toujours
 * revalidé (appartenance), avec repli sur le premier foyer de l'utilisateur.
 */
final class CurrentFoyer
{
    private const SESSION_KEY = 'current_foyer_id';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function get(): ?Foyer
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $foyers = $user->getFoyers();
        if ($foyers->isEmpty()) {
            return null;
        }

        $session = $this->requestStack->getSession();
        $id = $session->get(self::SESSION_KEY);

        foreach ($foyers as $foyer) {
            if ($foyer->getId() === $id) {
                return $foyer;
            }
        }

        $first = $foyers->first();
        $session->set(self::SESSION_KEY, $first->getId());

        return $first;
    }

    public function set(Foyer $foyer): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $foyer->getId());
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
