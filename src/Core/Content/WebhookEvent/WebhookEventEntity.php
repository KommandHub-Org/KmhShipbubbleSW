<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Core\Content\WebhookEvent;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class WebhookEventEntity extends Entity
{
    use EntityIdTrait;

    protected string $idempotencyKey;

    protected string $event;

    protected ?string $shipbubbleOrderId = null;

    /**
     * @var array<mixed>
     */
    protected array $payload = [];

    protected \DateTimeInterface $receivedAt;

    protected ?\DateTimeInterface $processedAt = null;

    protected ?string $error = null;

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getShipbubbleOrderId(): ?string
    {
        return $this->shipbubbleOrderId;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getReceivedAt(): \DateTimeInterface
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
