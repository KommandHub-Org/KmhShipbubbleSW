<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipping;

use Kommandhub\ShipbubbleSW\Administration\Controller\ConfigStatusController;
use Kommandhub\ShipbubbleSW\Shipping\ProductWeightReport;
use Kommandhub\ShipbubbleSW\Webhook\Service\WebhookReceiver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(ProductWeightReport::class)]
#[CoversClass(ConfigStatusController::class)]
class ProductWeightReportTest extends TestCase
{
    public function testCountsActiveSellableProductsWithoutWeightIncludingInheritance(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository<ProductCollection>&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())->method('searchIds')
            ->willReturnCallback(static function (Criteria $criteria, Context $searchContext): IdSearchResult {
                static::assertTrue($searchContext->considerInheritance());
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                static::assertCount(3, $criteria->getFilters());

                return new IdSearchResult(4, [], $criteria, $searchContext);
            });

        $response = (new ConfigStatusController(new ProductWeightReport($repository), $this->createStub(WebhookReceiver::class)))->productsWithoutWeight($context);

        static::assertSame('{"count":4}', $response->getContent());
        static::assertFalse($context->considerInheritance());
    }

    public function testWebhookStatus(): void
    {
        $receiver = $this->createMock(WebhookReceiver::class);
        $receiver->method('lastReceivedAt')->willReturn('2026-11-03 12:00:00.000');
        $controller = new ConfigStatusController(new ProductWeightReport($this->createStub(EntityRepository::class)), $receiver);

        static::assertSame('{"lastReceivedAt":"2026-11-03 12:00:00.000"}', $controller->webhookStatus()->getContent());
    }
}
