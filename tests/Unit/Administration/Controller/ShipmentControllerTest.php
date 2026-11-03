<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Administration\Controller;

use Kommandhub\ShipbubbleSW\Administration\Controller\ShipmentController;
use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Shipment\Service\CreateResult;
use Kommandhub\ShipbubbleSW\Shipment\Service\ShipmentService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ShipmentController::class)]
#[UsesClass(CreateResult::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
#[UsesClass(ShipmentException::class)]
class ShipmentControllerTest extends TestCase
{
    public function testCreatePassesTheConfirmationAndReturnsTheResult(): void
    {
        $service = $this->createMock(ShipmentService::class);
        $service->expects(static::once())->method('create')->with('s1', true)->willReturn(CreateResult::confirmationNeeded(1.5, 2.5, 'One', 'NGN'));

        $body = $this->json((new ShipmentController($service))->create('s1', new Request([], ['confirmed' => true]), Context::createDefaultContext()));

        static::assertSame(['success' => true, 'booked' => false, 'oldCarrierPrice' => 1.5, 'newCarrierPrice' => 2.5, 'courierName' => 'One', 'currency' => 'NGN'], $body);
    }

    public function testRefreshAndCancelSucceed(): void
    {
        $service = $this->createMock(ShipmentService::class);
        $service->expects(static::once())->method('refresh')->with('s1');
        $service->expects(static::once())->method('cancel')->with('s1');
        $controller = new ShipmentController($service);

        static::assertSame(['success' => true], $this->json($controller->refresh('s1', Context::createDefaultContext())));
        static::assertSame(['success' => true], $this->json($controller->cancel('s1', Context::createDefaultContext())));
    }

    public function testRefusalsCarryTheReasonAndMessage(): void
    {
        $service = $this->createMock(ShipmentService::class);
        $service->method('cancel')->willThrowException(new ShipbubbleApiException(ApiErrorReason::BadRequest, 'Shipment label already processed', 400));
        $service->method('refresh')->willThrowException(ShipmentException::notBooked());
        $controller = new ShipmentController($service);

        static::assertSame(['success' => false, 'reason' => 'bad_request', 'message' => 'Shipment label already processed'], $this->json($controller->cancel('s1', Context::createDefaultContext())));
        static::assertSame('not_booked', $this->json($controller->refresh('s1', Context::createDefaultContext()))['reason']);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(JsonResponse $response): array
    {
        return json_decode((string)$response->getContent(), true);
    }
}
