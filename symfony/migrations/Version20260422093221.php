<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422093221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_user_groups (user_id INT NOT NULL, user_groups_id INT NOT NULL, PRIMARY KEY (user_id, user_groups_id))');
        $this->addSql('CREATE INDEX IDX_3C24EBD8A76ED395 ON user_user_groups (user_id)');
        $this->addSql('CREATE INDEX IDX_3C24EBD8FD7B02B ON user_user_groups (user_groups_id)');
        $this->addSql('ALTER TABLE user_user_groups ADD CONSTRAINT FK_3C24EBD8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_user_groups ADD CONSTRAINT FK_3C24EBD8FD7B02B FOREIGN KEY (user_groups_id) REFERENCES user_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_groups ADD is_admin BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_user_groups DROP CONSTRAINT FK_3C24EBD8A76ED395');
        $this->addSql('ALTER TABLE user_user_groups DROP CONSTRAINT FK_3C24EBD8FD7B02B');
        $this->addSql('DROP TABLE user_user_groups');
        $this->addSql('ALTER TABLE user_groups DROP is_admin');
    }
}
