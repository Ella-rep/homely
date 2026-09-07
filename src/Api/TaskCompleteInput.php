<?php

namespace App\Api;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de requête (facultatif) pour POST /api/tasks/{id}/complete.
 */
final class TaskCompleteInput
{
    #[Groups(['task:write'])]
    #[Assert\Length(max: 80)]
    public ?string $doneBy = null;
}
