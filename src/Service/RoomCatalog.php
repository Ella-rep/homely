<?php

namespace App\Service;

/**
 * Couleur d'affichage suggérée par type de pièce (le catalogue lui-même vit en base :
 * tables room_type / task). Purement cosmétique.
 */
final class RoomCatalog
{
    private const DEFAULT_COLOR = '#8AA9B8';

    private const COLORS = [
        'cuisine' => '#5FA875',
        'salon' => '#6FA0D8',
        'salle de bain' => '#5FA0A0',
        'wc' => '#8AA9B8',
        'chambre' => '#6A7FC7',
    ];

    public static function colorFor(string $roomTypeLabel): string
    {
        return self::COLORS[mb_strtolower(trim($roomTypeLabel))] ?? self::DEFAULT_COLOR;
    }
}
