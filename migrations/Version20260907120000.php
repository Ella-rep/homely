<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial : foyer, user, room, task, task_log.
 * RG4 : task_log est en ON DELETE CASCADE depuis task (pas de soft delete).
 * Toute pièce appartient à un foyer ; tout utilisateur appartient à un foyer.
 */
final class Version20260907120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial Homely : foyer, user, room, task, task_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE foyer (
            id SERIAL NOT NULL,
            name VARCHAR(80) NOT NULL,
            invite_code VARCHAR(8) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FOYER_INVITE_CODE ON foyer (invite_code)');

        $this->addSql('CREATE TABLE "user" (
            id SERIAL NOT NULL,
            foyer_id INT NOT NULL,
            email VARCHAR(180) NOT NULL,
            pseudo VARCHAR(60) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_USER_FOYER ON "user" (foyer_id)');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_USER_FOYER FOREIGN KEY (foyer_id) REFERENCES foyer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE room (
            id SERIAL NOT NULL,
            foyer_id INT NOT NULL,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(7) NOT NULL,
            position INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_ROOM_FOYER ON room (foyer_id)');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_ROOM_FOYER FOREIGN KEY (foyer_id) REFERENCES foyer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE task (
            id SERIAL NOT NULL,
            room_id INT NOT NULL,
            name VARCHAR(120) NOT NULL,
            frequency_days INT NOT NULL,
            last_done_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_TASK_ROOM ON task (room_id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_TASK_ROOM FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE task_log (
            id SERIAL NOT NULL,
            task_id INT NOT NULL,
            done_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            done_by VARCHAR(80) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_TASKLOG_TASK ON task_log (task_id)');
        $this->addSql('ALTER TABLE task_log ADD CONSTRAINT FK_TASKLOG_TASK FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_log');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE foyer');
    }
}
