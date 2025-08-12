<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Contracts;

use Charcoal\Buffers\Frames\Bytes20;

/**
 * Interface CacheApiInterface
 * @package Charcoal\Cache\Contracts
 */
interface CacheApiInterface
{
    public function set(string $key, mixed $value, ?int $ttl = null, ?bool $createChecksum = null): bool|Bytes20;

    public function get(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $returnReferenceKeyObject = true,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): int|string|null|array|object|bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;
}
