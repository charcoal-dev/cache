<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Contracts;

use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Exceptions\CacheDriverConnectionException;
use Charcoal\Cache\Exceptions\CacheDriverException;

/**
 * Interface CacheDriverInterface
 * @package Charcoal\Cache\Contracts
 */
interface CacheDriverInterface
{
    public function createLink(CacheClient $cache): void;

    public function isConnected(): bool;

    /**
     * @throws CacheDriverConnectionException
     */
    public function connect(): void;

    public function disconnect(): void;

    public function metaUniqueId(): string;

    public function metaPingSupported(): bool;

    public function ping(): bool;

    public function store(string $key, int|string $value, ?int $ttl = null): void;

    public function resolve(string $key): int|string|null|bool;

    public function isStored(string $key): bool;

    /**
     * @throws CacheDriverException
     */
    public function delete(string $key): bool;

    public function truncate(): bool;
}