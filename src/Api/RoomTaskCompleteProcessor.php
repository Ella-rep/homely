<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RoomTask;
use App\Entity\TaskLog;
use App\Entity\User;
use App\Repository\RoomTaskRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoomTask
    {
        $roomTask = $this->roomTaskRepository->find($uriVariables['id'] ?? null);

        if (!$roomTask instanceof RoomTask) {
            throw new NotFoundHttpException('Tâche introuvable.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        $now = new DateTimeImmutable();

        $log = new TaskLog();
        $log->setDoneAt($now);
        $log->setDoneBy($user); // toujours l'utilisateur authentifié, jamais le corps de requête

        $roomTask->addLog($log);
        $roomTask->setLastDoneAt($now);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $roomTask;
    }
}
