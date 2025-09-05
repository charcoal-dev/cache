<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Stored;

use Charcoal\Base\Encoding\Encoding;
use Charcoal\Buffers\Types\Bytes20;
use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Enums\CachedEnvelopeError;
use Charcoal\Cache\Exceptions\CachedEnvelopeException;
use Charcoal\Cache\Exceptions\CacheStoreException;
use Charcoal\Cache\Exceptions\CacheException;
use Charcoal\Cache\Pool\CachePool;

/**
 * Represents a cached reference key that facilitates the serialization and deserialization
 * of cache reference keys as well as resolving the actual cached entities via cache stores.
 */
final readonly class CachedReferenceKey
{
    /**
     * @throws CachedEnvelopeException
     */
    public static function Serialize(
        CacheClient  $cacheStore,
        string       $targetKey,
        ?CacheClient $targetKeyServer = null,
        ?Bytes20     $checksum = null
    ): string
    {
        if ($targetKeyServer) {
            if ($targetKeyServer->store->getId() === $cacheStore->store->getId()) {
                $targetKeyServer = null;
            }
        }

        $reference = sprintf(
            "%s[%s][%s](%s)",
            $cacheStore->referenceKeysPrefix,
            $targetKeyServer ? $targetKeyServer->store->getId() : "~",
            $targetKey,
            $checksum ? $checksum->encode(Encoding::Base16) : "*"
        );

        if (strlen($reference) > $cacheStore->plainStringsMaxLength) {
            throw new CachedEnvelopeException(
                CachedEnvelopeError::REF_KEY_LENGTH,
                sprintf("Reference key exceeds plain string limit of %d bytes", $cacheStore->plainStringsMaxLength)
            );
        }

        return $reference;
    }

    /**
     * @throws CachedEnvelopeException
     */
    public static function Unserialize(CacheClient $cacheStore, string $serialized): self
    {
        $matches = [];
        if (!preg_match(
                "/^" . preg_quote($cacheStore->referenceKeysPrefix, "/") . "\[(.*)]\[(.*)]\((.*)\)$/",
                $serialized,
                $matches
            ) || count($matches) !== 4) {
            throw new CachedEnvelopeException(
                CachedEnvelopeError::REF_DECODE_ERROR,
                "Malformed reference pointer"
            );
        }

        $targetServerId = $matches[1] ?? "";
        $targetChecksum = $matches[3] ?? "";
        return new self(
            $matches[2] ?? "",
            $targetServerId === "~" ? null : $targetServerId,
            $targetChecksum === "*" ? null : new Bytes20(Encoding::Base16->decode($targetChecksum))
        );
    }

    /**
     * @param string $targetKey
     * @param string|null $targetServerId
     * @param Bytes20|null $targetChecksum
     */
    protected function __construct(
        public string   $targetKey,
        public ?string  $targetServerId = null,
        public ?Bytes20 $targetChecksum = null,
    )
    {
    }

    /**
     * @throws CacheException
     */
    public function resolve(CacheClient|CachePool $storage): mixed
    {
        $cacheArray = $storage instanceof CachePool ? $storage : [$storage];
        foreach ($cacheArray as $cache) {
            if ($this->targetServerId && $cache->store->getId() !== $this->targetServerId) {
                continue;
            }

            $item = $cache->get($this->targetKey, returnEnvelope: true, returnReferenceKeyObject: false);
            if (!$item instanceof CachedEnvelope) {
                if ($item) {
                    throw new CachedEnvelopeException(
                        CachedEnvelopeError::REF_NOT_OBJECT,
                        sprintf('Reference key resolved to item of type "%s", expected CachedEntity object', gettype($item))
                    );
                }

                continue;
            }

            if ($this->targetChecksum) {
                if (!$item->checksum || !$this->targetChecksum->equals($item->checksum)) {
                    throw CachedEnvelopeException::ChecksumError(
                        CachedEnvelopeError::REF_BAD_CHECKSUM,
                        $this->targetChecksum,
                        $item->checksum
                    );
                }
            }

            try {
                return $item->getStoredItem();
            } catch (CachedEnvelopeException $e) {
                if ($e->error === CachedEnvelopeError::IS_EXPIRED) {
                    if ($cache->deleteIfExpired) {
                        try {
                            $cache->delete($this->targetKey);
                        } catch (CacheStoreException) {
                        }
                    }

                    if ($cache->nullIfExpired) {
                        return null;
                    }
                }

                throw $e;
            }
        }

        return null;
    }
}