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

namespace Charcoal\Cache;


use Charcoal\Buffers\Frames\Bytes20;
use Charcoal\Cache\CacheArray\BulkCacheOp;
use Charcoal\Cache\Exception\CacheArrayException;
use Charcoal\Cache\Exception\CacheDriverConnectionException;
use Charcoal\Cache\Exception\CacheException;

/**
 * Class CacheArray
 * @package Charcoal\Cache
 */
class CacheArray implements \IteratorAggregate
{
    protected array $stores = [];
    protected int $count = 0;
    protected ?Cache $primary = null;

    /**
     * @param \Charcoal\Cache\Cache $cache
     * @return $this
     */
    public function addServer(Cache $cache): static
    {
        if (!isset($this->stores[$cache->storageDriver->metaUniqueId()])) {
            $this->stores[$cache->storageDriver->metaUniqueId()] = $cache;
            $this->count++;
        }

        return $this;
    }

    /**
     * @param string $key
     * @return \Charcoal\Cache\Cache|null
     */
    public function getServer(string $key): ?Cache
    {
        return array_key_exists($key, $this->stores) ? $this->stores[$key] : null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->stores);
    }

    /**
     * @return \Charcoal\Cache\Cache
     * @throws \Charcoal\Cache\Exception\CacheArrayException
     */
    protected function getPrimary(): Cache
    {
        if ($this->primary) {
            return $this->primary;
        }

        if (!$this->stores) {
            throw new CacheArrayException('No servers added to CacheArray');
        }

        /** @var \Charcoal\Cache\Cache $store */
        foreach ($this->stores as $store) {
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
     * @param \Closure $callback
     * @return mixed
     * @throws \Charcoal\Cache\Exception\CacheException
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
        /** @var \Charcoal\Cache\Cache $store */
        foreach ($this->stores as $store) {
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
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param bool|null $createChecksum
     * @return bool|\Charcoal\Buffers\Frames\Bytes20
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function set(string $key, mixed $value, ?int $ttl = null, ?bool $createChecksum = null): bool|Bytes20
    {
        return $this->primaryServerOp(function (Cache $primary) use ($key, $value, $ttl, $createChecksum) {
            return $primary->set($key, $value, $ttl, $createChecksum);
        });
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param bool|null $createChecksum
     * @return \Charcoal\Cache\CacheArray\BulkCacheOp
     */
    public function setToAll(string $key, mixed $value, ?int $ttl = null, ?bool $createChecksum = null): BulkCacheOp
    {
        return $this->allServersOp(function (Cache $store) use ($key, $value, $ttl, $createChecksum) {
            return $store->set($key, $value, $ttl, $createChecksum);
        });
    }

    /**
     * @param string $key
     * @param bool $returnCachedEntity
     * @param bool $expectInteger
     * @param bool|null $verifyChecksum
     * @return int|string|array|object|bool|null
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function get(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): int|string|null|array|object|bool
    {
        return $this->primaryServerOp(function (Cache $primary) use ($key, $returnCachedEntity, $expectInteger, $verifyChecksum) {
            return $primary->get($key, $returnCachedEntity, $expectInteger, $verifyChecksum);
        });
    }

    /**
     * @param string $key
     * @param bool $returnCachedEntity
     * @param bool $expectInteger
     * @param bool|null $verifyChecksum
     * @return \Charcoal\Cache\CacheArray\BulkCacheOp
     */
    public function getFromAll(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): BulkCacheOp
    {
        return $this->allServersOp(function (Cache $store) use ($key, $returnCachedEntity, $expectInteger, $verifyChecksum) {
            return $store->get($key, $returnCachedEntity, $expectInteger, $verifyChecksum);
        });
    }

    /**
     * @param string $key
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function has(string $key): bool
    {
        return $this->primaryServerOp(function (Cache $primary) use ($key) {
            return $primary->has($key);
        });
    }

    /**
     * @param string $key
     * @return \Charcoal\Cache\CacheArray\BulkCacheOp
     */
    public function allHave(string $key): BulkCacheOp
    {
        return $this->allServersOp(function (Cache $store) use ($key) {
            return $store->has($key);
        });
    }

    /**
     * @param string $key
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function delete(string $key): bool
    {
        return $this->primaryServerOp(function (Cache $primary) use ($key) {
            return $primary->delete($key);
        });
    }

    /**
     * @param string $key
     * @return \Charcoal\Cache\CacheArray\BulkCacheOp
     */
    public function deleteFromAll(string $key): BulkCacheOp
    {
        return $this->allServersOp(function (Cache $store) use ($key) {
            return $store->delete($key);
        });
    }
}
