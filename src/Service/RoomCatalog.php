<?php

namespace App\Service;

/**
 * Couleurs de pièce : l'utilisateur choisit uniquement parmi PALETTE (pas de couleur libre).
 * Le CSS en dérive pastille, fond de tuile et texte (oklch relatif, voir style.css).
 * Le catalogue de types lui-même vit en base (tables room_type / task).
 */
final class RoomCatalog
{
    /** Libellé => hex (majuscules, forme canonique stockée en base). */
    public const PALETTE = [
        'Abricot' => '#E8743B',
        'Cobalt' => '#5B7CFA',
        'Turquoise' => '#19A7B5',
        'Orchidée' => '#C86BD8',
        'Menthe' => '#3DB37A',
        'Safran' => '#E6A91F',
        'Rose' => '#E4577F',
        'Lilas' => '#8E78F0',
    ];

    public const DEFAULT_COLOR = '#5B7CFA';

    /** Couleur suggérée par type de pièce (toujours une couleur de PALETTE). */
    private const COLORS = [
        'cuisine' => '#E8743B',
        'salon' => '#5B7CFA',
        'salle de bain' => '#19A7B5',
        'chambre' => '#C86BD8',
        'wc' => '#3DB37A',
        'bureau' => '#E6A91F',
    ];

    public static function colorFor(string $roomTypeLabel): string
    {
        return self::COLORS[mb_strtolower(trim($roomTypeLabel))] ?? self::DEFAULT_COLOR;
    }

    /** @return string[] hex autorisés (contrainte de validation de Room::$color). */
    public static function allowedColors(): array
    {
        return array_values(self::PALETTE);
    }

    /** Forme canonique (majuscules) d'une couleur de la palette, ou null si elle n'en fait pas partie. */
    public static function canonical(string $color): ?string
    {
        $color = strtoupper(trim($color));

        return in_array($color, self::PALETTE, true) ? $color : null;
    }
}
