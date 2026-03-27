<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\EntityType;
use App\Domain\ValueObject\SubscriptionStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(columns: ['entity_type', 'entity_id', 'status'], name: 'idx_entity_status')]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_user_status')]
#[ORM\UniqueConstraint(columns: ['user_id', 'entity_type', 'entity_id'], name: 'uniq_user_entity')]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $userId;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 50, enumType: EntityType::class)]
    private EntityType $entityType;

    #[ORM\Column(length: 255)]
    private string $entityId;

    #[ORM\Column(length: 20, enumType: SubscriptionStatus::class, options: ['default' => 'active'])]
    private SubscriptionStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        string $userId,
        string $email,
        EntityType $entityType,
        string $entityId,
    ) {
        $this->id = Uuid::v7();
        $this->userId = $userId;
        $this->email = $email;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->status = SubscriptionStatus::ACTIVE;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(
        string $userId,
        string $email,
        EntityType $entityType,
        string $entityId,
    ): self {
        return new self($userId, $email, $entityType, $entityId);
    }

    public function deactivate(): void
    {
        $this->status = SubscriptionStatus::INACTIVE;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reactivate(): void
    {
        $this->status = SubscriptionStatus::ACTIVE;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEntityType(): EntityType
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
