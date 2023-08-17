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
use Charcoal\Cache\Exception\CachedEntityError;
use Charcoal\Cache\Exception\CachedEntityException;
use Charcoal\Cache\Exception\CacheDriverException;

/**
 * Class Cache
 * @package Charcoal\Cache
 */
class Cache
{
    public readonly int $serializePrefixLen;

    /**
     * @param \Charcoal\Cache\CacheDriverInterface $storageDriver
     * @param bool $useChecksumsByDefault
     * @param bool $nullIfExpired
     * @param bool $deleteIfExpired
     * @param string $serializedEntityPrefix
     * @param int $plainStringsMaxLength
     */
    public function __construct(
        public readonly CacheDriverInterface $storageDriver,
        public bool                          $useChecksumsByDefault = false,
        public bool                          $nullIfExpired = true,
        public bool                          $deleteIfExpired = true,
        public readonly string               $serializedEntityPrefix = "~~charcoalCacheSerializedItem",
        public readonly int                  $plainStringsMaxLength = 0x80
    )
    {
        $this->serializePrefixLen = strlen($this->serializedEntityPrefix);
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
        $value = CachedEntity::Prepare($this, $key, $value, $createChecksum ?? $this->useChecksumsByDefault, $ttl);
        if ($value instanceof CachedEntity) {
            $checksum = $value->checksum;
            $value = CachedEntity::Serialize($this, $value);
        }

        $this->storageDriver->store($key, $value, $ttl);
        return $checksum ?? true;
    }

    /**
     * @param string $key
     * @param bool $returnCachedEntity
     * @param bool $expectInteger
     * @param bool|null $verifyChecksum
     * @return int|string|array|object|bool|null
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function get(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): int|string|null|array|object|bool
    {
        $stored = $this->storageDriver->resolve($key);
        if (!is_string($stored)) {
            return $stored;
        }

        $stored = CachedEntity::Restore($this, $stored, $expectInteger);
        if (!$stored instanceof CachedEntity) {
            return $stored;
        }

        if ($returnCachedEntity) {
            return $stored;
        }

        if (!is_bool($verifyChecksum)) {
            $verifyChecksum = isset($stored->checksum) || $this->useChecksumsByDefault;
        }

        if ($verifyChecksum) {
            $stored->verifyChecksum();
        }

        try {
            return $stored->getStoredItem();
        } catch (CachedEntityException $e) {
            if ($e->error === CachedEntityError::IS_EXPIRED) {
                if ($this->deleteIfExpired) {
                    try {
                        $this->delete($key);
                    } catch (CacheDriverException) {
                    }
                }

                if ($this->nullIfExpired) {
                    return null;
                }
            }

            throw $e;
        }
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->storageDriver->isConnected();
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverConnectionException
     */
    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $this->storageDriver->connect();
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        $this->storageDriver->disconnect();
    }

    /**
     * @param string $key
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function delete(string $key): bool
    {
        return $this->storageDriver->delete($key);
    }

    /**
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function flush(): bool
    {
        return $this->storageDriver->truncate();
    }

    /**
     * @param string $key
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function has(string $key): bool
    {
        return $this->storageDriver->isStored($key);
    }
}

