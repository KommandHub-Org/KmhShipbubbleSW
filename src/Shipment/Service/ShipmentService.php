<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipment\Service;

use Doctrine\DBAL\Connection;
use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\Shipment;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Core\Content\Shipment\ShipmentCollection;
use Kommandhub\ShipbubbleSW\Core\Content\Shipment\ShipmentEntity;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCancelledEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCreatedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentDeliveredEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentFailedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentInTransitEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentPickedUpEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentReturnedEvent;
use Kommandhub\ShipbubbleSW\Quote\CourierOption;
use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Kommandhub\ShipbubbleSW\Quote\QuoteService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentException;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Books, refreshes and cancels the Shipbubble shipment of an order delivery
 * (spec §12).
 *
 * Booking uses the request token stored at checkout. A token books one label
 * only and may expire, so when the shipment needs a quote, the token is old,
 * or Shipbubble rejects it, the delivery is re-quoted and the merchant
 * confirms the new carrier price before anything is booked. The customer's
 * charged price never changes.
 */
class ShipmentService
{
    /**
     * Tokens older than this are re-quoted before booking. ⚠ The real limit is
     * not documented; Shipbubble support has been asked (spec §12).
     */
    public const TOKEN_MAX_AGE_HOURS = 120;

    /**
     * How long a re-quote waits for the merchant's confirmation, in seconds.
     */
    private const CONFIRMATION_WINDOW = 900;

    private const INVALID_TOKEN = 'invalid request token';

    /**
     * Flow event fired when a shipment reaches a status (spec §10).
     */
    private const STATUS_EVENTS = [
        'picked_up' => ShipmentPickedUpEvent::class,
        'in_transit' => ShipmentInTransitEvent::class,
        'completed' => ShipmentDeliveredEvent::class,
        'returned' => ShipmentReturnedEvent::class,
        'cancelled' => ShipmentCancelledEvent::class,
    ];

    /**
     * @param EntityRepository<ShipmentCollection> $kmhShipbubbleShipmentRepository
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<OrderDeliveryCollection> $orderDeliveryRepository
     */
    public function __construct(
        private readonly EntityRepository $kmhShipbubbleShipmentRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $orderDeliveryRepository,
        private readonly ShipbubbleClient $client,
        private readonly QuoteService $quoteService,
        private readonly DeliveryStateUpdater $deliveryStateUpdater,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ShipmentException
     * @throws ShipbubbleApiException
     */
    public function create(string $shipmentId, bool $confirmed, Context $context): CreateResult
    {
        // The row lock keeps two clicks, or a click and the Flow action, from
        // booking the same delivery twice. A Shipbubble refusal is returned,
        // not thrown, so the transaction commits and the error can be stored.
        /** @var array{0: ShipmentEntity, 1: CreateResult|ShipbubbleApiException} $outcome */
        $outcome = $this->connection->transactional(function () use ($shipmentId, $confirmed, $context): array {
            $status = $this->connection->fetchOne(
                'SELECT `status` FROM `kmh_shipbubble_shipment` WHERE `id` = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($shipmentId)],
            );

            if (!is_string($status)) {
                throw ShipmentException::notFound();
            }

            if (ShipmentStatus::tryFrom($status)?->isBookable() !== true) {
                throw ShipmentException::notBookable($status);
            }

            $shipment = $this->load($shipmentId, $context);

            try {
                return [$shipment, $this->attempt($shipment, $confirmed, $context)];
            } catch (ShipbubbleApiException $e) {
                return [$shipment, $e];
            }
        });

        [$shipment, $result] = $outcome;

        if ($result instanceof ShipbubbleApiException) {
            $this->recordError($shipment, $result, $context);
            $this->dispatch($shipmentId, ShipmentFailedEvent::class, $context, $result->getMessage());

            throw $result;
        }

        if ($result->booked) {
            $this->dispatch($shipmentId, ShipmentCreatedEvent::class, $context);
        }

        return $result;
    }

    /**
     * Reads the shipment back from Shipbubble and applies its status.
     *
     * @throws ShipmentException
     * @throws ShipbubbleApiException
     */
    public function refresh(string $shipmentId, Context $context): void
    {
        $shipment = $this->load($shipmentId, $context);
        $orderId = $shipment->getShipbubbleOrderId();

        if ($orderId === null) {
            throw ShipmentException::notBooked();
        }

        $label = $this->client->labels()->get([$orderId], $shipment->getSalesChannelId())[0] ?? null;

        if ($label === null) {
            throw ShipmentException::notFound();
        }

        $this->applyLabel($shipment, $label, ShipmentStatus::tryFrom($label->status), $context);
    }

    /**
     * Applies a status change Shipbubble reported by webhook (spec §7). The
     * webhook body's own `status` is the previous one, so the caller passes
     * the new status separately.
     *
     * @return bool false when no shipment has this Shipbubble order id
     */
    public function applyWebhook(string $shipbubbleOrderId, Shipment $label, ?ShipmentStatus $status, Context $context): bool
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('shipbubbleOrderId', $shipbubbleOrderId))->setLimit(1);
        $shipment = $this->kmhShipbubbleShipmentRepository->search($criteria, $context)->getEntities()->first();

        if ($shipment === null) {
            return false;
        }

        $this->applyLabel($shipment, $label, $status, $context);

        return true;
    }

    /**
     * @throws ShipmentException
     * @throws ShipbubbleApiException with Shipbubble's reason when it refuses
     */
    public function cancel(string $shipmentId, Context $context): void
    {
        $shipment = $this->load($shipmentId, $context);
        $orderId = $shipment->getShipbubbleOrderId();

        if ($orderId === null || ShipmentStatus::tryFrom($shipment->getStatus())?->isOpen() !== true) {
            throw ShipmentException::notCancellable($shipment->getStatus());
        }

        try {
            $this->client->labels()->cancel($orderId, $shipment->getSalesChannelId());
        } catch (ShipbubbleApiException $e) {
            $this->recordError($shipment, $e, $context);

            throw $e;
        }

        $this->update($shipment, [
            'status' => ShipmentStatus::Cancelled->value,
            'cancelledAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'errorCode' => null,
            'errorMessage' => null,
        ], $context);
        $this->deliveryStateUpdater->apply(ShipmentStatus::Cancelled, $shipment->getOrderDeliveryId(), $context);
        $this->dispatch($shipment->getId(), ShipmentCancelledEvent::class, $context);
    }

    /**
     * @throws ShipmentException
     * @throws ShipbubbleApiException any refusal except a spent token
     */
    private function attempt(ShipmentEntity $shipment, bool $confirmed, Context $context): CreateResult
    {
        if (!$this->needsQuote($shipment)) {
            try {
                $this->book($shipment, $shipment->getRequestToken() ?? '', $context);

                return CreateResult::booked();
            } catch (ShipbubbleApiException $e) {
                if (!self::isInvalidToken($e)) {
                    throw $e;
                }
            }
        }

        return $this->bookAfterQuote($shipment, $confirmed, $context);
    }

    /**
     * Fires a shipment Flow event with the shipment's current data (spec §10).
     *
     * @param class-string<ShipmentEvent> $eventClass
     */
    private function dispatch(string $shipmentId, string $eventClass, Context $context, string $errorMessage = ''): void
    {
        $shipment = $this->load($shipmentId, $context);
        $criteria = (new Criteria([$shipment->getOrderId()]))->addAssociation('orderCustomer');
        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        if ($order === null) {
            return;
        }

        $this->eventDispatcher->dispatch(new $eventClass(
            $order,
            $context,
            (string)$shipment->getCourierName(),
            (string)$shipment->getTrackingUrl(),
            $shipment->getStatus(),
            $errorMessage,
        ));
    }

    private function needsQuote(ShipmentEntity $shipment): bool
    {
        $quotedAt = $shipment->getQuotedAt();

        return $shipment->getStatus() === ShipmentStatus::NeedsQuote->value
            || ($shipment->getRequestToken() ?? '') === ''
            || $quotedAt === null
            || $quotedAt < new \DateTimeImmutable(sprintf('-%d hours', self::TOKEN_MAX_AGE_HOURS));
    }

    /**
     * Books the quote the merchant confirmed, or fetches a fresh one and asks.
     *
     * A fresh quote is stored on the shipment (status `needs_quote`), so the
     * confirmation books exactly the price the merchant saw.
     *
     * @throws ShipmentException
     * @throws ShipbubbleApiException
     */
    private function bookAfterQuote(ShipmentEntity $shipment, bool $confirmed, Context $context): CreateResult
    {
        if ($confirmed && $this->hasConfirmableQuote($shipment)) {
            try {
                $this->book($shipment, (string)$shipment->getRequestToken(), $context);

                return CreateResult::booked();
            } catch (ShipbubbleApiException $e) {
                if (!self::isInvalidToken($e)) {
                    throw $e;
                }
            }
        }

        $quote = $this->requote($shipment, $context);
        $courier = $quote['courier'];

        $this->update($shipment, [
            'status' => ShipmentStatus::NeedsQuote->value,
            'requestToken' => $quote['token'],
            'courierId' => $courier->courierId,
            'courierName' => $courier->courierName,
            'serviceCode' => $courier->serviceCode,
            'quotedAmount' => $courier->carrierTotal,
            'quotedAt' => $quote['fetchedAt']->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], $context);

        return CreateResult::confirmationNeeded(
            $shipment->getQuotedAmount(),
            $courier->carrierTotal,
            $courier->courierName,
            (string)$shipment->getCurrency(),
        );
    }

    /**
     * A re-quote the merchant was shown a moment ago.
     */
    private function hasConfirmableQuote(ShipmentEntity $shipment): bool
    {
        $quotedAt = $shipment->getQuotedAt();

        return $shipment->getStatus() === ShipmentStatus::NeedsQuote->value
            && ($shipment->getRequestToken() ?? '') !== ''
            && $quotedAt !== null
            && $quotedAt >= new \DateTimeImmutable(sprintf('-%d seconds', self::CONFIRMATION_WINDOW));
    }

    /**
     * A fresh quote for the order, keeping the customer's courier when it is
     * still offered.
     *
     * @return array{token: string, courier: CourierOption, fetchedAt: \DateTimeImmutable}
     *
     * @throws ShipmentException
     */
    private function requote(ShipmentEntity $shipment, Context $context): array
    {
        $criteria = new Criteria([$shipment->getOrderId()]);
        $criteria->addAssociations(['lineItems.product', 'orderCustomer', 'currency', 'deliveries.shippingOrderAddress.country', 'deliveries.shippingOrderAddress.countryState']);
        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();
        $address = $order?->getDeliveries()?->get($shipment->getOrderDeliveryId())?->getShippingOrderAddress();

        if ($order === null || $address === null) {
            throw ShipmentException::quoteFailed(QuoteError::IncompleteAddress);
        }

        $quote = $this->quoteService->requote($order, $address, $context);

        if ($quote instanceof QuoteError) {
            throw ShipmentException::quoteFailed($quote);
        }

        $courier = $quote->couriers[0];

        foreach ($quote->couriers as $offered) {
            if ($offered->serviceCode === $shipment->getServiceCode()) {
                $courier = $offered;
                break;
            }
        }

        return ['token' => $quote->requestToken, 'courier' => $courier, 'fetchedAt' => $quote->fetchedAt];
    }

    /**
     * @throws ShipbubbleApiException
     */
    private function book(ShipmentEntity $shipment, string $token, Context $context): void
    {
        $label = $this->client->labels()->create($token, (string)$shipment->getServiceCode(), (string)$shipment->getCourierId(), $shipment->getSalesChannelId());

        $this->update($shipment, [
            'shipbubbleOrderId' => $label->orderId,
            'status' => ShipmentStatus::Pending->value,
            'trackingUrl' => $label->trackingUrl,
            'waybillUrl' => $label->waybillUrl,
            'errorCode' => null,
            'errorMessage' => null,
        ], $context);
        $this->addTrackingCode($shipment->getOrderDeliveryId(), $label->orderId, $context);
    }

    private function applyLabel(ShipmentEntity $shipment, Shipment $label, ?ShipmentStatus $status, Context $context): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $data = [
            'trackingUrl' => $label->trackingUrl ?? $shipment->getTrackingUrl(),
            'waybillUrl' => $label->waybillUrl ?? $shipment->getWaybillUrl(),
            'lastPayload' => ['courierTrackingCode' => $label->courierTrackingCode],
            'errorCode' => null,
            'errorMessage' => null,
        ];

        if ($status !== null) {
            $data['status'] = $status->value;

            if ($status === ShipmentStatus::Completed && $shipment->getDeliveredAt() === null) {
                $data['deliveredAt'] = $now;
            }

            if ($status === ShipmentStatus::Cancelled && $shipment->getCancelledAt() === null) {
                $data['cancelledAt'] = $now;
            }
        }

        $this->update($shipment, $data, $context);

        if ($status === null || $status->value === $shipment->getStatus()) {
            return;
        }

        $this->deliveryStateUpdater->apply($status, $shipment->getOrderDeliveryId(), $context);

        if (isset(self::STATUS_EVENTS[$status->value])) {
            $this->dispatch($shipment->getId(), self::STATUS_EVENTS[$status->value], $context);
        }
    }

    /**
     * Writes the Shipbubble order id as the delivery's tracking code, so
     * Shopware's own tracking link and mails point to Shipbubble tracking.
     */
    private function addTrackingCode(string $orderDeliveryId, string $trackingCode, Context $context): void
    {
        $delivery = $this->orderDeliveryRepository->search(new Criteria([$orderDeliveryId]), $context)->getEntities()->first();

        if ($delivery === null) {
            return;
        }

        $codes = $delivery->getTrackingCodes();

        if (!\in_array($trackingCode, $codes, true)) {
            $this->orderDeliveryRepository->update([['id' => $orderDeliveryId, 'trackingCodes' => [...$codes, $trackingCode]]], $context);
        }
    }

    private function recordError(ShipmentEntity $shipment, ShipbubbleApiException $e, Context $context): void
    {
        $this->logger->warning('Shipbubble shipment action failed.', [
            'shipmentId' => $shipment->getId(),
            'reason' => $e->getReason()->value,
            'httpStatus' => $e->getHttpStatus(),
        ]);

        $this->update($shipment, [
            'errorCode' => $e->getReason()->value,
            'errorMessage' => mb_substr($e->getMessage(), 0, 1024),
        ], $context);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(ShipmentEntity $shipment, array $data, Context $context): void
    {
        $this->kmhShipbubbleShipmentRepository->update([['id' => $shipment->getId()] + $data], $context);
    }

    /**
     * @throws ShipmentException
     */
    private function load(string $shipmentId, Context $context): ShipmentEntity
    {
        $shipment = $this->kmhShipbubbleShipmentRepository->search(new Criteria([$shipmentId]), $context)->getEntities()->first();

        if ($shipment === null) {
            throw ShipmentException::notFound();
        }

        return $shipment;
    }

    private static function isInvalidToken(ShipbubbleApiException $e): bool
    {
        return $e->getReason() === ApiErrorReason::Validation && str_contains(mb_strtolower($e->getMessage()), self::INVALID_TOKEN);
    }
}
