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
    public const IS_EXPIRED = 0x64;
    public const BAD_BYTES = 0xc8;
    public const UNSERIALIZE_FAIL = 0x12c;
    public const CHECKSUM_NOT_STORED = 0x190;
    public const BAD_CHECKSUM = 0x1f4;

    /**
     * @param int $code
     * @param string $msg
     * @param \Throwable|null $previous
     */
    public function __construct(int $code = 0, string $msg = "", ?\Throwable $previous = null)
    {
        parent::__construct($msg, $code, $previous);
    }
}
