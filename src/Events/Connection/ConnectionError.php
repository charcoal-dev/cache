<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Events\Connection;

/**
 * Class ConnectionSuccess
 * @package Charcoal\Cache\Events\Connection
 */
readonly class ConnectionError implements ConnectionStateContext
{
    public function __construct(
        public \Throwable $exception
    )
    {
    }
}