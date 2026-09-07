<?php

namespace App\Controller;

use App\Entity\Foyer;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\RoomRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Onglet de paramétrage du foyer : code d'invitation, ajout de membres,
 * et gestion des tâches (renommer, supprimer, ajuster fréquence / dernière
 * date de nettoyage). Toute mutation reste ici côté backend, le front
 * n'affiche que les valeurs et formulaires.
 */
#[IsGranted('ROLE_USER')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly TaskRepository $taskRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/parametres', name: 'app_settings', methods: ['GET'])]
    public function index(): Response
    {
        $foyer = $this->currentFoyer();

        return $this->render('settings/index.html.twig', [
            'foyer' => $foyer,
            'members' => $this->userRepository->findBy(['foyer' => $foyer], ['createdAt' => 'ASC']),
            'rooms' => $this->roomRepository->findBy(['foyer' => $foyer], ['position' => 'ASC']),
        ]);
    }

    #[Route('/parametres/membres', name: 'app_settings_add_member', methods: ['POST'])]
    public function addMember(Request $request): Response
    {
        $token = new CsrfToken('add_member', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        $pseudo = trim((string) $request->request->get('pseudo'));
        $password = (string) $request->request->get('password');
        $passwordConfirm = (string) $request->request->get('password_confirm');

        $errors = [];

        if ($email === '' || $this->validator->validate($email, new Email())->count() > 0) {
            $errors[] = 'Adresse email invalide.';
        } elseif ($this->userRepository->findOneByEmail($email) !== null) {
            $errors[] = 'Un compte existe déjà avec cet email.';
        }

        if ($pseudo === '') {
            $errors[] = 'Le prénom / pseudo est obligatoire.';
        } elseif (mb_strlen($pseudo) > 60) {
            $errors[] = 'Le prénom / pseudo est trop long (60 caractères max).';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Les deux mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('app_settings');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setFoyer($this->currentFoyer());
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s a été ajouté·e au foyer.', $pseudo));

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/parametres/taches/{id}', name: 'app_settings_task_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateTask(int $id, Request $request): Response
    {
        $task = $this->findTaskOrFail($id);

        $token = new CsrfToken('update_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name = trim((string) $request->request->get('name'));
        $frequencyDays = max(1, (int) $request->request->get('frequencyDays', 7));
        $lastDoneAtRaw = trim((string) $request->request->get('lastDoneAt'));

        if ($name === '') {
            $this->addFlash('error', 'Le nom de la tâche est obligatoire.');

            return $this->redirectToRoute('app_settings');
        }

        $lastDoneAt = null;
        if ($lastDoneAtRaw !== '') {
            $lastDoneAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $lastDoneAtRaw);
            if ($lastDoneAt === false) {
                $this->addFlash('error', 'Date de dernier nettoyage invalide.');

                return $this->redirectToRoute('app_settings');
            }
        }

        $task->setName($name);
        $task->setFrequencyDays($frequencyDays);
        $task->setLastDoneAt($lastDoneAt);

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Tâche "%s" mise à jour.', $name));

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/parametres/taches/{id}/supprimer', name: 'app_settings_task_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTask(int $id, Request $request): Response
    {
        $task = $this->findTaskOrFail($id);

        $token = new CsrfToken('delete_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name = $task->getName();

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Tâche "%s" supprimée.', $name));

        return $this->redirectToRoute('app_settings');
    }

    private function findTaskOrFail(int $id): Task
    {
        $task = $this->taskRepository->find($id);
        $foyer = $this->currentFoyer();

        if (!$task instanceof Task || $task->getRoom()?->getFoyer()?->getId() !== $foyer->getId()) {
            throw $this->createNotFoundException('Tâche introuvable.');
        }

        return $task;
    }

    private function currentFoyer(): Foyer
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getFoyer();
    }
}
