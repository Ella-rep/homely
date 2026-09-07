<?php

namespace App\Controller;

use App\Service\RoomCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert le catalogue référentiel de types de pièces (lecture seule, données statiques).
 */
final class RoomCatalogController
{
    #[Route('/api/room-catalog', name: 'room_catalog', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(RoomCatalog::all());
    }
}
