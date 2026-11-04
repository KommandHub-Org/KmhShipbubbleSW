<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\CourierRate;
use Kommandhub\ShipbubbleSW\Client\Dto\RateQuote;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Prices Shipbubble delivery for a cart (spec §1).
 *
 * Quotes are cached per receiver, parcel and currency, so reloading checkout
 * never calls Shipbubble while changing a quantity or the address re-quotes.
 * Failures are never cached, with one exception: when Shipbubble times out or
 * cannot be reached, checkout does not call it again for a minute, so an
 * outage costs one timeout per minute rather than one per page.
 */
class QuoteService
{
    /**
     * Shipbubble rejects a rate request without delivery instructions.
     */
    private const DELIVERY_INSTRUCTIONS = 'Online shop order';

    private const TIMEZONE = 'Africa/Lagos';

    private const UNREACHABLE_TTL = 60;

    public function __construct(
        private readonly Config $config,
        private readonly ShipbubbleClient $client,
        private readonly ReceiverAddressService $receiverAddressService,
        private readonly PackageBuilder $packageBuilder,
        private readonly FailureNotifier $failureNotifier,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function quote(Cart $cart, SalesChannelContext $context): QuoteStruct|QuoteError
    {
        $salesChannelId = $context->getSalesChannelId();

        return $this->report(function () use ($cart, $context, $salesChannelId): QuoteStruct|QuoteError {
            // ponytail: also skips quotes already cached; check per cache miss if that costs orders.
            if ($this->wasUnreachable($salesChannelId)) {
                return QuoteError::Unavailable;
            }

            $receiverCode = $this->receiverAddressService->addressCode($context);

            if ($receiverCode instanceof QuoteError) {
                return $receiverCode;
            }

            return $this->rates($receiverCode, $this->packageBuilder->build($cart, $salesChannelId), $context->getCurrency()->getIsoCode(), $salesChannelId, true);
        }, $salesChannelId, $context->getContext());
    }

    /**
     * A fresh quote for a placed order, never from the cache: a cached quote
     * may carry a request token another booking already used (spec §12).
     * Needs the order's `lineItems.product`, `orderCustomer` and `currency`
     * associations and the delivery's shipping address with its country.
     */
    public function requote(OrderEntity $order, OrderAddressEntity $address, Context $context): QuoteStruct|QuoteError
    {
        $salesChannelId = $order->getSalesChannelId();

        return $this->report(function () use ($order, $address, $salesChannelId): QuoteStruct|QuoteError {
            $receiverCode = $this->receiverAddressService->addressCodeFor($address, (string)$order->getOrderCustomer()?->getEmail(), $salesChannelId);

            if ($receiverCode instanceof QuoteError) {
                return $receiverCode;
            }

            return $this->rates($receiverCode, $this->packageBuilder->buildForOrder($order), (string)$order->getCurrency()?->getIsoCode(), $salesChannelId, false);
        }, $salesChannelId, $context);
    }

    /**
     * Runs a quote and logs and reports any failure.
     *
     * @param \Closure(): (QuoteStruct|QuoteError) $fetch
     */
    private function report(\Closure $fetch, string $salesChannelId, Context $context): QuoteStruct|QuoteError
    {
        try {
            $result = $fetch();
        } catch (ShipbubbleApiException $e) {
            $result = QuoteError::fromRateFailure($e->getReason());
            $httpStatus = $e->getHttpStatus();

            if (\in_array($e->getReason(), [ApiErrorReason::Timeout, ApiErrorReason::Network], true)) {
                $this->markUnreachable($salesChannelId);
            }
        }

        if ($result instanceof QuoteError) {
            $this->logger->log($result->notifiesAdmin() ? 'error' : 'notice', 'Shipbubble delivery could not be quoted.', [
                'reason' => $result->value,
                'httpStatus' => $httpStatus ?? null,
                'salesChannelId' => $salesChannelId,
            ]);
            $this->failureNotifier->notify($result, $salesChannelId, $context);
        }

        return $result;
    }

    private function wasUnreachable(string $salesChannelId): bool
    {
        return $this->cache->get('kmh_sb_unreachable_' . $salesChannelId, static function (ItemInterface $item, bool &$save): bool {
            $save = false;

            return false;
        });
    }

    private function markUnreachable(string $salesChannelId): void
    {
        $key = 'kmh_sb_unreachable_' . $salesChannelId;
        $this->cache->delete($key);
        $this->cache->get($key, static function (ItemInterface $item): bool {
            $item->expiresAfter(self::UNREACHABLE_TTL);

            return true;
        });
    }

    /**
     * @throws ShipbubbleApiException
     */
    private function rates(string $receiverCode, Package $package, string $currency, string $salesChannelId, bool $useCache): QuoteStruct|QuoteError
    {
        $senderCode = $this->config->getSenderAddressCode($salesChannelId);

        if ($senderCode === '') {
            return QuoteError::NotConfigured;
        }

        $mode = $this->config->getMode($salesChannelId);
        $couriers = $this->enabledCouriers($salesChannelId);
        $key = 'kmh_sb_quote_' . $salesChannelId . '_' . $mode . '_'
            . hash('sha256', implode('|', [$senderCode, $receiverCode, $package->signature(), $currency, implode(',', $couriers)]));

        $fetch = function () use ($senderCode, $receiverCode, $package, $couriers, $salesChannelId): array {
            $quote = $this->client->rates()->fetch([
                'sender_address_code' => (int)$senderCode,
                'reciever_address_code' => (int)$receiverCode,
                'pickup_date' => (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d'),
                'category_id' => (int)$package->categoryId,
                'package_items' => $package->items,
                'package_dimension' => $package->dimension,
                'delivery_instructions' => self::DELIVERY_INSTRUCTIONS,
            ], $couriers, $salesChannelId);

            return ['quote' => $quote, 'fetchedAt' => new \DateTimeImmutable()];
        };

        /** @var array{quote: RateQuote, fetchedAt: \DateTimeImmutable} $cached */
        $cached = $useCache
            ? $this->cache->get($key, function (ItemInterface $item) use ($fetch, $salesChannelId): array {
                $item->expiresAfter($this->config->getInt('quoteCacheTtl', 1800, $salesChannelId));

                return $fetch();
            })
            : $fetch();

        $rates = $cached['quote']->couriers;

        if ($rates === []) {
            return QuoteError::NoCouriers;
        }

        foreach ($rates as $rate) {
            if ($rate->currency !== $currency) {
                return QuoteError::CurrencyMismatch;
            }
        }

        $options = array_map(fn (CourierRate $rate): CourierOption => new CourierOption(
            $rate->courierId,
            $rate->courierName,
            $rate->serviceCode,
            $rate->total,
            $this->withMarkup($rate->total, $salesChannelId),
            $rate->deliveryEta,
            $rate->courierImage,
        ), $rates);
        usort($options, static fn (CourierOption $a, CourierOption $b): int => $a->price <=> $b->price);

        return new QuoteStruct($options, $cached['quote']->requestToken, $currency, $mode, $cached['fetchedAt']);
    }

    /**
     * @return list<string>
     */
    private function enabledCouriers(?string $salesChannelId): array
    {
        $codes = $this->config->get('enabledCouriers', [], $salesChannelId);
        $codes = is_array($codes) ? array_values(array_filter($codes, is_string(...))) : [];
        sort($codes);

        return $codes;
    }

    private function withMarkup(float $total, ?string $salesChannelId): float
    {
        $value = $this->config->getFloat('markupValue', 0.0, $salesChannelId);

        return match ($this->config->getString('markupType', $salesChannelId)) {
            'fixed' => $total + $value,
            'percent' => $total * (1 + $value / 100),
            default => $total,
        };
    }
}
