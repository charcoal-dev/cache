<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache;

use Charcoal\Buffers\Types\Bytes20;
use Charcoal\Cache\Enums\CachedEntityError;
use Charcoal\Cache\Events\CacheEvents;
use Charcoal\Cache\Exceptions\CachedEnvelopeException;
use Charcoal\Cache\Exceptions\CacheStoreConnectionException;
use Charcoal\Cache\Exceptions\CacheStoreException;
use Charcoal\Cache\Exceptions\CacheStoreOpException;
use Charcoal\Cache\Stored\CachedEnvelope;
use Charcoal\Cache\Stored\CachedReferenceKey;
use Charcoal\Contracts\Storage\Cache\CacheAdapterInterface;
use Charcoal\Contracts\Storage\Cache\CacheClientInterface;
use Charcoal\Contracts\Storage\Enums\StorageType;
use Charcoal\Events\Contracts\EventStoreOwnerInterface;

/**
 * Implements caching functionalities with support for storage management, event handling,
 * and cached entity management. This class interacts with the underlying storage driver
 * to provide efficient caching operations, reference key management, and checksum validation.
 */
class CacheClient implements CacheClientInterface, EventStoreOwnerInterface
{
    public readonly CacheEvents $events;
    public readonly int $serializePrefixLen;

    public function __construct(
        public readonly CacheAdapterInterface $store,
        public bool                           $useChecksumsByDefault = false,
        public bool                           $nullIfExpired = true,
        public bool                           $deleteIfExpired = true,
        public readonly string                $serializedEntityPrefix = "~~charcoalCacheSerializedItem",
        public readonly string                $referenceKeysPrefix = "~~charcoalCachedRef",
        public readonly int                   $plainStringsMaxLength = 0x80,
        bool                                  $staticScopeReplaceExisting = false
    )
    {
        $this->serializePrefixLen = strlen($this->serializedEntityPrefix);
        $this->store->createLink($this);
        $this->events = new CacheEvents($this, $staticScopeReplaceExisting);
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            "store" => $this->store,
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
        $this->store = $data["store"];
        $this->useChecksumsByDefault = $data["useChecksumsByDefault"];
        $this->nullIfExpired = $data["nullIfExpired"];
        $this->deleteIfExpired = $data["deleteIfExpired"];
        $this->serializedEntityPrefix = $data["serializedEntityPrefix"];
        $this->referenceKeysPrefix = $data["referenceKeysPrefix"];
        $this->plainStringsMaxLength = $data["plainStringsMaxLength"];
        $this->serializePrefixLen = $data["serializePrefixLen"];
        $this->events = $data["events"];
        $this->store->createLink($this);
    }

    /**
     * Set a value in the cache.
     * @throws CacheStoreOpException
     */
    public function set(string $key, mixed $value, ?int $ttl = null, ?bool $withChecksum = null): bool|Bytes20
    {
        $value = CachedEnvelope::Prepare($this, $key, $value, $createChecksum ?? $this->useChecksumsByDefault, $ttl);
        if ($value instanceof CachedEnvelope) {
            $checksum = $value->checksum;
            $value = CachedEnvelope::Seal($this, $value);
        }

        try {
            $this->store->set($key, $value, $ttl);
        } catch (\Exception $e) {
            throw new CacheStoreOpException($e);
        }

        return $checksum ?? true;
    }

    /**
     * @throws CacheStoreOpException
     * @throws CachedEnvelopeException
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
     * @throws CacheStoreOpException
     * @throws CachedEnvelopeException
     */
    public function get(
        string $key,
        bool   $returnEnvelope = false,
        bool   $returnReferenceKeyObject = true,
        bool   $expectInteger = false,
        ?bool  $verifyChecksum = null
    ): int|string|null|array|object|bool
    {
        try {
            $stored = $this->store->get($key);
        } catch (\Exception $e) {
            throw new CacheStoreOpException($e);
        }

        if (!is_string($stored)) {
            return $stored;
        }

        $stored = CachedEnvelope::Open($this, $stored, $expectInteger);
        if (!$stored instanceof CachedEnvelope) {
            if (is_string($stored) && str_starts_with($stored, $this->referenceKeysPrefix) && $returnReferenceKeyObject) {
                return CachedReferenceKey::Unserialize($this, $stored);
            }

            return $stored;
        }

        if ($returnEnvelope) {
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
        } catch (CachedEnvelopeException $e) {
            if ($e->error === CachedEntityError::IS_EXPIRED) {
                if ($this->deleteIfExpired) {
                    try {
                        $this->delete($key);
                    } catch (CacheStoreException) {
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
        return $this->store->isConnected();
    }

    /**
     * @throws CacheStoreConnectionException
     */
    public function connect(): void
    {
        try {
            if ($this->isConnected()) {
                return;
            }

            $this->store->connect();
        } catch (\Exception $e) {
            throw new CacheStoreConnectionException($e);
        }
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        $this->store->disconnect();
    }

    /**
     * @throws CacheStoreOpException
     */
    public function delete(string $key): bool
    {
        try {
            return $this->store->delete($key);
        } catch (\Exception $e) {
            throw new CacheStoreOpException($e);
        }
    }

    /**
     * @throws CacheStoreOpException
     */
    public function flush(): bool
    {
        try {
            return $this->store->truncate();
        } catch (\Exception $e) {
            throw new CacheStoreOpException($e);
        }
    }

    /**
     * @throws CacheStoreOpException
     */
    public function has(string $key): bool
    {
        try {
            return $this->store->has($key);
        } catch (\Exception $e) {
            throw new CacheStoreOpException($e);
        }
    }

    /**
     * @throws CacheStoreOpException
     */
    public function ping(): bool
    {
        if (!$this->store->supportsPing()) {
            return false;
        }

        try {
            return $this->store->ping();
        } catch (\Exception $e) {
            throw new CacheStoreOpException($e);
        }
    }

    /**
     * @return StorageType
     */
    public function storageType(): StorageType
    {
        return StorageType::Cache;
    }

    /**
     * @return string
     */
    public function storageProviderId(): string
    {
        return $this->store->getId();
    }

    /**
     * @return string
     */
    public function eventsUniqueContextKey(): string
    {
        return $this->store->getId();
    }
}

