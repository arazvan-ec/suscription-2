---
name: symfony-doctrine
description: Doctrine ORM patterns for entities, repositories, and migrations
triggers:
  - entity
  - repository
  - migration
  - Doctrine
  - database
  - schema
---

# Symfony Doctrine Skill

## Purpose

Guide Claude to implement Doctrine ORM entities, repositories, and migrations
following El Confidencial's domain-driven conventions.

## Entity Pattern

Entities live in `src/Domain/` with rich behavior:

```php
namespace App\Domain\Entity;

use App\Domain\Event\SubscriptionCreatedEvent;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(columns: ['entity_type', 'entity_id', 'status'], name: 'idx_entity_status')]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_user_status')]
#[ORM\UniqueConstraint(columns: ['user_id', 'entity_type', 'entity_id'])]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $userId;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 50)]
    private string $entityType;

    #[ORM\Column(length: 255)]
    private string $entityId;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private string $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        string $userId,
        string $email,
        string $entityType,
        string $entityId,
    ) {
        $this->id = Uuid::v7();
        $this->userId = $userId;
        $this->email = $email;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->status = 'active';
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(
        string $userId,
        string $email,
        string $entityType,
        string $entityId,
    ): self {
        return new self($userId, $email, $entityType, $entityId);
    }

    public function deactivate(): void
    {
        $this->status = 'inactive';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reactivate(): void
    {
        $this->status = 'active';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // Getters...
    public function getId(): Uuid { return $this->id; }
    public function getUserId(): string { return $this->userId; }
    public function getEmail(): string { return $this->email; }
    public function getEntityType(): string { return $this->entityType; }
    public function getEntityId(): string { return $this->entityId; }
    public function getStatus(): string { return $this->status; }
}
```

## Repository Interface (Domain)

```php
namespace App\Domain\Repository;

interface SubscriptionRepositoryInterface
{
    public function save(Subscription $subscription): void;
    public function findByUserAndEntity(string $userId, string $entityType, string $entityId): ?Subscription;
    public function findActiveByUserId(string $userId): array;
    public function findActiveByEntities(array $entities): array;
    public function hasActiveSubscriptionsForAny(array $entities): bool;
}
```

## Repository Implementation (Infrastructure)

```php
namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Subscription;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubscriptionRepository extends ServiceEntityRepository implements SubscriptionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $subscription): void
    {
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();
    }

    public function findByUserAndEntity(
        string $userId,
        string $entityType,
        string $entityId,
    ): ?Subscription {
        return $this->findOneBy([
            'userId' => $userId,
            'entityType' => $entityType,
            'entityId' => $entityId,
        ]);
    }

    public function findActiveByEntities(array $entities): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', 'active');

        $orConditions = [];
        foreach ($entities as $i => $entity) {
            $orConditions[] = $qb->expr()->andX(
                $qb->expr()->eq('s.entityType', ":type_{$i}"),
                $qb->expr()->eq('s.entityId', ":id_{$i}"),
            );
            $qb->setParameter("type_{$i}", $entity['type']);
            $qb->setParameter("id_{$i}", $entity['id']);
        }

        $qb->andWhere($qb->expr()->orX(...$orConditions));

        return $qb->getQuery()->getResult();
    }

    public function hasActiveSubscriptionsForAny(array $entities): bool
    {
        return !empty($this->findActiveByEntities($entities));
    }
}
```

## Campaign Entity

```php
#[ORM\Entity]
#[ORM\Table(name: 'campaigns')]
#[ORM\Index(columns: ['status', 'scheduled_at'], name: 'idx_status_scheduled')]
class Campaign
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status;

    #[ORM\Column]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: 'json')]
    private array $audienceCriteria;

    #[ORM\Column(length: 255)]
    private string $editorialId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
}
```

## Migration Convention

```php
// migrations/Version20240101120000.php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101120000 extends AbstractMigration
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
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscriptions');
    }
}
```

## Rules

- Entities own their behavior — no anemic models
- Use UUID v7 for IDs (time-sortable)
- Private constructor + static `create()` factory
- Soft delete via status field, never hard delete subscriptions
- Repository interfaces in Domain, implementations in Infrastructure
- Always add indexes for query patterns
- Unique constraints to prevent duplicate subscriptions
- Use `\DateTimeImmutable` for all timestamps
- Entity type is an enum-like string: `journalist`, `tag`, `section`
