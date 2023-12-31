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

/**
 * Class DumbCacheStore
 */
class DumbCacheStore implements \Charcoal\Cache\CacheDriverInterface
{
    private array $items = [];

    public function __construct(public readonly int $salt = 0)
    {
    }

    public function metaUniqueId(): string
    {
        return static::class . "_" . $this->salt;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function connect(): void
    {
    }

    public function disconnect(): void
    {
    }

    public function metaPingSupported(): bool
    {
        return false;
    }

    public function ping(): bool
    {
        return false;
    }

    public function createLink(\Charcoal\Cache\Cache $cache): void
    {
    }

    public function store(string $key, int|string $value, ?int $ttl = null): void
    {
        $this->items[$key] = strval($value);
    }

    public function resolve(string $key): int|string|null|bool
    {
        return $this->items[$key] ?? null;
    }

    public function isStored(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function delete(string $key): bool
    {
        if ($this->isStored($key)) {
            unset($this->items[$key]);
        }

        return false;
    }

    public function truncate(): bool
    {
        $this->items = [];
        return true;
    }
}