<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create subscriptions and campaigns tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscriptions (
            id UUID NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_user_entity ON subscriptions (user_id, entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_entity_status ON subscriptions (entity_type, entity_id, status)');
        $this->addSql('CREATE INDEX idx_user_status ON subscriptions (user_id, status)');

        $this->addSql('COMMENT ON COLUMN subscriptions.id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE TABLE campaigns (
            id UUID NOT NULL,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            audience_criteria JSON NOT NULL,
            editorial_id VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_status_scheduled ON campaigns (status, scheduled_at)');

        $this->addSql('COMMENT ON COLUMN campaigns.id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE TABLE messenger_messages (
            id BIGSERIAL NOT NULL,
            body TEXT NOT NULL,
            headers TEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE campaigns');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
