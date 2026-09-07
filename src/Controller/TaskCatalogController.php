<?php

namespace App\Controller;

use App\Service\TaskCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert le catalogue référentiel de tâches (lecture seule, données statiques).
 * Volontairement en dehors d'API Platform : ce n'est pas une ressource métier,
 * juste du contenu de référence à afficher côté front.
 */
final class TaskCatalogController
{
    #[Route('/api/task-catalog', name: 'task_catalog', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(TaskCatalog::all());
    }
}
