<?php

namespace App\Controller;

use App\Entity\Room;
use App\Entity\Task;
use App\Entity\TaskLog;
use App\Entity\User;
use App\Repository\RoomRepository;
use App\Repository\TaskRepository;
use App\Service\TaskCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Les entités sont chargées "à la main" via les repositories : la config
 * Doctrine du projet désactive le controller_resolver.auto_mapping
 * (voir config/packages/doctrine.yaml), donc pas de résolution implicite
 * Room $room / Task $task depuis {id}.
 */
#[IsGranted('ROLE_USER')]
final class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/rooms/{id}', name: 'app_room_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $room = $this->findRoomOrFail($id);

        return $this->render('room/show.html.twig', [
            'room' => $room,
            'catalogGroups' => $this->catalogGroupsForRoom($room->getName()),
        ]);
    }

    #[Route('/rooms/{id}/tasks', name: 'app_task_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createTask(int $id, Request $request): Response
    {
        $room = $this->findRoomOrFail($id);

        $token = new CsrfToken('add_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name = trim((string) $request->request->get('name'));
        $frequencyDays = max(1, (int) $request->request->get('frequencyDays', 7));

        if ($name === '') {
            $this->addFlash('error', 'Le nom de la tâche est obligatoire.');

            return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
        }

        $task = new Task();
        $task->setName($name);
        $task->setRoom($room);
        $task->setFrequencyDays($frequencyDays);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Tâche "%s" ajoutée.', $name));

        return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
    }

    #[Route('/rooms/{id}/tasks/catalogue', name: 'app_task_create_from_catalog', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createTasksFromCatalog(int $id, Request $request): Response
    {
        $room = $this->findRoomOrFail($id);

        $token = new CsrfToken('add_task_catalog', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $selected = $request->request->all('tasks');
        if (!is_array($selected) || $selected === []) {
            $this->addFlash('error', 'Choisis au moins une tâche dans le catalogue.');

            return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
        }

        $existingNames = array_map(
            static fn (Task $task): string => mb_strtolower($task->getName()),
            $room->getTasks()->toArray(),
        );

        $created = 0;
        foreach ($selected as $entry) {
            if (!is_string($entry) || !str_contains($entry, '|')) {
                continue;
            }

            [$name, $frequencyRaw] = explode('|', $entry, 2);
            $name = trim($name);
            $frequencyDays = max(1, (int) $frequencyRaw);

            if ($name === '' || in_array(mb_strtolower($name), $existingNames, true)) {
                continue;
            }

            $task = new Task();
            $task->setName($name);
            $task->setRoom($room);
            $task->setFrequencyDays($frequencyDays);
            $this->entityManager->persist($task);

            $existingNames[] = mb_strtolower($name);
            $created++;
        }

        if ($created > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('%d tâche%s ajoutée%s depuis le catalogue.', $created, $created > 1 ? 's' : '', $created > 1 ? 's' : ''));
        } else {
            $this->addFlash('error', 'Ces tâches sont déjà présentes dans la pièce.');
        }

        return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
    }

    #[Route('/tasks/{id}/complete', name: 'app_task_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function completeTask(int $id, Request $request): Response
    {
        $task = $this->taskRepository->find($id);
        if (!$task instanceof Task) {
            throw $this->createNotFoundException('Tâche introuvable.');
        }

        $room = $task->getRoom();
        $this->denyUnlessSameFoyer($room);

        $token = new CsrfToken('complete_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $now = new \DateTimeImmutable();

        $log = new TaskLog();
        $log->setDoneAt($now);
        $log->setDoneBy($user->getPseudo());
        $task->addLog($log);
        $task->setLastDoneAt($now);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->addFlash('success', 'Tâche marquée comme faite !');

        return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
    }

    /**
     * Regroupe les tâches suggérées du catalogue pour une pièce, en faisant
     * correspondre son nom (insensible à la casse) à une entrée du catalogue,
     * et en complétant toujours avec les tâches "Général".
     *
     * @return array<string, array<string, array<int, array{name: string, frequencyDays: int}>>>
     */
    private function catalogGroupsForRoom(string $roomName): array
    {
        $normalized = mb_strtolower(trim($roomName));
        $result = [];

        foreach (TaskCatalog::all() as $entry) {
            $entryName = mb_strtolower($entry['room']);
            if ($entryName === $normalized || $entryName === 'général') {
                foreach ($entry['groups'] as $category => $tasks) {
                    $result[$entry['room']][$category] = $tasks;
                }
            }
        }

        return $result;
    }

    private function findRoomOrFail(int $id): Room
    {
        $room = $this->roomRepository->find($id);
        if (!$room instanceof Room) {
            throw $this->createNotFoundException('Pièce introuvable.');
        }

        $this->denyUnlessSameFoyer($room);

        return $room;
    }

    private function denyUnlessSameFoyer(?Room $room): void
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$room instanceof Room || $room->getFoyer()?->getId() !== $user->getFoyer()?->getId()) {
            throw $this->createNotFoundException('Pièce introuvable.');
        }
    }
}
