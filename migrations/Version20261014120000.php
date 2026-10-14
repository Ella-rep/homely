<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Plusieurs foyers par utilisateur : "user.foyer_id" devient la table de liaison "user_foyer",
 * et "foyer.owner_id" mémorise le créateur (seul autorisé à supprimer le foyer).
 * Reprise : chaque lien existant est conservé ; le créateur d'un foyer existant est son plus ancien membre.
 */
final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Multi-foyers : user_foyer (many-to-many) + foyer.owner_id';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Cette migration cible PostgreSQL uniquement.'
        );

        $this->addSql('CREATE TABLE user_foyer (user_id INT NOT NULL, foyer_id INT NOT NULL, PRIMARY KEY(user_id, foyer_id))');
        $this->addSql('CREATE INDEX IDX_USER_FOYER_USER ON user_foyer (user_id)');
        $this->addSql('CREATE INDEX IDX_USER_FOYER_FOYER ON user_foyer (foyer_id)');
        $this->addSql('ALTER TABLE user_foyer ADD CONSTRAINT FK_USER_FOYER_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_foyer ADD CONSTRAINT FK_USER_FOYER_FOYER FOREIGN KEY (foyer_id) REFERENCES foyer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('INSERT INTO user_foyer (user_id, foyer_id) SELECT id, foyer_id FROM "user" WHERE foyer_id IS NOT NULL');

        $this->addSql('ALTER TABLE foyer ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE foyer ADD CONSTRAINT FK_FOYER_OWNER FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FOYER_OWNER ON foyer (owner_id)');
        $this->addSql('UPDATE foyer f SET owner_id = (SELECT u.id FROM "user" u WHERE u.foyer_id = f.id ORDER BY u.created_at, u.id LIMIT 1)');

        // Supprime aussi la FK et l'index associés.
        $this->addSql('ALTER TABLE "user" DROP COLUMN foyer_id');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Un utilisateur peut avoir plusieurs foyers : retour arrière impossible sans perte de données.');
    }
}
