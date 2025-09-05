<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Pool;

use Charcoal\Base\Registry\Traits\InstancedObjectsRegistry;
use Charcoal\Base\Registry\Traits\RegistryKeysLowercaseTrimmed;
use Charcoal\Buffers\Types\Bytes20;
use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Exceptions\CachePoolException;
use Charcoal\Cache\Exceptions\CacheDriverConnectionException;
use Charcoal\Cache\Exceptions\CacheException;
use Charcoal\Contracts\Storage\Cache\CacheClientInterface;
use Charcoal\Contracts\Storage\Enums\StorageType;

/**
 * This class implements a cache pooling system that manages multiple cache clients.
 * It allows adding, retrieving, and performing operations on cache clients under a collective or individual basis.
 * @use InstancedObjectsRegistry<array, CacheClient>
 */
final class CachePool implements \IteratorAggregate, CacheClientInterface
{
    protected ?CacheClient $primary = null;
    protected int $count = 0;

    use InstancedObjectsRegistry;
    use RegistryKeysLowercaseTrimmed;

    /**
     * @param string $poolId
     */
    public function __construct(public readonly string $poolId)
    {
    }

    /**
     * Add a cache client to the pool.
     */
    public function addServer(CacheClient $cache): self
    {
        if (isset($this->instances[$cache->storageDriver->getId()])) {
            $this->instances[$cache->storageDriver->getId()] = $cache;
            $this->count++;
        }

        return $this;
    }

    /**
     * Get a cache client from the pool.
     */
    public function getServer(string $key): ?CacheClient
    {
        return array_key_exists($key, $this->instances) ? $this->instances[$key] : null;
    }

    /**
     * Number of cache clients in the pool.
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return \ArrayIterator<string,CacheClient>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->instances);
    }

    /**
     * Returns the primary cache client.
     * @throws CachePoolException
     */
    private function getPrimary(): CacheClient
    {
        if ($this->primary) {
            return $this->primary;
        }

        if (!$this->instances) {
            throw new CachePoolException("No servers added to CachePool");
        }

        foreach ($this->instances as $store) {
            if (!$store->isConnected()) {
                try {
                    $store->connect();
                } catch (CacheDriverConnectionException) {
                }
            }

            if ($store->isConnected()) {
                $this->primary = $store;
                return $this->primary;
            }
        }

        throw new CachePoolException("Could not connect to any Cache server");
    }

    /**
     * @throws CachePoolException
     */
    private function primaryServerOp(\Closure $callback): mixed
    {
        try {
            return call_user_func_array($callback, [$this->getPrimary()]);
        } catch (CacheException $e) {
            $this->primary = null;
            throw $e;
        }
    }

    /**
     * @param \Closure $callback
     * @return BulkCacheOp
     */
    private function bulkOp(\Closure $callback): BulkCacheOp
    {
        $success = [];
        $errors = [];
        foreach ($this->instances as $store) {
            try {
                $success[$store->storageDriver->metaUniqueId()] = call_user_func_array($callback, [$store]);
            } catch (\Exception $e) {
                $errors[$store->storageDriver->metaUniqueId()] = $e;
            }
        }

        return new BulkCacheOp($success, $errors);
    }

    /**
     * Set the value for the specified key in the primary cache store.
     * @throws CachePoolException
     */
    public function set(string $key, mixed $value, ?int $ttl = null, ?bool $withChecksum = null): bool|Bytes20
    {
        return $this->primaryServerOp(function (CacheClient $primary) use ($key, $value, $ttl, $withChecksum) {
            return $primary->set($key, $value, $ttl, $withChecksum);
        });
    }

    /**
     * Set the value for the specified key in all cache stores.
     */
    public function setToAll(string $key, mixed $value, ?int $ttl = null, ?bool $withChecksum = null): BulkCacheOp
    {
        return $this->bulkOp(function (CacheClient $store) use ($key, $value, $ttl, $withChecksum) {
            return $store->set($key, $value, $ttl, $withChecksum);
        });
    }

    /**
     * Get the value for the specified key from the primary cache store.
     * @throws CachePoolException
     */
    public function get(
        string $key,
        bool   $returnEnvelope = false,
        bool   $returnReferenceKeyObject = true,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): int|string|null|array|object|bool
    {
        return $this->primaryServerOp(function (CacheClient $primary) use (
            $key,
            $returnEnvelope,
            $returnReferenceKeyObject,
            $expectInteger,
            $verifyChecksum
        ) {
            return $primary->get(
                $key,
                $returnEnvelope,
                $returnReferenceKeyObject,
                $expectInteger,
                $verifyChecksum);
        });
    }

    /**
     * Get the value for the specified key from all cache stores.
     */
    public function getFromAll(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $returnReferenceKeyObject = true,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): BulkCacheOp
    {
        return $this->bulkOp(function (CacheClient $store) use (
            $key,
            $returnCachedEntity,
            $returnReferenceKeyObject,
            $expectInteger,
            $verifyChecksum
        ) {
            return $store->get(
                $key,
                $returnCachedEntity,
                $returnReferenceKeyObject,
                $expectInteger,
                $verifyChecksum
            );
        });
    }

    /**
     * Checks if the specified key exists in the primary cache store.
     * @throws \Charcoal\Cache\Exceptions\CacheException
     */
    public function has(string $key): bool
    {
        return $this->primaryServerOp(function (CacheClient $primary) use ($key) {
            return $primary->has($key);
        });
    }

    /**
     * Checks if the specified key exists in all cache stores.
     */
    public function haveInAll(string $key): BulkCacheOp
    {
        return $this->bulkOp(function (CacheClient $store) use ($key) {
            return $store->has($key);
        });
    }

    /**
     * Deletes the specified key from the primary cache store.
     * @throws CachePoolException
     */
    public function delete(string $key): bool
    {
        return $this->primaryServerOp(function (CacheClient $primary) use ($key) {
            return $primary->delete($key);
        });
    }

    /**
     * Deletes the specified key from all cache stores.
     */
    public function deleteFromAll(string $key): BulkCacheOp
    {
        return $this->bulkOp(function (CacheClient $store) use ($key) {
            return $store->delete($key);
        });
    }

    /**
     * Returns the storage type for the cache pool.
     */
    public function storageType(): StorageType
    {
        return StorageType::Cache;
    }

    /**
     * Returns the storage provider ID for the cache pool.
     */
    public function storageProviderId(): string
    {
        return "pool:" . $this->poolId;
    }
}
