<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Administration\Controller;

use Kommandhub\ShipbubbleSW\Shipping\ProductWeightReport;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API for the "products without weight" warning on the config page.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ProductWeightController extends AbstractController
{
    public function __construct(private readonly ProductWeightReport $report)
    {
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
}
