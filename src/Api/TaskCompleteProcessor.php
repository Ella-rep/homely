<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Task;
use App\Entity\TaskLog;
use App\Repository\TaskRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Traite POST /api/tasks/{id}/complete : crée un TaskLog et met à jour
 * Task::lastDoneAt. C'est le SEUL endroit qui écrit "une tâche a été faite" —
 * le front se contente d'appeler cette route, sans calculer quoi que ce soit.
 *
 * @implements ProcessorInterface<TaskCompleteInput, Task>
 */
final class TaskCompleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Task
    {
        $task = $this->taskRepository->find($uriVariables['id'] ?? null);

        if (!$task instanceof Task) {
            throw new NotFoundHttpException('Tâche introuvable.');
        }

        $now = new DateTimeImmutable();

        $log = new TaskLog();
        $log->setDoneAt($now);
        $log->setDoneBy($data instanceof TaskCompleteInput ? $data->doneBy : null);

        $task->addLog($log);
        $task->setLastDoneAt($now);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $task;
    }
}
