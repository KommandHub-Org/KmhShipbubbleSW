<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Core\Content\Shipment;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShipmentEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;

    protected string $orderVersionId;

    protected string $orderDeliveryId;

    protected string $orderDeliveryVersionId;

    protected string $salesChannelId;

    protected string $mode;

    protected string $status;

    protected ?string $courierId = null;

    protected ?string $courierName = null;

    protected ?string $serviceCode = null;

    protected ?string $requestToken = null;

    protected ?\DateTimeInterface $quotedAt = null;

    protected ?float $quotedAmount = null;

    protected ?float $chargedAmount = null;

    protected ?string $currency = null;

    protected ?string $shipbubbleOrderId = null;

    protected ?string $trackingUrl = null;

    protected ?string $waybillUrl = null;

    protected ?bool $cod = null;

    protected ?string $insuranceCode = null;

    protected ?string $errorCode = null;

    protected ?string $errorMessage = null;

    /**
     * @var array<mixed>|null
     */
    protected ?array $lastPayload = null;

    protected ?\DateTimeInterface $deliveredAt = null;

    protected ?\DateTimeInterface $cancelledAt = null;

    protected ?OrderEntity $order = null;

    protected ?OrderDeliveryEntity $orderDelivery = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getOrderDeliveryId(): string
    {
        return $this->orderDeliveryId;
    }

    public function getOrderDeliveryVersionId(): string
    {
        return $this->orderDeliveryVersionId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCourierId(): ?string
    {
        return $this->courierId;
    }

    public function getCourierName(): ?string
    {
        return $this->courierName;
    }

    public function getServiceCode(): ?string
    {
        return $this->serviceCode;
    }

    public function getRequestToken(): ?string
    {
        return $this->requestToken;
    }

    public function getQuotedAt(): ?\DateTimeInterface
    {
        return $this->quotedAt;
    }

    public function getQuotedAmount(): ?float
    {
        return $this->quotedAmount;
    }

    public function getChargedAmount(): ?float
    {
        return $this->chargedAmount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getShipbubbleOrderId(): ?string
    {
        return $this->shipbubbleOrderId;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function getWaybillUrl(): ?string
    {
        return $this->waybillUrl;
    }

    public function getCod(): ?bool
    {
        return $this->cod;
    }

    public function getInsuranceCode(): ?string
    {
        return $this->insuranceCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<mixed>|null
     */
    public function getLastPayload(): ?array
    {
        return $this->lastPayload;
    }

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function getOrderDelivery(): ?OrderDeliveryEntity
    {
        return $this->orderDelivery;
    }
}
