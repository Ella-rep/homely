<?php

namespace App\Controller;

use App\Entity\Foyer;
use App\Entity\User;
use App\Repository\FoyerRepository;
use App\Service\CurrentFoyer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des foyers de l'utilisateur connecté : en avoir plusieurs, changer de
 * foyer actif, en créer / rejoindre un autre, le quitter (tout membre) ou le
 * supprimer (créateur uniquement, avec ses pièces et tâches).
 */
#[IsGranted('ROLE_USER')]
final class FoyerController extends AbstractController
{
    public function __construct(
        private readonly FoyerRepository $foyerRepository,
        private readonly CurrentFoyer $currentFoyer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/foyers', name: 'app_foyers', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('foyer/index.html.twig', [
            'foyers' => $this->user()->getFoyers(),
            'current' => $this->currentFoyer->get(),
        ]);
    }

    #[Route('/foyers/creer', name: 'app_foyer_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->assertCsrf($request, 'foyer_create');

        $name = trim((string) $request->request->get('foyer_name'));
        if ($name === '' || mb_strlen($name) > 80) {
            $this->addFlash('error', 'Le nom du foyer est obligatoire (80 caractères max).');

            return $this->redirectToRoute('app_foyers');
        }

        $user = $this->user();

        $foyer = new Foyer();
        $foyer->setName($name);
        $foyer->setInviteCode($this->foyerRepository->generateUniqueInviteCode());
        $foyer->setOwner($user);
        $user->addFoyer($foyer);

        $this->entityManager->persist($foyer);
        $this->entityManager->flush();

        $this->currentFoyer->set($foyer);
        $this->addFlash('success', sprintf('Foyer "%s" créé (code à partager : %s).', $name, $foyer->getInviteCode()));

        return $this->redirectToRoute('app_home');
    }

    #[Route('/foyers/rejoindre', name: 'app_foyer_join', methods: ['POST'])]
    public function join(Request $request): Response
    {
        $this->assertCsrf($request, 'foyer_join');

        $code = strtoupper(trim((string) $request->request->get('invite_code')));
        $foyer = $code !== '' ? $this->foyerRepository->findOneByInviteCode($code) : null;

        if (!$foyer instanceof Foyer) {
            $this->addFlash('error', 'Aucun foyer ne correspond à ce code.');

            return $this->redirectToRoute('app_foyers');
        }

        $user = $this->user();
        if ($user->getFoyers()->contains($foyer)) {
            $this->addFlash('error', 'Tu fais déjà partie de ce foyer.');

            return $this->redirectToRoute('app_foyers');
        }

        $user->addFoyer($foyer);
        $this->entityManager->flush();

        $this->currentFoyer->set($foyer);
        $this->addFlash('success', sprintf('Tu as rejoint le foyer "%s".', $foyer->getName()));

        return $this->redirectToRoute('app_home');
    }

    #[Route('/foyers/{id}/activer', name: 'app_foyer_switch', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function switch(int $id, Request $request): Response
    {
        $foyer = $this->findMemberFoyerOrFail($id);
        $this->assertCsrf($request, 'foyer_switch');

        $this->currentFoyer->set($foyer);

        return $this->redirectToRoute('app_home');
    }

    #[Route('/foyers/{id}/quitter', name: 'app_foyer_leave', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leave(int $id, Request $request): Response
    {
        $foyer = $this->findMemberFoyerOrFail($id);
        $this->assertCsrf($request, 'foyer_leave');

        $user = $this->user();
        if ($foyer->isOwnedBy($user)) {
            $this->addFlash('error', 'Tu es le créateur de ce foyer : supprime-le pour le quitter.');

            return $this->redirectToRoute('app_foyers');
        }

        $name = $foyer->getName();
        $user->removeFoyer($foyer);
        $this->entityManager->flush();

        // Le foyer actif retombera sur un autre foyer de l'utilisateur au prochain accès.
        $this->currentFoyer->clear();
        $this->addFlash('success', sprintf('Tu as quitté le foyer "%s".', $name));

        return $this->redirectToRoute('app_foyers');
    }

    #[Route('/foyers/{id}/supprimer', name: 'app_foyer_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $foyer = $this->findMemberFoyerOrFail($id);
        $this->assertCsrf($request, 'foyer_delete');

        if (!$foyer->isOwnedBy($this->user())) {
            throw $this->createAccessDeniedException('Seul le créateur peut supprimer le foyer.');
        }

        $name = $foyer->getName();

        foreach ($foyer->getUsers()->toArray() as $member) {
            $member->removeFoyer($foyer);
        }

        // Pièces, tâches planifiées, historiques et liens membres : supprimés en cascade par la base (ON DELETE CASCADE).
        $this->entityManager->remove($foyer);
        $this->entityManager->flush();

        $this->currentFoyer->clear();
        $this->addFlash('success', sprintf('Foyer "%s" supprimé.', $name));

        return $this->redirectToRoute('app_foyers');
    }

    private function findMemberFoyerOrFail(int $id): Foyer
    {
        foreach ($this->user()->getFoyers() as $foyer) {
            if ($foyer->getId() === $id) {
                return $foyer;
            }
        }

        throw $this->createNotFoundException('Foyer introuvable.');
    }

    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
