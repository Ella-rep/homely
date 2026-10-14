<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RoomTask;
use App\Entity\TaskLog;
use App\Repository\RoomTaskRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Traite POST /api/room_tasks/{id}/complete : crée un TaskLog et met à jour
 * RoomTask::lastDoneAt. C'est le SEUL endroit API qui écrit "une tâche a été faite" —
 * le front se contente d'appeler cette route, sans calculer quoi que ce soit.
 *
 * @implements ProcessorInterface<RoomTaskCompleteInput, RoomTask>
 */
final class RoomTaskCompleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RoomTaskRepository $roomTaskRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoomTask
    {
        $roomTask = $this->roomTaskRepository->find($uriVariables['id'] ?? null);

        if (!$roomTask instanceof RoomTask) {
            throw new NotFoundHttpException('Tâche introuvable.');
        }

        $now = new DateTimeImmutable();

        $log = new TaskLog();
        $log->setDoneAt($now);
        $log->setDoneBy($data instanceof RoomTaskCompleteInput ? $data->doneBy : null);

        $roomTask->addLog($log);
        $roomTask->setLastDoneAt($now);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $roomTask;
    }
}
