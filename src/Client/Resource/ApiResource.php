<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Resource;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Http\HttpClientInterface;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Base class for one Shipbubble API endpoint group.
 *
 * One subclass per endpoint group, each method mapping to a single documented
 * call. Nothing outside Client/ should build a URL by hand.
 */
abstract class ApiResource
{
    public function __construct(protected HttpClientInterface $httpClient)
    {
    }

    /**
     * Reads a response and returns its `data` member.
     *
     * Shipbubble wraps everything in `{status, message, data, errors}`. Any
     * failure (transport fault, timeout, HTTP error, `"status": "failed"`,
     * undecodable body) becomes a ShipbubbleApiException with a reason; a
     * success returns `data` as an array (empty when absent or not an array).
     *
     * @return array<mixed>
     *
     * @throws ShipbubbleApiException
     */
    protected function data(ResponseInterface $response): array
    {
        try {
            $status = $response->getStatusCode();
        } catch (TimeoutExceptionInterface $e) {
            throw new ShipbubbleApiException(ApiErrorReason::Timeout, 'Shipbubble did not answer in time.', null, [], $e);
        } catch (TransportExceptionInterface $e) {
            throw new ShipbubbleApiException(ApiErrorReason::Network, 'Shipbubble could not be reached.', null, [], $e);
        }

        try {
            $body = $response->toArray(false);
        } catch (\Throwable $e) {
            if ($status >= 400) {
                throw ShipbubbleApiException::fromResponse($status, []);
            }

            throw new ShipbubbleApiException(ApiErrorReason::InvalidResponse, 'Shipbubble returned an unreadable response.', $status, [], $e);
        }

        if ($status >= 400 || ($body['status'] ?? null) === 'failed') {
            throw ShipbubbleApiException::fromResponse($status, $body);
        }

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }
}
