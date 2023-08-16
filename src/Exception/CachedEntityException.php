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

namespace Charcoal\Cache\Exception;

/**
 * Class CachedEntityException
 * @package Charcoal\Cache\Exception
 */
class CachedEntityException extends CacheException
{
    /**
     * @param \Charcoal\Cache\Exception\CachedEntityError $error
     * @param string $msg
     * @param \Throwable|null $previous
     */
    public function __construct(
        public readonly CachedEntityError $error,
        string                            $msg = "",
        ?\Throwable                       $previous = null
    )
    {
        parent::__construct($msg, $this->error->value, $previous);
    }
}
