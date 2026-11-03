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
use Shopware\Core\Checkout\Order\OrderEntity;
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
        $items = [];

        foreach ($cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $payload = $lineItem->getPayload();
            $items[] = [
                'id' => $lineItem->getId(),
                'name' => (string)$lineItem->getLabel(),
                'number' => is_string($payload['productNumber'] ?? null) ? $payload['productNumber'] : '',
                'weight' => $lineItem->getDeliveryInformation()?->getWeight(),
                'unitPrice' => $lineItem->getPrice()?->getUnitPrice() ?? 0.0,
                'quantity' => $lineItem->getQuantity(),
            ];
        }

        return $this->fromItems($items, $salesChannelId);
    }

    /**
     * The parcel of a placed order, for a re-quote from the Administration.
     * Needs the order's `lineItems.product` association for the weights.
     */
    public function buildForOrder(OrderEntity $order): Package
    {
        $items = [];

        foreach ($order->getLineItems()?->filterByType(LineItem::PRODUCT_LINE_ITEM_TYPE) ?? [] as $lineItem) {
            $payload = $lineItem->getPayload() ?? [];
            $items[] = [
                'id' => $lineItem->getId(),
                'name' => $lineItem->getLabel(),
                'number' => is_string($payload['productNumber'] ?? null) ? $payload['productNumber'] : '',
                'weight' => $lineItem->getProduct()?->getWeight(),
                'unitPrice' => $lineItem->getUnitPrice(),
                'quantity' => $lineItem->getQuantity(),
            ];
        }

        return $this->fromItems($items, $order->getSalesChannelId());
    }

    /**
     * @param list<array{id: string, name: string, number: string, weight: ?float, unitPrice: float, quantity: int}> $items
     */
    private function fromItems(array $items, ?string $salesChannelId): Package
    {
        $defaultWeight = $this->config->getFloat('defaultProductWeight', 0.5, $salesChannelId);
        $packageItems = [];
        $totalWeight = 0.0;

        // Sorted by id so the signature does not depend on line item order.
        usort($items, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        foreach ($items as $item) {
            $weight = $item['weight'];

            if ($weight === null || $weight <= 0) {
                $this->logger->notice('Product without weight; using the default product weight.', [
                    'lineItemId' => $item['id'],
                    'salesChannelId' => $salesChannelId,
                ]);
                $weight = $defaultWeight;
            }

            $packageItems[] = [
                'name' => $item['name'],
                'description' => $item['number'],
                'unit_weight' => (string)$weight,
                'unit_amount' => (string)$item['unitPrice'],
                'quantity' => (string)$item['quantity'],
            ];
            $totalWeight += $weight * $item['quantity'];
        }

        return new Package($packageItems, $this->dimension($totalWeight, $salesChannelId), $this->config->getString('packageCategoryId', $salesChannelId));
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
