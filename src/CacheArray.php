<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache;

use Charcoal\Base\Concerns\InstancedObjectsRegistry;
use Charcoal\Base\Concerns\RegistryKeysLowercaseTrimmed;
use Charcoal\Buffers\Frames\Bytes20;
use Charcoal\Cache\CacheArray\BulkCacheOp;
use Charcoal\Cache\Contracts\CacheApiInterface;
use Charcoal\Cache\Exception\CacheArrayException;
use Charcoal\Cache\Exception\CacheDriverConnectionException;
use Charcoal\Cache\Exception\CacheException;

/**
 * Class CacheArray
 * @package Charcoal\Cache
 * @template-implements InstancedObjectsRegistry<array, CacheClient>
 * @property array<string,CacheClient> $instances
 */
class CacheArray implements \IteratorAggregate, CacheApiInterface
{
    protected ?CacheClient $primary = null;
    protected int $count = 0;

    use InstancedObjectsRegistry;
    use RegistryKeysLowercaseTrimmed;

    public function addServer(CacheClient $cache): static
    {
        if (!isset($this->instances[$cache->storageDriver->metaUniqueId()])) {
            $this->instances[$cache->storageDriver->metaUniqueId()] = $cache;
            $this->count++;
        }

        return $this;
    }

    public function getServer(string $key): ?CacheClient
    {
        return array_key_exists($key, $this->instances) ? $this->instances[$key] : null;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->instances);
    }

    /**
     * @throws \Charcoal\Cache\Exception\CacheArrayException
     */
    protected function getPrimary(): CacheClient
    {
        if ($this->primary) {
            return $this->primary;
        }

        if (!$this->instances) {
            throw new CacheArrayException('No servers added to CacheArray');
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

        throw new CacheArrayException('Could not connect to any Cache server');
    }

    /**
     * @throws CacheArrayException
     */
    protected function primaryServerOp(\Closure $callback): mixed
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
     * @return \Charcoal\Cache\CacheArray\BulkCacheOp
     */
    protected function allServersOp(\Closure $callback): BulkCacheOp
    {
        $result = new BulkCacheOp();
        foreach ($this->instances as $store) {
            $result->total++;

            try {
                $result->successList[$store->storageDriver->metaUniqueId()] = call_user_func_array($callback, [$store]);
                $result->success++;
            } catch (\Exception $e) {
                $result->exceptionsList[$store->storageDriver->metaUniqueId()] = $e;
                $result->exceptions++;
            }
        }

        return $result;
    }

    /**
     * @throws CacheArrayException
     */
    public function set(string $key, mixed $value, ?int $ttl = null, ?bool $createChecksum = null): bool|Bytes20
    {
        return $this->primaryServerOp(function (CacheClient $primary) use ($key, $value, $ttl, $createChecksum) {
            return $primary->set($key, $value, $ttl, $createChecksum);
        });
    }

    public function setToAll(string $key, mixed $value, ?int $ttl = null, ?bool $createChecksum = null): BulkCacheOp
    {
        return $this->allServersOp(function (CacheClient $store) use ($key, $value, $ttl, $createChecksum) {
            return $store->set($key, $value, $ttl, $createChecksum);
        });
    }

    /**
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function get(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $returnReferenceKeyObject = true,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): int|string|null|array|object|bool
    {
        return $this->primaryServerOp(function (CacheClient $primary) use (
            $key,
            $returnCachedEntity,
            $returnReferenceKeyObject,
            $expectInteger,
            $verifyChecksum
        ) {
            return $primary->get($key, $returnCachedEntity, $returnReferenceKeyObject,
                $expectInteger, $verifyChecksum);
        });
    }

    public function getFromAll(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $returnReferenceKeyObject = true,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): BulkCacheOp
    {
        return $this->allServersOp(function (CacheClient $store) use (
            $key,
            $returnCachedEntity,
            $returnReferenceKeyObject,
            $expectInteger,
            $verifyChecksum
        ) {
            return $store->get($key, $returnCachedEntity, $returnReferenceKeyObject,
                $expectInteger, $verifyChecksum);
        });
    }

    /**
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function has(string $key): bool
    {
        return $this->primaryServerOp(function (CacheClient $primary) use ($key) {
            return $primary->has($key);
        });
    }

    public function allHave(string $key): BulkCacheOp
    {
        return $this->allServersOp(function (CacheClient $store) use ($key) {
            return $store->has($key);
        });
    }

    /**
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function delete(string $key): bool
    {
        return $this->primaryServerOp(function (CacheClient $primary) use ($key) {
            return $primary->delete($key);
        });
    }

    public function deleteFromAll(string $key): BulkCacheOp
    {
        return $this->allServersOp(function (CacheClient $store) use ($key) {
            return $store->delete($key);
        });
    }
}
