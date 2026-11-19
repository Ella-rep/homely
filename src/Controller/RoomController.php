<?php

namespace App\Controller;

use App\Entity\Room;
use App\Entity\RoomTask;
use App\Entity\Task;
use App\Entity\TaskLog;
use App\Entity\User;
use App\Repository\RoomRepository;
use App\Repository\RoomTaskRepository;
use App\Repository\TaskRepository;
use App\Service\Dirtiness;
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
 * Room $room / RoomTask $roomTask depuis {id}.
 */
#[IsGranted('ROLE_USER')]
final class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly RoomTaskRepository $roomTaskRepository,
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
            'groupedTasks' => Dirtiness::groupByStatus($room->getRoomTasks()->toArray()),
            'catalogTasks' => $this->availableCatalogTasks($room),
        ]);
    }

    /** Ajout d'une tâche libre : réutilise l'entrée du catalogue de même libellé, sinon la crée. */
    #[Route('/rooms/{id}/tasks', name: 'app_task_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createTask(int $id, Request $request): Response
    {
        $room = $this->findRoomOrFail($id);

        $token = new CsrfToken('add_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $label = trim((string) $request->request->get('name'));
        $frequencyDays = max(1, (int) $request->request->get('frequencyDays', 7));

        if ($label === '') {
            $this->addFlash('error', 'Le nom de la tâche est obligatoire.');

            return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
        }

        $task = $this->findCatalogTaskByLabel($room, $label);
        if (!$task instanceof Task) {
            $task = new Task();
            $task->setRoomType($room->getRoomType());
            $task->setLabel($label);
            $task->setDefaultFrequencyDays($frequencyDays);
            $this->entityManager->persist($task);
        }

        if ($this->roomHasTask($room, $task)) {
            $this->addFlash('error', 'Cette tâche est déjà présente dans la pièce.');

            return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
        }

        $this->entityManager->persist($this->newRoomTask($room, $task, $frequencyDays));
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Tâche "%s" ajoutée.', $task->getLabel()));

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
        if ($selected === []) {
            $this->addFlash('error', 'Choisis au moins une tâche dans le catalogue.');

            return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
        }

        $created = 0;
        foreach ($selected as $taskId) {
            $task = $this->taskRepository->find((int) $taskId);

            // Uniquement le catalogue du type de la pièce, et pas de doublon.
            if (!$task instanceof Task
                || $task->getRoomType()?->getId() !== $room->getRoomType()?->getId()
                || $this->roomHasTask($room, $task)
            ) {
                continue;
            }

            $this->entityManager->persist($this->newRoomTask($room, $task, $task->getDefaultFrequencyDays()));
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

    #[Route('/room-tasks/{id}/complete', name: 'app_task_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function completeTask(int $id, Request $request): Response
    {
        $roomTask = $this->roomTaskRepository->find($id);
        if (!$roomTask instanceof RoomTask) {
            throw $this->createNotFoundException('Tâche introuvable.');
        }

        $room = $roomTask->getRoom();
        $this->denyUnlessSameFoyer($room);

        $token = new CsrfToken('complete_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $now = new \DateTimeImmutable();
        $previousDoneAt = $roomTask->getLastDoneAt();

        $log = new TaskLog();
        $log->setDoneAt($now);
        $log->setDoneBy($user);
        $roomTask->addLog($log);
        $roomTask->setLastDoneAt($now);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        // Toast « Annuler » (voir templates/app.html.twig) plutôt qu'un simple message.
        $this->addFlash('undo', [
            'id' => $roomTask->getId(),
            'name' => $roomTask->getLabel(),
            'prev' => $previousDoneAt?->format(\DateTimeInterface::ATOM) ?? '',
        ]);

        return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
    }

    /** Annule le dernier « C'est fait » (fenêtre de 15 minutes) : supprime le log et restaure la date précédente. */
    #[Route('/room-tasks/{id}/undo', name: 'app_task_undo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function undoTask(int $id, Request $request): Response
    {
        $roomTask = $this->findRoomTaskOrFail($id);
        $room = $roomTask->getRoom();

        $token = new CsrfToken('undo_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $latest = null;
        foreach ($roomTask->getLogs() as $log) {
            if ($latest === null || $log->getDoneAt() > $latest->getDoneAt()) {
                $latest = $log;
            }
        }

        $limit = (new \DateTimeImmutable())->modify('-15 minutes');
        if ($latest === null || $latest->getDoneAt() < $limit) {
            $this->addFlash('error', "Trop tard pour annuler : modifie la date depuis l'édition de la tâche.");

            return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
        }

        $previous = null;
        $prevRaw = (string) $request->request->get('prev', '');
        if ($prevRaw !== '') {
            try {
                $previous = new \DateTimeImmutable($prevRaw);
            } catch (\Exception) {
                $previous = null;
            }
        }

        $roomTask->getLogs()->removeElement($latest);
        $this->entityManager->remove($latest);
        $roomTask->setLastDoneAt($previous);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('« %s » : action annulée.', $roomTask->getLabel()));

        return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
    }

    #[Route('/room-tasks/{id}/update', name: 'app_room_task_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateTask(int $id, Request $request): Response
    {
        $roomTask = $this->findRoomTaskOrFail($id);
        $room = $roomTask->getRoom();

        $token = new CsrfToken('update_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $lastDoneAt = null;
        $lastDoneAtRaw = trim((string) $request->request->get('lastDoneAt'));
        if ($lastDoneAtRaw !== '') {
            $lastDoneAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $lastDoneAtRaw);
            if ($lastDoneAt === false) {
                $this->addFlash('error', 'Date de dernier nettoyage invalide.');

                return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
            }
        }

        $roomTask->setFrequencyDays(max(1, (int) $request->request->get('frequencyDays', 7)));
        $roomTask->setLastDoneAt($lastDoneAt);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Tâche "%s" mise à jour.', $roomTask->getLabel()));

        return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
    }

    #[Route('/room-tasks/{id}/delete', name: 'app_room_task_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTask(int $id, Request $request): Response
    {
        $roomTask = $this->findRoomTaskOrFail($id);
        $room = $roomTask->getRoom();

        $token = new CsrfToken('delete_task', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name = $roomTask->getLabel();
        $this->entityManager->remove($roomTask);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Tâche "%s" supprimée.', $name));

        return $this->redirectToRoute('app_room_show', ['id' => $room->getId()]);
    }

    private function findRoomTaskOrFail(int $id): RoomTask
    {
        $roomTask = $this->roomTaskRepository->find($id);
        if (!$roomTask instanceof RoomTask) {
            throw $this->createNotFoundException('Tâche introuvable.');
        }

        $this->denyUnlessSameFoyer($roomTask->getRoom());

        return $roomTask;
    }

    /**
     * Tâches du catalogue du type de la pièce, hors celles déjà présentes dans la pièce.
     *
     * @return Task[]
     */
    private function availableCatalogTasks(Room $room): array
    {
        $taken = array_map(
            static fn (RoomTask $roomTask): ?int => $roomTask->getTask()?->getId(),
            $room->getRoomTasks()->toArray(),
        );

        return array_values(array_filter(
            $room->getRoomType()?->getTasks()->toArray() ?? [],
            static fn (Task $task): bool => !in_array($task->getId(), $taken, true),
        ));
    }

    private function findCatalogTaskByLabel(Room $room, string $label): ?Task
    {
        $needle = mb_strtolower($label);

        foreach ($room->getRoomType()?->getTasks() ?? [] as $task) {
            if (mb_strtolower($task->getLabel()) === $needle) {
                return $task;
            }
        }

        return null;
    }

    private function roomHasTask(Room $room, Task $task): bool
    {
        foreach ($room->getRoomTasks() as $roomTask) {
            if ($roomTask->getTask() === $task) {
                return true;
            }
        }

        return false;
    }

    private function newRoomTask(Room $room, Task $task, int $frequencyDays): RoomTask
    {
        $roomTask = new RoomTask();
        $roomTask->setRoom($room);
        $roomTask->setTask($task);
        $roomTask->setFrequencyDays(max(1, $frequencyDays));

        return $roomTask;
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
        // Une pièce est accessible dès que l'utilisateur est membre de son foyer.
        /** @var User $user */
        $user = $this->getUser();

        if (!$room instanceof Room || !$room->getFoyer() || !$user->getFoyers()->contains($room->getFoyer())) {
            throw $this->createNotFoundException('Pièce introuvable.');
        }
    }
}
