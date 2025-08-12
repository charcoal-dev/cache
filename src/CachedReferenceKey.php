<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache;

use Charcoal\Buffers\Frames\Bytes20;
use Charcoal\Cache\Exception\CachedEntityError;
use Charcoal\Cache\Exception\CachedEntityException;
use Charcoal\Cache\Exception\CacheDriverException;
use Charcoal\Cache\Exception\CacheException;

/**
 * Class CachedReferenceKey
 * @package Charcoal\Cache
 */
readonly class CachedReferenceKey
{
    /**
     * @throws CachedEntityException
     */
    public static function Serialize(
        CacheClient  $cacheStore,
        string       $targetKey,
        ?CacheClient $targetKeyServer = null,
        ?Bytes20     $checksum = null
    ): string
    {
        if ($targetKeyServer) {
            if ($targetKeyServer->storageDriver->metaUniqueId() === $cacheStore->storageDriver->metaUniqueId()) {
                $targetKeyServer = null;
            }
        }

        $reference = sprintf(
            "%s[%s][%s](%s)",
            $cacheStore->referenceKeysPrefix,
            $targetKeyServer ? $targetKeyServer->storageDriver->metaUniqueId() : "~",
            $targetKey,
            $checksum ? $checksum->toBase16() : "*"
        );

        if (strlen($reference) > $cacheStore->plainStringsMaxLength) {
            throw new CachedEntityException(
                CachedEntityError::REF_KEY_LENGTH,
                sprintf("Reference key exceeds plain string limit of %d bytes", $cacheStore->plainStringsMaxLength)
            );
        }

        return $reference;
    }

    /**
     * @throws CachedEntityException
     */
    public static function Unserialize(CacheClient $cacheStore, string $serialized): static
    {
        $matches = [];
        if (!preg_match(
                "/^" . preg_quote($cacheStore->referenceKeysPrefix, "/") . "\[(.*)]\[(.*)]\((.*)\)$/",
                $serialized,
                $matches
            ) || count($matches) !== 4) {
            throw new CachedEntityException(
                CachedEntityError::REF_DECODE_ERROR,
                "Malformed reference pointer"
            );
        }

        $targetServerId = $matches[1] ?? "";
        $targetChecksum = $matches[3] ?? "";
        return new static(
            $matches[2] ?? "",
            $targetServerId === "~" ? null : $targetServerId,
            $targetChecksum === "*" ? null : Bytes20::fromBase16($targetChecksum)
        );
    }

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
    public function resolve(CacheClient|CacheArray $storage): mixed
    {
        $cacheArray = $storage instanceof CacheArray ? $storage : [$storage];
        foreach ($cacheArray as $cache) {
            if ($this->targetServerId && $cache->storageDriver->metaUniqueId() !== $this->targetServerId) {
                continue;
            }

            $item = $cache->get($this->targetKey, returnCachedEntity: true, returnReferenceKeyObject: false);
            if (!$item instanceof CachedEntity) {
                if ($item) {
                    throw new CachedEntityException(
                        CachedEntityError::REF_NOT_OBJECT,
                        sprintf('Reference key resolved to item of type "%s", expected CachedEntity object', gettype($item))
                    );
                }

                continue;
            }

            if ($this->targetChecksum) {
                if (!$item->checksum || !$this->targetChecksum->equals($item->checksum)) {
                    throw CachedEntityException::ChecksumError(
                        CachedEntityError::REF_BAD_CHECKSUM,
                        $this->targetChecksum,
                        $item->checksum
                    );
                }
            }

            try {
                return $item->getStoredItem();
            } catch (CachedEntityException $e) {
                if ($e->error === CachedEntityError::IS_EXPIRED) {
                    if ($cache->deleteIfExpired) {
                        try {
                            $cache->delete($this->targetKey);
                        } catch (CacheDriverException) {
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