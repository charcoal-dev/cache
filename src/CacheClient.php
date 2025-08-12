<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache;

use Charcoal\Base\Contracts\Storage\StorageProviderInterface;
use Charcoal\Base\Enums\StorageType;
use Charcoal\Buffers\Frames\Bytes20;
use Charcoal\Cache\Contracts\CacheApiInterface;
use Charcoal\Cache\Contracts\CacheDriverInterface;
use Charcoal\Cache\Enums\CachedEntityError;
use Charcoal\Cache\Exception\CachedEntityException;
use Charcoal\Cache\Exception\CacheDriverException;

/**
 * Class CacheClient
 * @package Charcoal\Cache
 */
class CacheClient implements CacheApiInterface, StorageProviderInterface
{
    public readonly Events $events;
    public readonly int $serializePrefixLen;

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
     * @throws CachedEntityException
     */
    public function createReferenceKey(
        string       $referenceKey,
        string       $targetKey,
        ?int         $ttl = null,
        ?CacheClient $targetKeyServer = null,
        ?Bytes20     $checksum = null
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
     * @throws CachedEntityException
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

    public function isConnected(): bool
    {
        return $this->storageDriver->isConnected();
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $this->storageDriver->connect();
    }

    public function disconnect(): void
    {
        $this->storageDriver->disconnect();
    }

    /**
     * @throws CacheDriverException
     */
    public function delete(string $key): bool
    {
        return $this->storageDriver->delete($key);
    }

    public function flush(): bool
    {
        return $this->storageDriver->truncate();
    }

    public function has(string $key): bool
    {
        return $this->storageDriver->isStored($key);
    }

    public function ping(): bool
    {
        if (!$this->storageDriver->metaPingSupported()) {
            return false;
        }

        return $this->storageDriver->ping();
    }

    public function storageType(): StorageType
    {
        return StorageType::CACHE;
    }

    public function storageProviderId(): string
    {
        return $this->storageDriver->metaUniqueId();
    }
}

