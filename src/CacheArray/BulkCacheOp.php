<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\CacheArray;

/**
 * Class BulkCacheOp
 * @package Charcoal\Cache\CacheArray
 */
class BulkCacheOp
{
    /**
     * @param int $total
     * @param int $success
     * @param int $exceptions
     * @param array $successList
     * @param array $exceptionsList
     */
    public function __construct(
        public int   $total = 0,
        public int   $success = 0,
        public int   $exceptions = 0,
        public array $successList = [],
        public array $exceptionsList = [],
    )
    {
    }
}

