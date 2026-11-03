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

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrderDeliveryId(): string
    {
        return $this->orderDeliveryId;
    }

    public function setOrderDeliveryId(string $orderDeliveryId): void
    {
        $this->orderDeliveryId = $orderDeliveryId;
    }

    public function getOrderDeliveryVersionId(): string
    {
        return $this->orderDeliveryVersionId;
    }

    public function setOrderDeliveryVersionId(string $orderDeliveryVersionId): void
    {
        $this->orderDeliveryVersionId = $orderDeliveryVersionId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCourierId(): ?string
    {
        return $this->courierId;
    }

    public function setCourierId(?string $courierId): void
    {
        $this->courierId = $courierId;
    }

    public function getCourierName(): ?string
    {
        return $this->courierName;
    }

    public function setCourierName(?string $courierName): void
    {
        $this->courierName = $courierName;
    }

    public function getServiceCode(): ?string
    {
        return $this->serviceCode;
    }

    public function setServiceCode(?string $serviceCode): void
    {
        $this->serviceCode = $serviceCode;
    }

    public function getRequestToken(): ?string
    {
        return $this->requestToken;
    }

    public function setRequestToken(?string $requestToken): void
    {
        $this->requestToken = $requestToken;
    }

    public function getQuotedAt(): ?\DateTimeInterface
    {
        return $this->quotedAt;
    }

    public function setQuotedAt(?\DateTimeInterface $quotedAt): void
    {
        $this->quotedAt = $quotedAt;
    }

    public function getQuotedAmount(): ?float
    {
        return $this->quotedAmount;
    }

    public function setQuotedAmount(?float $quotedAmount): void
    {
        $this->quotedAmount = $quotedAmount;
    }

    public function getChargedAmount(): ?float
    {
        return $this->chargedAmount;
    }

    public function setChargedAmount(?float $chargedAmount): void
    {
        $this->chargedAmount = $chargedAmount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getShipbubbleOrderId(): ?string
    {
        return $this->shipbubbleOrderId;
    }

    public function setShipbubbleOrderId(?string $shipbubbleOrderId): void
    {
        $this->shipbubbleOrderId = $shipbubbleOrderId;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): void
    {
        $this->trackingUrl = $trackingUrl;
    }

    public function getWaybillUrl(): ?string
    {
        return $this->waybillUrl;
    }

    public function setWaybillUrl(?string $waybillUrl): void
    {
        $this->waybillUrl = $waybillUrl;
    }

    public function getCod(): ?bool
    {
        return $this->cod;
    }

    public function setCod(?bool $cod): void
    {
        $this->cod = $cod;
    }

    public function getInsuranceCode(): ?string
    {
        return $this->insuranceCode;
    }

    public function setInsuranceCode(?string $insuranceCode): void
    {
        $this->insuranceCode = $insuranceCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return array<mixed>|null
     */
    public function getLastPayload(): ?array
    {
        return $this->lastPayload;
    }

    /**
     * @param array<mixed>|null $lastPayload
     */
    public function setLastPayload(?array $lastPayload): void
    {
        $this->lastPayload = $lastPayload;
    }

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeInterface $deliveredAt): void
    {
        $this->deliveredAt = $deliveredAt;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeInterface $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderDelivery(): ?OrderDeliveryEntity
    {
        return $this->orderDelivery;
    }

    public function setOrderDelivery(?OrderDeliveryEntity $orderDelivery): void
    {
        $this->orderDelivery = $orderDelivery;
    }
}
