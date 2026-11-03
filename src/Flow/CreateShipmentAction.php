<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow;

use Kommandhub\ShipbubbleSW\Core\Content\Shipment\ShipmentCollection;
use Kommandhub\ShipbubbleSW\Shipment\Service\ShipmentService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\OrderAware;

/**
 * Flow Builder action "Create Shipbubble shipment" (spec §10): books every
 * `quoted` Shipbubble delivery of the order. A delivery that needs a new
 * price is left for the merchant to confirm in the order; a failure is logged
 * and never stops the rest of the flow.
 */
class CreateShipmentAction extends FlowAction
{
    public const ACTION_NAME = 'action.kmh.shipbubble.create_shipment';

    /**
     * @param EntityRepository<ShipmentCollection> $kmhShipbubbleShipmentRepository
     */
    public function __construct(
        private readonly EntityRepository $kmhShipbubbleShipmentRepository,
        private readonly ShipmentService $shipmentService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getName(): string
    {
        return self::ACTION_NAME;
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $orderId = $flow->getData(OrderAware::ORDER_ID);

        if (!is_string($orderId)) {
            return;
        }

        $context = $flow->getContext();
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addFilter(new EqualsFilter('status', ShipmentStatus::Quoted->value));

        /** @var list<string> $shipmentIds */
        $shipmentIds = $this->kmhShipbubbleShipmentRepository->searchIds($criteria, $context)->getIds();

        foreach ($shipmentIds as $shipmentId) {
            try {
                $result = $this->shipmentService->create($shipmentId, false, $context);

                if (!$result->booked) {
                    $this->logger->notice('Shipbubble shipment needs a new price; confirm it in the order.', ['shipmentId' => $shipmentId]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Shipbubble shipment could not be created by the flow.', [
                    'shipmentId' => $shipmentId,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }
}
