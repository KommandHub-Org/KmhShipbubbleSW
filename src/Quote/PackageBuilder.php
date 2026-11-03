<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Kommandhub\ShipbubbleSW\Client\Dto\PackageBox;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Turns the cart's product line items into the parcel Shipbubble prices (spec §6).
 */
class PackageBuilder
{
    private const BOX_CACHE_TTL = 86400;

    public function __construct(
        private readonly Config $config,
        private readonly ShipbubbleClient $client,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function build(Cart $cart, ?string $salesChannelId): Package
    {
        $defaultWeight = $this->config->getFloat('defaultProductWeight', 0.5, $salesChannelId);
        $items = [];
        $totalWeight = 0.0;

        // Sorted by id so the signature does not depend on line item order.
        $lineItems = $cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        usort($lineItems, static fn (LineItem $a, LineItem $b): int => strcmp($a->getId(), $b->getId()));

        foreach ($lineItems as $lineItem) {
            $weight = $lineItem->getDeliveryInformation()?->getWeight();

            if ($weight === null || $weight <= 0) {
                $this->logger->notice('Product without weight; using the default product weight.', [
                    'productId' => $lineItem->getReferencedId(),
                    'salesChannelId' => $salesChannelId,
                ]);
                $weight = $defaultWeight;
            }

            $payload = $lineItem->getPayload();
            $items[] = [
                'name' => (string)$lineItem->getLabel(),
                'description' => is_string($payload['productNumber'] ?? null) ? $payload['productNumber'] : '',
                'unit_weight' => (string)$weight,
                'unit_amount' => (string)($lineItem->getPrice()?->getUnitPrice() ?? 0.0),
                'quantity' => (string)$lineItem->getQuantity(),
            ];
            $totalWeight += $weight * $lineItem->getQuantity();
        }

        return new Package($items, $this->dimension($totalWeight, $salesChannelId), $this->config->getString('packageCategoryId', $salesChannelId));
    }

    /**
     * The first standard box that carries the weight, else the configured default box.
     *
     * @return array{length: float, width: float, height: float}
     */
    private function dimension(float $weight, ?string $salesChannelId): array
    {
        foreach ($this->boxes($salesChannelId) as $box) {
            if ($box->maxWeight >= $weight) {
                return ['length' => $box->length, 'width' => $box->width, 'height' => $box->height];
            }
        }

        return [
            'length' => (float)$this->config->getInt('defaultLength', 56, $salesChannelId),
            'width' => (float)$this->config->getInt('defaultWidth', 50, $salesChannelId),
            'height' => (float)$this->config->getInt('defaultHeight', 45, $salesChannelId),
        ];
    }

    /**
     * @return list<PackageBox>
     */
    private function boxes(?string $salesChannelId): array
    {
        try {
            return $this->cache->get(
                'kmh_sb_boxes_' . $this->config->getMode($salesChannelId),
                function (ItemInterface $item) use ($salesChannelId): array {
                    $item->expiresAfter(self::BOX_CACHE_TTL);

                    return $this->client->rates()->boxes($salesChannelId);
                },
            );
        } catch (ShipbubbleApiException) {
            // Not worth failing the quote over: the default box still gets a price.
            return [];
        }
    }
}
