<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 1/2 — Schéma : refonte « type de pièce » (rooms.sql). Aucune donnée de référence ici
 * (voir Version20261014104600 pour le catalogue et la reprise des données).
 *
 * Modèle cible :
 *   room_type (catalogue des types de pièce)
 *   task      (catalogue des tâches proposées par type de pièce, fréquence par défaut)
 *   room      (pièce d'un foyer, rattachée à un room_type)
 *   room_task (tâche choisie dans une pièce : ex-« task », garde son id => task_log inchangé)
 *   task_log  (historique, rattaché à room_task)
 *
 * Noms validés / alignés par rapport à rooms.sql :
 *   - foyer / user : inchangés (name, invite_code, ...) ; les versions de rooms.sql divergeaient des entités.
 *   - room.name -> room.label ; unicité par foyer (et non globale).
 *   - room_task : id surrogate + UNIQUE (room_id, task_id) ; task_log.room_task_id référence cet id
 *     (une FK simple ne peut pas viser une PK composite).
 *   - ids en SERIAL/INT comme le reste du schéma (pas de BIGINT => ids restent des int côté Doctrine).
 *   - rooms.sql insérait des room/room_task sans foyer : supprimé (instances créées par l'utilisateur).
 *
 * État transitoire à l'issue de cette migration : room.room_type_id et room_task.task_id sont NULLables
 * et room_task.name existe encore, le temps que la migration suivante reprenne les lignes existantes.
 * Les deux migrations sont à exécuter ensemble.
 */
final class Version20261014104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma : room_type + catalogue task ; ex-task => room_task ; room.name => room.label ; task_log => room_task';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        // --- room_type ---
        $this->addSql('CREATE TABLE room_type (
            id SERIAL NOT NULL,
            label VARCHAR(255) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ROOM_TYPE_LABEL ON room_type (label)');

        // --- room : room_type_id (NOT NULL posé par la migration de données) + name => label ---
        $this->addSql('ALTER TABLE room ADD room_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_ROOM_ROOM_TYPE FOREIGN KEY (room_type_id) REFERENCES room_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_ROOM_ROOM_TYPE ON room (room_type_id)');

        $this->addSql('ALTER TABLE room RENAME COLUMN name TO label');
        $this->addSql('ALTER TABLE room ALTER label TYPE VARCHAR(255)');
        // Désambiguïsation avant l'unicité (foyer_id, label).
        $this->addSql(<<<'SQL'
            UPDATE room r
            SET label = r.label || ' (' || d.rn || ')'
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY foyer_id, label ORDER BY id) AS rn
                FROM room
            ) d
            WHERE d.id = r.id AND d.rn > 1
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ROOM_FOYER_LABEL ON room (foyer_id, label)');

        // --- ancienne task => room_task (id conservé, task_log reste valide) ---
        $this->addSql('ALTER TABLE task RENAME TO room_task');
        $this->addSql('ALTER SEQUENCE task_id_seq RENAME TO room_task_id_seq');
        $this->addSql('ALTER INDEX task_pkey RENAME TO room_task_pkey');
        $this->addSql('ALTER INDEX IDX_TASK_ROOM RENAME TO IDX_ROOM_TASK_ROOM');
        $this->addSql('ALTER TABLE room_task RENAME CONSTRAINT FK_TASK_ROOM TO FK_ROOM_TASK_ROOM');

        $this->addSql('ALTER TABLE task_log RENAME COLUMN task_id TO room_task_id');
        $this->addSql('ALTER INDEX IDX_TASKLOG_TASK RENAME TO IDX_TASKLOG_ROOM_TASK');
        $this->addSql('ALTER TABLE task_log RENAME CONSTRAINT FK_TASKLOG_TASK TO FK_TASKLOG_ROOM_TASK');

        // --- nouveau catalogue task ---
        $this->addSql('CREATE TABLE task (
            id SERIAL NOT NULL,
            room_type_id INT NOT NULL,
            label VARCHAR(255) NOT NULL,
            default_frequency_days INT NOT NULL DEFAULT 7,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TASK_ROOM_TYPE_LABEL ON task (room_type_id, label)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_TASK_ROOM_TYPE FOREIGN KEY (room_type_id) REFERENCES room_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT CHK_TASK_FREQUENCY CHECK (default_frequency_days > 0)');

        // --- room_task -> catalogue (task_id NOT NULL et suppression de name : migration de données) ---
        $this->addSql('ALTER TABLE room_task ADD task_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE room_task ADD CONSTRAINT FK_ROOM_TASK_TASK FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_ROOM_TASK_TASK ON room_task (task_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ROOM_TASK_ROOM_TASK ON room_task (room_id, task_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        // room_task => task (la colonne name existe encore dans l'état transitoire)
        $this->addSql('ALTER TABLE room_task DROP CONSTRAINT FK_ROOM_TASK_TASK');
        $this->addSql('DROP INDEX UNIQ_ROOM_TASK_ROOM_TASK');
        $this->addSql('DROP INDEX IDX_ROOM_TASK_TASK');
        $this->addSql('ALTER TABLE room_task DROP task_id');
        $this->addSql('DROP TABLE task');

        $this->addSql('ALTER TABLE room_task RENAME TO task');
        $this->addSql('ALTER SEQUENCE room_task_id_seq RENAME TO task_id_seq');
        $this->addSql('ALTER INDEX room_task_pkey RENAME TO task_pkey');
        $this->addSql('ALTER INDEX IDX_ROOM_TASK_ROOM RENAME TO IDX_TASK_ROOM');
        $this->addSql('ALTER TABLE task RENAME CONSTRAINT FK_ROOM_TASK_ROOM TO FK_TASK_ROOM');

        $this->addSql('ALTER TABLE task_log RENAME COLUMN room_task_id TO task_id');
        $this->addSql('ALTER INDEX IDX_TASKLOG_ROOM_TASK RENAME TO IDX_TASKLOG_TASK');
        $this->addSql('ALTER TABLE task_log RENAME CONSTRAINT FK_TASKLOG_ROOM_TASK TO FK_TASKLOG_TASK');

        // room
        $this->addSql('DROP INDEX UNIQ_ROOM_FOYER_LABEL');
        $this->addSql('ALTER TABLE room ALTER label TYPE VARCHAR(80) USING LEFT(label, 80)');
        $this->addSql('ALTER TABLE room RENAME COLUMN label TO name');
        $this->addSql('ALTER TABLE room DROP CONSTRAINT FK_ROOM_ROOM_TYPE');
        $this->addSql('DROP INDEX IDX_ROOM_ROOM_TYPE');
        $this->addSql('ALTER TABLE room DROP room_type_id');
        $this->addSql('DROP TABLE room_type');
    }
}
