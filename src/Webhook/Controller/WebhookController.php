<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Webhook\Controller;

use Kommandhub\ShipbubbleSW\Webhook\Service\WebhookReceiver;
use Kommandhub\ShipbubbleSW\Webhook\Service\WebhookSignatureValidator;
use Psr\Log\LoggerInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Shipbubble's webhook endpoint (spec §7). Storefront scope, because
 * Shipbubble cannot authenticate against the Admin API; the signature is the
 * authentication. Answers at once — processing happens in the queue.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookReceiver $receiver,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/kmh-shipbubble/webhook/{salesChannelId}',
        name: 'frontend.kmh_shipbubble.webhook',
        requirements: ['salesChannelId' => '[0-9a-f]{32}|default'],
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['POST'],
    )]
    public function receive(string $salesChannelId, Request $request): Response
    {
        $result = $this->receiver->receive(
            $request->getContent(),
            $request->headers->get(WebhookSignatureValidator::SIGNATURE_HEADER),
            $salesChannelId === 'default' ? null : $salesChannelId,
        );

        if ($result === WebhookReceiver::INVALID_SIGNATURE || $result === WebhookReceiver::INVALID_PAYLOAD) {
            $this->logger->warning('Shipbubble webhook rejected.', ['reason' => $result, 'ip' => $request->getClientIp()]);

            return new Response('', $result === WebhookReceiver::INVALID_SIGNATURE ? Response::HTTP_UNAUTHORIZED : Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_OK);
    }
}
