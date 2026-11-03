<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Shopware\Core\Framework\Struct\Struct;

/**
 * One courier the customer can pick in checkout, priced for this cart.
 *
 * `carrierTotal` is what Shipbubble charges the merchant; `price` is what the
 * customer pays (carrier total plus markup).
 */
final class CourierOption extends Struct
{
    public function __construct(
        public readonly string $courierId,
        public readonly string $courierName,
        public readonly string $serviceCode,
        public readonly float $carrierTotal,
        public readonly float $price,
        public readonly string $deliveryEta,
        public readonly ?string $courierImage,
    ) {
    }

    /**
     * The value the checkout form posts and the context stores (spec §1).
     */
    public function getKey(): string
    {
        return $this->courierId . ':' . $this->serviceCode;
    }
}
