<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Administration\Controller;

use Kommandhub\ShipbubbleSW\Client\Dto\Courier;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageCategory;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Shipping\Sender\SenderAddressService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API behind the plugin configuration page (spec §11): test the saved
 * API key, validate the sender address, and load the courier and package
 * category options from Shipbubble.
 *
 * Every route answers 200 with `success: false` and a reason on a Shipbubble
 * failure, so the config page can show it inline. Responses never contain the
 * API key: they carry only data Shipbubble returned or a mapped error.
 *
 * All calls use the *saved* configuration of the given sales channel (or the
 * global one), so the merchant saves before testing.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConnectionController extends AbstractController
{
    public function __construct(
        private readonly ShipbubbleClient $client,
        private readonly SenderAddressService $senderAddressService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/test-connection',
        name: 'api.action.kmh_shipbubble.test_connection',
        defaults: ['_acl' => ['system_config:update']],
        methods: ['POST'],
    )]
    public function testConnection(Request $request): JsonResponse
    {
        $salesChannelId = $this->salesChannelId($request);

        try {
            $balance = $this->client->wallet()->balance($salesChannelId);
        } catch (ShipbubbleApiException $e) {
            return $this->failure('Shipbubble connection test failed', $e, $salesChannelId);
        }

        return new JsonResponse(['success' => true, 'balance' => $balance->balance, 'currency' => $balance->currency]);
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/validate-sender',
        name: 'api.action.kmh_shipbubble.validate_sender',
        defaults: ['_acl' => ['system_config:update']],
        methods: ['POST'],
    )]
    public function validateSender(Request $request): JsonResponse
    {
        $salesChannelId = $this->salesChannelId($request);

        try {
            $address = $this->senderAddressService->validateAndStore($salesChannelId);
        } catch (ShipbubbleApiException $e) {
            return $this->failure('Shipbubble sender validation failed', $e, $salesChannelId);
        }

        return new JsonResponse(['success' => true, 'formattedAddress' => $address->formattedAddress]);
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/couriers',
        name: 'api.action.kmh_shipbubble.couriers',
        defaults: ['_acl' => ['system_config:read']],
        methods: ['GET'],
    )]
    public function couriers(Request $request): JsonResponse
    {
        $salesChannelId = $this->salesChannelId($request);

        try {
            $options = array_map(
                static fn (Courier $courier): array => ['value' => $courier->serviceCode, 'label' => $courier->name],
                $this->client->couriers()->list($salesChannelId),
            );
        } catch (ShipbubbleApiException $e) {
            return $this->failure('Loading Shipbubble couriers failed', $e, $salesChannelId);
        }

        return new JsonResponse(['success' => true, 'options' => $options]);
    }

    #[Route(
        path: '/api/_action/kmh-shipbubble/package-categories',
        name: 'api.action.kmh_shipbubble.package_categories',
        defaults: ['_acl' => ['system_config:read']],
        methods: ['GET'],
    )]
    public function packageCategories(Request $request): JsonResponse
    {
        $salesChannelId = $this->salesChannelId($request);

        try {
            $options = array_map(
                static fn (PackageCategory $category): array => ['value' => $category->id, 'label' => $category->name],
                $this->client->rates()->categories($salesChannelId),
            );
        } catch (ShipbubbleApiException $e) {
            return $this->failure('Loading Shipbubble package categories failed', $e, $salesChannelId);
        }

        return new JsonResponse(['success' => true, 'options' => $options]);
    }

    private function salesChannelId(Request $request): ?string
    {
        $value = $request->get('salesChannelId');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function failure(string $logMessage, ShipbubbleApiException $e, ?string $salesChannelId): JsonResponse
    {
        $this->logger->warning($logMessage, [
            'reason' => $e->getReason()->value,
            'httpStatus' => $e->getHttpStatus(),
            'salesChannelId' => $salesChannelId,
        ]);

        return new JsonResponse([
            'success' => false,
            'reason' => $e->getReason()->value,
            'message' => $e->getMessage(),
        ]);
    }
}
