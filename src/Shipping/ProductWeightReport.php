<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

/**
 * Counts the sellable products checkout has to price with the default weight
 * (spec §6), so the merchant can fix them.
 */
class ProductWeightReport
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(private readonly EntityRepository $productRepository)
    {
    }

    public function countWithoutWeight(Context $context): int
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            // A parent with variants is never in a cart; its variants are counted.
            ->addFilter(new EqualsFilter('childCount', 0))
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('weight', null),
                new RangeFilter('weight', [RangeFilter::LTE => 0]),
            ]))
            ->setLimit(1)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        // Variants inherit the parent's weight.
        $context = clone $context;
        $context->setConsiderInheritance(true);

        return $this->productRepository->searchIds($criteria, $context)->getTotal();
    }
}
