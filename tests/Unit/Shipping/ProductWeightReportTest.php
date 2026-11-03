<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipping;

use Kommandhub\ShipbubbleSW\Administration\Controller\ProductWeightController;
use Kommandhub\ShipbubbleSW\Shipping\ProductWeightReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(ProductWeightReport::class)]
#[CoversClass(ProductWeightController::class)]
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

        $response = (new ProductWeightController(new ProductWeightReport($repository)))->productsWithoutWeight($context);

        static::assertSame('{"count":4}', $response->getContent());
        static::assertFalse($context->considerInheritance());
    }
}
