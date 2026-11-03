<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Administration\Controller;

use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Shipment\Service\ShipmentService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API behind the order's Shipbubble card (spec §12). Every route
 * answers 200; a refusal carries `success: false`, a reason and a message the
 * card shows as is.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ShipmentController extends AbstractController
{
    public function __construct(private readonly ShipmentService $shipmentService)
    {
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/shipment/{shipmentId}/create',
        name: 'api.action.kmh_shipbubble.shipment.create',
        requirements: ['shipmentId' => '[0-9a-f]{32}'],
        defaults: ['_acl' => ['kmh_shipbubble_shipment:update']],
        methods: ['POST'],
    )]
    public function create(string $shipmentId, Request $request, Context $context): JsonResponse
    {
        return $this->run(fn (): array => $this->shipmentService->create($shipmentId, $request->request->getBoolean('confirmed'), $context)->toArray());
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/shipment/{shipmentId}/refresh',
        name: 'api.action.kmh_shipbubble.shipment.refresh',
        requirements: ['shipmentId' => '[0-9a-f]{32}'],
        defaults: ['_acl' => ['kmh_shipbubble_shipment:update']],
        methods: ['POST'],
    )]
    public function refresh(string $shipmentId, Context $context): JsonResponse
    {
        return $this->run(function () use ($shipmentId, $context): array {
            $this->shipmentService->refresh($shipmentId, $context);

            return [];
        });
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/shipment/{shipmentId}/cancel',
        name: 'api.action.kmh_shipbubble.shipment.cancel',
        requirements: ['shipmentId' => '[0-9a-f]{32}'],
        defaults: ['_acl' => ['kmh_shipbubble_shipment:update']],
        methods: ['POST'],
    )]
    public function cancel(string $shipmentId, Context $context): JsonResponse
    {
        return $this->run(function () use ($shipmentId, $context): array {
            $this->shipmentService->cancel($shipmentId, $context);

            return [];
        });
    }

    /**
     * @param \Closure(): array<string, mixed> $action
     */
    private function run(\Closure $action): JsonResponse
    {
        try {
            return new JsonResponse(['success' => true] + $action());
        } catch (ShipbubbleApiException $e) {
            return new JsonResponse(['success' => false, 'reason' => $e->getReason()->value, 'message' => $e->getMessage()]);
        } catch (ShipmentException $e) {
            return new JsonResponse(['success' => false, 'reason' => $e->reason, 'message' => $e->getMessage()]);
        }
    }
}
