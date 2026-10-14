<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 2/2 — Données : catalogue de base (types de pièce + tâches proposées) et reprise des lignes existantes.
 *
 *   1. insère les types de pièce et le catalogue de tâches (idempotent : ON CONFLICT DO NOTHING) ;
 *   2. rattache les pièces existantes à un type (d'après leur nom, « Autre » par défaut) ;
 *   3. rattache les anciennes tâches au catalogue (entrée créée si le libellé n'existe pas pour le type) ;
 *   4. verrouille le schéma : room.room_type_id et room_task.task_id NOT NULL, suppression de room_task.name.
 *
 * Nécessite Version20261014104500 (schéma).
 */
final class Version20261014104600 extends AbstractMigration
{
    /**
     * Catalogue : type de pièce => [libellé de tâche, fréquence par défaut en jours].
     *
     * @var array<string, array<int, array{0: string, 1: int}>>
     */
    private const CATALOG = [
        'Cuisine' => [
            ['Organiser et essuyer les tiroirs/placards', 60],
            ['Organiser le réfrigérateur', 14],
            ['Nettoyer le congélateur', 90],
            ['Nettoyer la plaque', 7],
            ['Changer les torchons', 7],
            ['Nettoyer les murs', 90],
            ['Détartrer la bouilloire', 30],
            ['Nettoyer le four', 30],
            ['Nettoyer le micro onde', 14],
            ['Nettoyer le réfrigérateur', 30],
            ['Essuyer le comptoir', 1],
            ["Nettoyer l'évier", 3],
            ['Serpillère', 7],
            ['Aspirateur', 3],
            ['Nettoyer les portes de placards', 30],
        ],
        'Salon' => [
            ['Ranger', 1],
            ['Nettoyer les équipements audio video', 30],
            ['Serpillère', 14],
            ['Aspirateur', 4],
            ['Laver les plaids', 30],
            ['Faire la poussière', 7],
            ['Aspirer les coussins', 14],
        ],
        'Salle de bain' => [
            ['Changer les serviettes', 4],
            ['Nettoyer le miroir', 7],
            ['Nettoyer la baignoire', 4],
            ['Nettoyer le lavabo', 3],
            ['Nettoyer les murs', 90],
            ['Laver le pare douche', 14],
            ['Nettoyer les canalisations', 30],
            ['Aspirateur', 7],
            ['Serpillère', 7],
            ['Jeter la poubelle', 3],
            ['Changer la brosse à dents', 90],
        ],
        'WC' => [
            ['Nettoyer les toilettes', 3],
            ['Faire la poussière', 7],
            ['Aspirateur', 7],
            ['Serpillère', 7],
            ['Désinfecter le réducteur', 30],
            ['Désinfecter les toilettes', 7],
        ],
        'Chambre' => [
            ['Changer les draps', 14],
            ['Ranger', 3],
            ['Nettoyer les tables de chevet', 14],
            ['Laver couette et oreillers', 90],
            ['Retourner le matelas', 90],
            ['Organiser et nettoyer le placard', 120],
            ['Aspirateur', 7],
            ['Serpillère', 14],
            ['Nettoyer sous le lit', 30],
        ],
        // Type de repli (pièces personnalisées) : pas de tâches pré-remplies.
        'Autre' => [],
    ];

    public function getDescription(): string
    {
        return 'Données : catalogue room_type / task pré-rempli + reprise des pièces et tâches existantes';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        // --- Garde-fou : une même tâche ne peut pas exister deux fois dans une pièce (UNIQUE room_id+task_id) ---
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM room_task rt
                    GROUP BY rt.room_id, lower(rt.name)
                    HAVING COUNT(*) > 1
                ) THEN
                    RAISE EXCEPTION 'Migration interrompue : tâches en doublon (même nom) dans une même pièce. Fusionner/supprimer les doublons avant de relancer.';
                END IF;
            END $$;
        SQL);

        // --- 1. catalogue ---
        $this->addSql('INSERT INTO room_type (label) VALUES ' . $this->values(array_map(
            fn (string $label): array => [$label],
            array_keys(self::CATALOG)
        )) . ' ON CONFLICT (label) DO NOTHING');

        foreach (self::CATALOG as $roomType => $tasks) {
            if ([] === $tasks) {
                continue;
            }
            $this->addSql(sprintf(
                'INSERT INTO task (room_type_id, label, default_frequency_days)
                 SELECT rt.id, v.label, v.freq
                 FROM (VALUES %s) AS v(label, freq)
                 CROSS JOIN room_type rt
                 WHERE rt.label = %s
                 ON CONFLICT (room_type_id, label) DO NOTHING',
                $this->values($tasks),
                $this->connection->quote($roomType)
            ));
        }

        // --- 2. pièces existantes => type ---
        $this->addSql(<<<'SQL'
            UPDATE room
            SET room_type_id = (
                SELECT rt.id FROM room_type rt
                WHERE rt.label = CASE
                    WHEN lower(room.label) LIKE '%cuisine%'        THEN 'Cuisine'
                    WHEN lower(room.label) LIKE '%salon%'          THEN 'Salon'
                    WHEN lower(room.label) LIKE '%salle de bain%'  THEN 'Salle de bain'
                    WHEN lower(room.label) LIKE '%wc%'
                      OR lower(room.label) LIKE '%toilette%'       THEN 'WC'
                    WHEN lower(room.label) LIKE '%chambre%'        THEN 'Chambre'
                    ELSE 'Autre'
                END
            )
            WHERE room_type_id IS NULL
        SQL);

        // --- 3. anciennes tâches => catalogue ---
        $this->addSql(<<<'SQL'
            INSERT INTO task (room_type_id, label, default_frequency_days)
            SELECT DISTINCT ON (r.room_type_id, lower(rt.name)) r.room_type_id, rt.name, rt.frequency_days
            FROM room_task rt
            JOIN room r ON r.id = rt.room_id
            WHERE rt.task_id IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM task t
                WHERE t.room_type_id = r.room_type_id AND lower(t.label) = lower(rt.name)
            )
            ORDER BY r.room_type_id, lower(rt.name), rt.id
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE room_task rt
            SET task_id = t.id
            FROM room r, task t
            WHERE rt.task_id IS NULL
              AND r.id = rt.room_id
              AND t.room_type_id = r.room_type_id
              AND lower(t.label) = lower(rt.name)
        SQL);

        // --- 4. verrouillage du schéma ---
        $this->addSql('ALTER TABLE room ALTER room_type_id SET NOT NULL');
        $this->addSql('ALTER TABLE room_task ALTER task_id SET NOT NULL');
        $this->addSql('ALTER TABLE room_task DROP name');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        // Retour à l'état transitoire du schéma. Le catalogue est conservé (le supprimer
        // supprimerait en cascade les tâches des pièces) ; il disparaît avec le down du schéma.
        $this->addSql('ALTER TABLE room_task ADD name VARCHAR(120) DEFAULT NULL');
        $this->addSql('UPDATE room_task rt SET name = LEFT(t.label, 120) FROM task t WHERE t.id = rt.task_id');
        $this->addSql('ALTER TABLE room_task ALTER name SET NOT NULL');
        $this->addSql('ALTER TABLE room_task ALTER task_id DROP NOT NULL');
        $this->addSql('ALTER TABLE room ALTER room_type_id DROP NOT NULL');
    }

    /**
     * Construit « ('a', 1), ('b', 2) » avec échappement des chaînes.
     *
     * @param array<int, array<int, string|int>> $rows
     */
    private function values(array $rows): string
    {
        return implode(', ', array_map(
            fn (array $row): string => '(' . implode(', ', array_map(
                fn (string|int $v): string => \is_int($v) ? (string) $v : $this->connection->quote($v),
                $row
            )) . ')',
            $rows
        ));
    }
}
