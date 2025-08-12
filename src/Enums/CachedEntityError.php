<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Enums;

/**
 * Class CachedEntityError
 * @package Charcoal\Cache\Enums
 */
enum CachedEntityError: int
{
    case IS_EXPIRED = 0x64;
    case BAD_BYTES = 0xc8;
    case UNSERIALIZE_FAIL = 0x12c;
    case CHECKSUM_NOT_STORED = 0x190;
    case BAD_CHECKSUM = 0x1f4;
    case REF_KEY_LENGTH = 0x258;
    case REF_DECODE_ERROR = 0x2bc;
    case REF_NOT_OBJECT = 0x320;
    case REF_BAD_CHECKSUM = 0x384;
}