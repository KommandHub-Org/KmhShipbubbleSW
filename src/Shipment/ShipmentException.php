<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipment;

use Kommandhub\ShipbubbleSW\Exception\ShipbubbleException;
use Kommandhub\ShipbubbleSW\Quote\QuoteError;

/**
 * A shipment action that cannot run in the shipment's current state, or a
 * re-quote that failed. The message is safe to show in the Administration.
 */
class ShipmentException extends ShipbubbleException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('not_found', 'The Shipbubble shipment does not exist.');
    }

    public static function notBookable(string $status): self
    {
        return new self('not_bookable', sprintf('A shipment with status "%s" cannot be created again.', $status));
    }

    public static function notBooked(): self
    {
        return new self('not_booked', 'The shipment has not been created at Shipbubble yet.');
    }

    public static function notCancellable(string $status): self
    {
        return new self('not_cancellable', sprintf('A shipment with status "%s" cannot be cancelled.', $status));
    }

    public static function quoteFailed(QuoteError $error): self
    {
        return new self('quote_' . $error->value, sprintf('Shipbubble could not quote this delivery again (%s).', $error->value));
    }
}
