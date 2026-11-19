<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tâches ponctuelles / non régulières (ex : jeter les encombrants, trier la bibliothèque).
 * Rattachées au foyer, sans pièce, hors catalogue room_type/task.
 *   - repeat_every_days NULL => tâche unique : clôturée via done_at
 *   - repeat_every_days renseigné => « de temps en temps » : reste active, due_date repoussée
 * special_task_log : historique des réalisations (supprimé en cascade avec la tâche).
 */
final class Version20261119170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de special_task et special_task_log (tâches ponctuelles du foyer)';
    }

    public function up(Schema $schema): void
    {
        $this->assertPostgres();

        $this->addSql('CREATE TABLE special_task (
            id SERIAL NOT NULL,
            foyer_id INT NOT NULL,
            label VARCHAR(255) NOT NULL,
            notes TEXT DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            repeat_every_days INT DEFAULT NULL,
            done_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_SPECIAL_TASK_FOYER ON special_task (foyer_id)');
        $this->addSql('ALTER TABLE special_task ADD CONSTRAINT FK_SPECIAL_TASK_FOYER FOREIGN KEY (foyer_id) REFERENCES foyer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE special_task ADD CONSTRAINT CHK_SPECIAL_TASK_REPEAT CHECK (repeat_every_days IS NULL OR repeat_every_days > 0)');

        $this->addSql('CREATE TABLE special_task_log (
            id SERIAL NOT NULL,
            special_task_id INT NOT NULL,
            done_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            done_by INT DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_SPECIAL_TASK_LOG_TASK ON special_task_log (special_task_id)');
        $this->addSql('CREATE INDEX IDX_SPECIAL_TASK_LOG_DONE_BY ON special_task_log (done_by)');
        $this->addSql('ALTER TABLE special_task_log ADD CONSTRAINT FK_SPECIAL_TASK_LOG_TASK FOREIGN KEY (special_task_id) REFERENCES special_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE special_task_log ADD CONSTRAINT FK_SPECIAL_TASK_LOG_DONE_BY FOREIGN KEY (done_by) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->assertPostgres();

        $this->addSql('DROP TABLE special_task_log');
        $this->addSql('DROP TABLE special_task');
    }

    private function assertPostgres(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );
    }
}
