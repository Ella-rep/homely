<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Un utilisateur connecté qui n'appartient plus à aucun foyer (il a quitté ou
 * supprimé le dernier) est renvoyé vers /foyers pour en créer ou en rejoindre un.
 */
final class RequireFoyerSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = ['app_foyers', 'app_foyer_create', 'app_foyer_join', 'app_logout', 'app_login'];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if ($route === '' || str_starts_with($route, '_') || in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $user->getFoyers()->isEmpty()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_foyers')));
        }
    }
}
