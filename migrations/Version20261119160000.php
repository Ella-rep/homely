<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les couleurs de pièce sont désormais limitées à la palette du site (App\Service\RoomCatalog::PALETTE,
 * plus de couleur libre). Toute pièce dont la couleur n'est pas dans la palette est ramenée à la
 * couleur de la palette la plus proche ; une couleur déjà dans la palette est seulement normalisée
 * en majuscules. La palette est volontairement recopiée ici : une migration ne doit pas dépendre du code applicatif.
 */
final class Version20261119160000 extends AbstractMigration
{
    private const PALETTE = [
        '#E8743B', // Abricot
        '#5B7CFA', // Cobalt
        '#19A7B5', // Turquoise
        '#C86BD8', // Orchidée
        '#3DB37A', // Menthe
        '#E6A91F', // Safran
        '#E4577F', // Rose
        '#8E78F0', // Lilas
    ];

    public function getDescription(): string
    {
        return 'room.color : limitée à la palette du site (couleurs libres ramenées à la plus proche)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        /** @var array<int, array{id: int|string, color: string}> $rooms */
        $rooms = $this->connection->fetchAllAssociative('SELECT id, color FROM room');

        foreach ($rooms as $room) {
            $current = strtoupper(trim((string) $room['color']));
            $target = in_array($current, self::PALETTE, true) ? $current : $this->nearest($current);

            if ($target !== (string) $room['color']) {
                $this->addSql('UPDATE room SET color = :color WHERE id = :id', [
                    'color' => $target,
                    'id' => (int) $room['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les couleurs libres d\'origine ne sont pas conservées.');
    }

    /** Couleur de la palette la plus proche (distance euclidienne en sRGB) ; Cobalt si la valeur est illisible. */
    private function nearest(string $hex): string
    {
        if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            return self::PALETTE[1];
        }

        $rgb = $this->rgb($hex);
        $best = self::PALETTE[1];
        $bestDistance = PHP_INT_MAX;

        foreach (self::PALETTE as $candidate) {
            $c = $this->rgb($candidate);
            $distance = ($rgb[0] - $c[0]) ** 2 + ($rgb[1] - $c[1]) ** 2 + ($rgb[2] - $c[2]) ** 2;
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $best;
    }

    /** @return int[] */
    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }
}
