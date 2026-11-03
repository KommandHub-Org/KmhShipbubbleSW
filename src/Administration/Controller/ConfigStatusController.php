<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Administration\Controller;

use Kommandhub\ShipbubbleSW\Shipping\ProductWeightReport;
use Kommandhub\ShipbubbleSW\Webhook\Service\WebhookReceiver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API for the configuration page's status lines: products without a
 * weight, and when the last Shipbubble webhook arrived.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConfigStatusController extends AbstractController
{
    public function __construct(
        private readonly ProductWeightReport $report,
        private readonly WebhookReceiver $webhookReceiver,
    ) {
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/products-without-weight',
        name: 'api.action.kmh_shipbubble.products_without_weight',
        defaults: ['_acl' => ['product:read']],
        methods: ['GET'],
    )]
    public function productsWithoutWeight(Context $context): JsonResponse
    {
        return new JsonResponse(['count' => $this->report->countWithoutWeight($context)]);
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/webhook-status',
        name: 'api.action.kmh_shipbubble.webhook_status',
        defaults: ['_acl' => ['system_config:read']],
        methods: ['GET'],
    )]
    public function webhookStatus(): JsonResponse
    {
        return new JsonResponse(['lastReceivedAt' => $this->webhookReceiver->lastReceivedAt()]);
    }
}
