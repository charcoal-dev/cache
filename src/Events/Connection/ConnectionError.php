<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Events\Connection;

use Charcoal\Contracts\Storage\Cache\CacheAdapterInterface;

/**
 * Represents a state where a connection error has occurred.
 * Implements the ConnectionStateContext interface to handle actions specific to this state.
 */
final readonly class ConnectionError implements ConnectionStateContext
{
    public function __construct(
        public CacheAdapterInterface $store,
        public \Throwable            $exception,
    )
    {
    }
}