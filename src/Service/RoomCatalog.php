<?php

namespace App\Service;

/**
 * Catalogue référentiel de types de pièces suggérés (à la Tody), pour préremplir
 * le formulaire de création de pièce. Données statiques uniquement.
 */
final class RoomCatalog
{
    /**
     * @return array<int, array{name: string, color: string, exterior: bool}>
     */
    public static function all(): array
    {
        return self::items();
    }

    /**
     * Catalogue filtré : retire les suggestions dont le nom correspond déjà
     * à une pièce existante du foyer (comparaison insensible à la casse).
     *
     * @param array<int, string> $existingNames
     * @return array<int, array{name: string, color: string, exterior: bool}>
     */
    public static function excludingNames(array $existingNames): array
    {
        $existing = array_map(static fn (string $name): string => mb_strtolower(trim($name)), $existingNames);

        return array_values(array_filter(
            self::items(),
            static fn (array $item): bool => !in_array(mb_strtolower($item['name']), $existing, true)
        ));
    }

    /**
     * @return array<int, array{name: string, color: string, exterior: bool}>
     */
    private static function items(): array
    {
        return [
            ['name' => 'Cuisine', 'color' => '#5FA875', 'exterior' => false],
            ['name' => 'Salon', 'color' => '#6FA0D8', 'exterior' => false],
            ['name' => 'Salle à manger', 'color' => '#C98A5A', 'exterior' => false],
            ['name' => 'Chambre', 'color' => '#6A7FC7', 'exterior' => false],
            ['name' => 'Salle de bain', 'color' => '#5FA0A0', 'exterior' => false],
            ['name' => 'Toilettes', 'color' => '#8AA9B8', 'exterior' => false],
            ['name' => 'Bureau', 'color' => '#9C8AC9', 'exterior' => false],
            ['name' => 'Entrée', 'color' => '#B08A6A', 'exterior' => false],
            ['name' => 'Buanderie', 'color' => '#7FB0C2', 'exterior' => false],
            ['name' => 'Jardin', 'color' => '#5C9E5C', 'exterior' => true],
            ['name' => 'Garage', 'color' => '#8A8F98', 'exterior' => true],
            ['name' => 'Terrasse', 'color' => '#C9A85A', 'exterior' => true],
            ['name' => 'Balcon', 'color' => '#7FB98F', 'exterior' => true],
        ];
    }
}
