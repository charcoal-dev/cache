<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache;

/**
 * Interface CacheDriverInterface
 * @package Charcoal\Cache
 */
interface CacheDriverInterface
{
    public function createLink(CacheClient $cache): void;

    public function isConnected(): bool;

    public function connect(): void;

    public function disconnect(): void;

    public function metaUniqueId(): string;

    public function metaPingSupported(): bool;

    public function ping(): bool;

    public function store(string $key, int|string $value, ?int $ttl = null): void;

    public function resolve(string $key): int|string|null|bool;

    public function isStored(string $key): bool;

    public function delete(string $key): bool;

    public function truncate(): bool;
}