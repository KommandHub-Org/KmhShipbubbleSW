<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Quote;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Stub;

/**
 * `kmh_shipbubble_address` in memory: a Connection stub that answers the
 * SELECT, INSERT and DELETE of ReceiverAddressService from an array.
 */
final class AddressTable
{
    /**
     * @var array<string, string> address code by sales channel, mode and hash
     */
    public array $rows = [];

    /**
     * @param Connection&Stub $connection a fresh `createStub(Connection::class)`
     */
    public function connect(Connection&Stub $connection): Connection
    {
        $table = $this;
        $key = static fn (array $params): string => bin2hex($params['salesChannelId']) . '|' . $params['mode'] . '|' . $params['hash'];
        $connection->method('fetchOne')->willReturnCallback(static fn (string $sql, array $params): string|false => $table->rows[$key($params)] ?? false);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql, array $params) use ($table, $key): int {
            if (str_starts_with(trim($sql), 'DELETE')) {
                unset($table->rows[$key($params)]);
            } else {
                $table->rows[$key($params)] = $params['code'];
            }

            return 1;
        });

        return $connection;
    }
}
