<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache;

use Charcoal\Buffers\Frames\Bytes20;

/**
 * Interface CacheApiInterface
 * @package Charcoal\Cache
 */
interface CacheApiInterface
{
    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param bool|null $createChecksum
     * @return bool|\Charcoal\Buffers\Frames\Bytes20
     */
    public function set(string $key, mixed $value, ?int $ttl = null, ?bool $createChecksum = null): bool|Bytes20;

    /**
     * @param string $key
     * @param bool $returnCachedEntity
     * @param bool $returnReferenceKeyObject
     * @param bool $expectInteger
     * @param bool|null $verifyChecksum
     * @return int|string|array|object|bool|null
     */
    public function get(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $returnReferenceKeyObject = true,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): int|string|null|array|object|bool;

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool;
}
