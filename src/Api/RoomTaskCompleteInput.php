<?php

namespace App\Api;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de requête (facultatif) pour POST /api/room_tasks/{id}/complete.
 */
final class RoomTaskCompleteInput
{
    #[Groups(['room_task:write'])]
    #[Assert\Length(max: 80)]
    public ?string $doneBy = null;
}
