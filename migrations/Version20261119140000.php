<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * task_log.done_by : texte libre (pseudo, VARCHAR 80) => référence vers "user" (id).
 * Reprise : le pseudo est rapproché d'un membre du foyer de la pièce ; si aucun ou plusieurs
 * membres correspondent (ex. « Démo »), done_by reste NULL. Suppression d'un user => done_by NULL.
 */
final class Version20261119140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'task_log.done_by : VARCHAR(80) => FK user.id (ON DELETE SET NULL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        $this->addSql('ALTER TABLE task_log ADD done_by_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE task_log tl
            SET done_by_id = (
                SELECT MIN(u.id)
                FROM room_task rt
                JOIN room r ON r.id = rt.room_id
                JOIN user_foyer uf ON uf.foyer_id = r.foyer_id
                JOIN "user" u ON u.id = uf.user_id
                WHERE rt.id = tl.room_task_id AND u.pseudo = tl.done_by
                HAVING COUNT(*) = 1
            )
            WHERE tl.done_by IS NOT NULL
        SQL);
        $this->addSql('ALTER TABLE task_log DROP done_by');
        $this->addSql('ALTER TABLE task_log RENAME COLUMN done_by_id TO done_by');
        $this->addSql('CREATE INDEX IDX_TASKLOG_DONE_BY ON task_log (done_by)');
        $this->addSql('ALTER TABLE task_log ADD CONSTRAINT FK_TASKLOG_DONE_BY FOREIGN KEY (done_by) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        $this->addSql('ALTER TABLE task_log DROP CONSTRAINT FK_TASKLOG_DONE_BY');
        $this->addSql('DROP INDEX IDX_TASKLOG_DONE_BY');
        $this->addSql('ALTER TABLE task_log ADD done_by_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('UPDATE task_log tl SET done_by_name = LEFT(u.pseudo, 80) FROM "user" u WHERE u.id = tl.done_by');
        $this->addSql('ALTER TABLE task_log DROP done_by');
        $this->addSql('ALTER TABLE task_log RENAME COLUMN done_by_name TO done_by');
    }
}
