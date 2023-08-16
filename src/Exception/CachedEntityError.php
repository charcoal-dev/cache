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
 * Class CachedEntityError
 * @package Charcoal\Cache\Exception
 */
enum CachedEntityError: int
{
    case IS_EXPIRED = 0x64;
    case BAD_BYTES = 0xc8;
    case UNSERIALIZE_FAIL = 0x12c;
    case CHECKSUM_NOT_STORED = 0x190;
    case BAD_CHECKSUM = 0x1f4;
}