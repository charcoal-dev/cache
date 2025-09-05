<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Events\Connection;

use Charcoal\Contracts\Storage\Cache\CacheAdapterInterface;

/**
 * Represents a successful state of a connection, implementing the ConnectionStateContext interface.
 * This class is readonly, ensuring immutability of its properties after initialization.
 */
final readonly class ConnectionSuccess implements ConnectionStateContext
{
    public function __construct(
        public CacheAdapterInterface $store
    )
    {
    }
}