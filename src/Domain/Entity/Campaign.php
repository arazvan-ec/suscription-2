<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\CampaignStatus;
use App\Domain\ValueObject\CampaignType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'campaigns')]
#[ORM\Index(columns: ['status', 'scheduled_at'], name: 'idx_status_scheduled')]
class Campaign
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 50, enumType: CampaignType::class)]
    private CampaignType $type;

    #[ORM\Column(length: 20, enumType: CampaignStatus::class, options: ['default' => 'pending'])]
    private CampaignStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: 'json')]
    private array $audienceCriteria;

    #[ORM\Column(length: 255)]
    private string $editorialId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        CampaignType $type,
        string $editorialId,
        array $audienceCriteria,
        \DateTimeImmutable $scheduledAt,
    ) {
        $this->id = Uuid::v7();
        $this->type = $type;
        $this->editorialId = $editorialId;
        $this->audienceCriteria = $audienceCriteria;
        $this->status = CampaignStatus::PENDING;
        $this->scheduledAt = $scheduledAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(
        CampaignType $type,
        string $editorialId,
        array $audienceCriteria,
        \DateTimeImmutable $scheduledAt,
    ): self {
        return new self($type, $editorialId, $audienceCriteria, $scheduledAt);
    }

    public function markAsProcessing(): void
    {
        $this->status = CampaignStatus::PROCESSING;
    }

    public function markAsSent(): void
    {
        $this->status = CampaignStatus::SENT;
    }

    public function markAsFailed(): void
    {
        $this->status = CampaignStatus::FAILED;
    }

    public function isPending(): bool
    {
        return $this->status === CampaignStatus::PENDING;
    }

    public function isReadyToProcess(): bool
    {
        return $this->isPending() && $this->scheduledAt <= new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): CampaignType
    {
        return $this->type;
    }

    public function getStatus(): CampaignStatus
    {
        return $this->status;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getAudienceCriteria(): array
    {
        return $this->audienceCriteria;
    }

    public function getEditorialId(): string
    {
        return $this->editorialId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
