<?php
/*
 * This file is a part of "charcoal-dev/cache" package.
 * https://github.com/charcoal-dev/cache
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/charcoal-dev/cache/blob/master/LICENSE
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

