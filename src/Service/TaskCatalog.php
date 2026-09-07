<?php

namespace App\Service;

/**
 * Catalogue référentiel de tâches suggérées par type de pièce (à la Tody).
 * Contenu statique uniquement — aucune logique de calcul ici.
 */
final class TaskCatalog
{
    /**
     * @return array<int, array{room: string, groups: array<string, array<int, array{name: string, frequencyDays: int}>>}>
     */
    public static function all(): array
    {
        return [
            [
                'room' => 'Cuisine',
                'groups' => [
                    'Basique' => [
                        ['name' => 'Faire la poussière (vite)', 'frequencyDays' => 3],
                        ['name' => 'Faire la poussière (à fond)', 'frequencyDays' => 14],
                        ['name' => 'Balayer', 'frequencyDays' => 2],
                        ['name' => 'Passer la serpillère', 'frequencyDays' => 4],
                        ['name' => "Organiser et essuyer les tiroirs", 'frequencyDays' => 21],
                        ['name' => 'Organiser le réfrigérateur', 'frequencyDays' => 14],
                        ['name' => 'Nettoyer le congélateur', 'frequencyDays' => 30],
                        ['name' => 'Nettoyer les plaques de cuisson', 'frequencyDays' => 7],
                        ['name' => 'Nettoyer la hotte', 'frequencyDays' => 30],
                        ['name' => 'Vider la poubelle', 'frequencyDays' => 1],
                        ['name' => 'Changer les torchons', 'frequencyDays' => 6],
                        ['name' => 'Aiguiser les couteaux', 'frequencyDays' => 60],
                        ['name' => 'Nettoyer les murs', 'frequencyDays' => 90],
                    ],
                    'Spéciale' => [
                        ['name' => 'Mettre la table', 'frequencyDays' => 1],
                        ['name' => 'Cuisiner le dîner', 'frequencyDays' => 1],
                        ['name' => 'Faire la vaisselle', 'frequencyDays' => 1],
                        ['name' => "Nettoyer l'acier inoxydable", 'frequencyDays' => 14],
                        ['name' => 'Lave-vaisselle | Sel', 'frequencyDays' => 30],
                        ['name' => 'Lave-vaisselle | Produit de rinçage', 'frequencyDays' => 30],
                        ['name' => 'Nettoyer le grille-pain', 'frequencyDays' => 21],
                        ['name' => 'Détartrer la cafetière', 'frequencyDays' => 30],
                        ['name' => 'Organiser le garde-manger', 'frequencyDays' => 60],
                        ['name' => 'Huiler les planches à découper', 'frequencyDays' => 30],
                        ['name' => 'Huiler les plateaux de table', 'frequencyDays' => 60],
                    ],
                ],
            ],
            [
                'room' => 'Salle de bain',
                'groups' => [
                    'Basique' => [
                        ['name' => 'Nettoyer la douche / baignoire', 'frequencyDays' => 4],
                        ['name' => 'Nettoyer les toilettes', 'frequencyDays' => 3],
                        ['name' => 'Nettoyer le lavabo', 'frequencyDays' => 3],
                        ['name' => 'Nettoyer le miroir', 'frequencyDays' => 7],
                        ['name' => 'Laver le rideau de douche', 'frequencyDays' => 30],
                        ['name' => 'Changer les serviettes', 'frequencyDays' => 4],
                        ['name' => 'Balayer / laver le sol', 'frequencyDays' => 4],
                        ['name' => 'Vider la poubelle', 'frequencyDays' => 3],
                    ],
                    'Spéciale' => [
                        ['name' => 'Détartrer la robinetterie', 'frequencyDays' => 21],
                        ['name' => 'Nettoyer les joints', 'frequencyDays' => 30],
                        ['name' => 'Ranger l\'armoire à pharmacie', 'frequencyDays' => 90],
                        ['name' => 'Laver les tapis de bain', 'frequencyDays' => 14],
                    ],
                ],
            ],
            [
                'room' => 'Chambre',
                'groups' => [
                    'Basique' => [
                        ['name' => 'Faire le lit', 'frequencyDays' => 1],
                        ['name' => 'Changer les draps', 'frequencyDays' => 14],
                        ['name' => 'Faire la poussière', 'frequencyDays' => 7],
                        ['name' => 'Passer l\'aspirateur', 'frequencyDays' => 7],
                        ['name' => 'Ranger les vêtements', 'frequencyDays' => 3],
                        ['name' => 'Aérer la pièce', 'frequencyDays' => 3],
                    ],
                    'Spéciale' => [
                        ['name' => 'Retourner / aérer le matelas', 'frequencyDays' => 90],
                        ['name' => 'Laver les rideaux', 'frequencyDays' => 60],
                        ['name' => 'Nettoyer sous le lit', 'frequencyDays' => 30],
                        ['name' => 'Trier la penderie', 'frequencyDays' => 120],
                    ],
                ],
            ],
            [
                'room' => 'Salon',
                'groups' => [
                    'Basique' => [
                        ['name' => 'Passer l\'aspirateur', 'frequencyDays' => 4],
                        ['name' => 'Faire la poussière', 'frequencyDays' => 5],
                        ['name' => 'Ranger les coussins / plaids', 'frequencyDays' => 1],
                        ['name' => 'Nettoyer la table basse', 'frequencyDays' => 3],
                        ['name' => 'Nettoyer les vitres', 'frequencyDays' => 21],
                    ],
                    'Spéciale' => [
                        ['name' => 'Nettoyer le canapé en profondeur', 'frequencyDays' => 60],
                        ['name' => 'Dépoussiérer la télé / électronique', 'frequencyDays' => 14],
                        ['name' => 'Laver les rideaux', 'frequencyDays' => 60],
                        ['name' => 'Nettoyer les luminaires', 'frequencyDays' => 45],
                    ],
                ],
            ],
            [
                'room' => 'Général',
                'groups' => [
                    'Basique' => [
                        ['name' => 'Sortir les poubelles', 'frequencyDays' => 3],
                        ['name' => 'Passer un coup de balai général', 'frequencyDays' => 4],
                        ['name' => 'Nettoyer les interrupteurs / poignées', 'frequencyDays' => 7],
                        ['name' => 'Arroser les plantes', 'frequencyDays' => 4],
                    ],
                    'Spéciale' => [
                        ['name' => 'Nettoyer les vitres', 'frequencyDays' => 30],
                        ['name' => 'Vérifier les détecteurs de fumée', 'frequencyDays' => 180],
                    ],
                ],
            ],
            [
                'room' => 'Entrée',
                'groups' => [
                    'Basique' => [
                        ['name' => 'Laver l\'entrée / le paillasson', 'frequencyDays' => 7],
                        ['name' => 'Ranger les chaussures', 'frequencyDays' => 4],
                    ],
                    'Spéciale' => [
                        ['name' => 'Nettoyer le miroir', 'frequencyDays' => 14],
                    ],
                ],
            ],
        ];
    }
}
