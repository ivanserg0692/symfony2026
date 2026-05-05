<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exported file path to news exports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE news_export ADD file_path VARCHAR(1024) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE news_export DROP file_path');
    }
}
