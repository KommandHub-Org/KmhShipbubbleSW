<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Kommandhub\ShipbubbleSW\Client\Dto\CourierRate;
use Kommandhub\ShipbubbleSW\Client\Dto\RateQuote;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Prices Shipbubble delivery for a cart (spec §1).
 *
 * Quotes are cached per receiver, parcel and currency, so reloading checkout
 * never calls Shipbubble while changing a quantity or the address re-quotes.
 * Failures are never cached.
 */
class QuoteService
{
    /**
     * Shipbubble rejects a rate request without delivery instructions.
     */
    private const DELIVERY_INSTRUCTIONS = 'Online shop order';

    private const TIMEZONE = 'Africa/Lagos';

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

        try {
            $result = $this->fetch($cart, $context);
        } catch (ShipbubbleApiException $e) {
            $result = QuoteError::fromRateFailure($e->getReason());
            $httpStatus = $e->getHttpStatus();
        }

        if ($result instanceof QuoteError) {
            $this->logger->log($result->notifiesAdmin() ? 'error' : 'notice', 'Shipbubble delivery could not be quoted.', [
                'reason' => $result->value,
                'httpStatus' => $httpStatus ?? null,
                'salesChannelId' => $salesChannelId,
            ]);
            $this->failureNotifier->notify($result, $salesChannelId, $context->getContext());
        }

        return $result;
    }

    /**
     * @throws ShipbubbleApiException
     */
    private function fetch(Cart $cart, SalesChannelContext $context): QuoteStruct|QuoteError
    {
        $salesChannelId = $context->getSalesChannelId();
        $senderCode = $this->config->getSenderAddressCode($salesChannelId);

        if ($senderCode === '') {
            return QuoteError::NotConfigured;
        }

        $receiverCode = $this->receiverAddressService->addressCode($context);

        if ($receiverCode instanceof QuoteError) {
            return $receiverCode;
        }

        $mode = $this->config->getMode($salesChannelId);
        $package = $this->packageBuilder->build($cart, $salesChannelId);
        $couriers = $this->enabledCouriers($salesChannelId);
        $currency = $context->getCurrency()->getIsoCode();
        $key = 'kmh_sb_quote_' . $salesChannelId . '_' . $mode . '_'
            . hash('sha256', implode('|', [$senderCode, $receiverCode, $package->signature(), $currency, implode(',', $couriers)]));

        /** @var array{quote: RateQuote, fetchedAt: \DateTimeImmutable} $cached */
        $cached = $this->cache->get($key, function (ItemInterface $item) use ($senderCode, $receiverCode, $package, $couriers, $salesChannelId): array {
            $item->expiresAfter($this->config->getInt('quoteCacheTtl', 1800, $salesChannelId));

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
        });

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
