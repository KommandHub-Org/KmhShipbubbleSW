<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client;

/**
 * Why a Shipbubble call failed, in terms the plugin can act on.
 *
 * Callers branch on this (hide the method, show the merchant a reason, retry),
 * never on HTTP codes or message strings.
 */
enum ApiErrorReason: string
{
    case NotConfigured = 'not_configured';
    case Unauthorized = 'unauthorized';
    case BadRequest = 'bad_request';
    case InsufficientBalance = 'insufficient_balance';
    case NotFound = 'not_found';
    case Validation = 'validation';
    case RateLimited = 'rate_limited';
    case CourierUnavailable = 'courier_unavailable';
    case ServerError = 'server_error';
    case Timeout = 'timeout';
    case Network = 'network';
    case InvalidResponse = 'invalid_response';

    /**
     * Shipbubble answers 400 both for malformed requests and for account
     * problems (missing KYC, empty wallet); only the message tells them apart.
     */
    public static function fromHttpStatus(int $status, string $message = ''): self
    {
        return match (true) {
            $status === 400 && stripos($message, 'balance') !== false => self::InsufficientBalance,
            $status === 400 => self::BadRequest,
            $status === 401, $status === 403 => self::Unauthorized,
            $status === 404 => self::NotFound,
            $status === 422 => self::Validation,
            $status === 429 => self::RateLimited,
            $status === 503 => self::CourierUnavailable,
            $status >= 500 => self::ServerError,
            default => self::BadRequest,
        };
    }

    /**
     * Transient failures are worth retrying later and must not be cached as a
     * definitive answer.
     */
    public function isTransient(): bool
    {
        return match ($this) {
            self::RateLimited, self::CourierUnavailable, self::ServerError, self::Timeout, self::Network => true,
            default => false,
        };
    }
}
