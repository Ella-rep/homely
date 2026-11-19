<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Palette « Iris » : remplace les anciennes couleurs par défaut des pièces par les nouvelles
 * (voir App\Service\RoomCatalog). Seules les pièces qui ont encore la couleur par défaut de leur
 * type sont mises à jour : une couleur choisie à la main par l'utilisateur est conservée.
 */
final class Version20261119150000 extends AbstractMigration
{
    /** type de pièce (minuscules) => [ancienne couleur, nouvelle couleur] */
    private const MAP = [
        'cuisine' => ['#5FA875', '#E8743B'],
        'salon' => ['#6FA0D8', '#5B7CFA'],
        'salle de bain' => ['#5FA0A0', '#19A7B5'],
        'chambre' => ['#6A7FC7', '#C86BD8'],
        'wc' => ['#8AA9B8', '#3DB37A'],
        'autre' => ['#8AA9B8', '#5B7CFA'],
    ];

    public function getDescription(): string
    {
        return 'room.color : anciennes couleurs par défaut => palette Iris (couleurs personnalisées conservées)';
    }

    public function up(Schema $schema): void
    {
        $this->assertPostgres();
        foreach (self::MAP as $type => [$old, $new]) {
            $this->recolor($type, $old, $new);
        }
    }

    public function down(Schema $schema): void
    {
        $this->assertPostgres();
        foreach (self::MAP as $type => [$old, $new]) {
            $this->recolor($type, $new, $old);
        }
    }

    private function recolor(string $type, string $from, string $to): void
    {
        $this->addSql(
            'UPDATE room SET color = :to WHERE UPPER(color) = UPPER(:from) AND room_type_id IN '
            . '(SELECT id FROM room_type WHERE LOWER(label) = :type)',
            ['to' => $to, 'from' => $from, 'type' => $type]
        );
    }

    private function assertPostgres(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );
    }
}
