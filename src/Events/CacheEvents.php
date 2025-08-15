<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Events;

use Charcoal\Cache\CacheClient;

/**
 * Class CacheEvents
 * @package Charcoal\Cache\Events
 */
readonly class CacheEvents
{
    public ConnectionEvent $connectionState;

    /**
     * @param CacheClient $cache
     * @param bool $staticScopeReplaceExisting
     */
    public function __construct(CacheClient $cache, bool $staticScopeReplaceExisting)
    {
        $this->connectionState = new ConnectionEvent($cache, $staticScopeReplaceExisting);
    }
}