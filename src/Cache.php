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
class Cache implements CacheApiInterface
{
    public readonly Events $events;
    public readonly int $serializePrefixLen;

    /**
     * @param \Charcoal\Cache\CacheDriverInterface $storageDriver
     * @param bool $useChecksumsByDefault
     * @param bool $nullIfExpired
     * @param bool $deleteIfExpired
     * @param string $serializedEntityPrefix
     * @param string $referenceKeysPrefix
     * @param int $plainStringsMaxLength
     */
    public function __construct(
        public readonly CacheDriverInterface $storageDriver,
        public bool                          $useChecksumsByDefault = false,
        public bool                          $nullIfExpired = true,
        public bool                          $deleteIfExpired = true,
        public readonly string               $serializedEntityPrefix = "~~charcoalCacheSerializedItem",
        public readonly string               $referenceKeysPrefix = "~~charcoalCachedRef",
        public readonly int                  $plainStringsMaxLength = 0x80
    )
    {
        $this->serializePrefixLen = strlen($this->serializedEntityPrefix);
        $this->events = new Events();
        $this->storageDriver->createLink($this);
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            "storageDriver" => $this->storageDriver,
            "useChecksumsByDefault" => $this->useChecksumsByDefault,
            "nullIfExpired" => $this->nullIfExpired,
            "deleteIfExpired" => $this->deleteIfExpired,
            "serializedEntityPrefix" => $this->serializedEntityPrefix,
            "referenceKeysPrefix" => $this->referenceKeysPrefix,
            "plainStringsMaxLength" => $this->plainStringsMaxLength,
            "serializePrefixLen" => $this->serializePrefixLen,
            "events" => $this->events,
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->storageDriver = $data["storageDriver"];
        $this->useChecksumsByDefault = $data["useChecksumsByDefault"];
        $this->nullIfExpired = $data["nullIfExpired"];
        $this->deleteIfExpired = $data["deleteIfExpired"];
        $this->serializedEntityPrefix = $data["serializedEntityPrefix"];
        $this->referenceKeysPrefix = $data["referenceKeysPrefix"];
        $this->plainStringsMaxLength = $data["plainStringsMaxLength"];
        $this->serializePrefixLen = $data["serializePrefixLen"];
        $this->events = $data["events"];
        $this->storageDriver->createLink($this);
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
     * @param string $referenceKey
     * @param string $targetKey
     * @param int|null $ttl
     * @param \Charcoal\Cache\Cache|null $targetKeyServer
     * @param \Charcoal\Buffers\Frames\Bytes20|null $checksum
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function createReferenceKey(
        string   $referenceKey,
        string   $targetKey,
        ?int     $ttl = null,
        ?Cache   $targetKeyServer = null,
        ?Bytes20 $checksum = null
    ): bool
    {
        return (bool)$this->set(
            $referenceKey,
            CachedReferenceKey::Serialize($this, $targetKey, $targetKeyServer, $checksum),
            $ttl,
            false
        );
    }

    /**
     * @param string $key
     * @param bool $returnCachedEntity
     * @param bool $returnReferenceKeyObject
     * @param bool $expectInteger
     * @param bool|null $verifyChecksum
     * @return int|string|array|object|bool|null
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function get(
        string $key,
        bool   $returnCachedEntity = false,
        bool   $returnReferenceKeyObject = true,
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
            if (is_string($stored) && str_starts_with($stored, $this->referenceKeysPrefix) && $returnReferenceKeyObject) {
                return CachedReferenceKey::Unserialize($this, $stored);
            }

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

    /**
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function ping(): bool
    {
        if (!$this->storageDriver->metaPingSupported()) {
            return false;
        }

        return $this->storageDriver->ping();
    }
}

