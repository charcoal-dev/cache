<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests\Stubs;

use Charcoal\Contracts\Storage\Cache\CacheAdapterInterface;
use Charcoal\Contracts\Storage\Cache\CacheClientInterface;

/**
 * LocalCache is an implementation of the CacheAdapterInterface designed for caching data locally.
 * This class manages a collection of cached items and handles basic operations such as storing,
 * retrieving, and deleting cached data.
 */
class LocalCache implements CacheAdapterInterface
{
    private array $items = [];

    public function __construct(public readonly int $salt = 0)
    {
    }

    public function getId(): string
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

    public function supportsPing(): bool
    {
        return false;
    }

    public function ping(): bool
    {
        return false;
    }

    public function createLink(CacheClientInterface $cache): void
    {
    }

    public function set(string $key, int|string $value, ?int $ttl = null): void
    {
        $this->items[$key] = strval($value);
    }

    public function get(string $key): int|string|null|bool
    {
        return $this->items[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function delete(string $key): bool
    {
        if ($this->has($key)) {
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