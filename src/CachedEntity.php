<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache;

use Charcoal\Buffers\Frames\Bytes20;
use Charcoal\Cache\Enums\CachedEntityError;
use Charcoal\Cache\Exceptions\CachedEntityException;

/**
 * Class CachedEntity
 * @package Charcoal\Cache
 */
readonly class CachedEntity
{
    public string $type;
    public bool|int|float|string|null $value;
    public int $storedOn;
    public ?Bytes20 $checksum;

    public function __construct(
        public string $key,
        mixed         $value,
        public ?int   $ttl = null,
        bool          $createChecksum = true,
    )
    {
        $this->type = gettype($value);
        $this->value = match ($this->type) {
            "boolean", "integer", "double", "string", "NULL" => $value,
            "object", "array" => serialize($value),
            default => throw new \UnexpectedValueException(sprintf('Cannot store value of type "%s"', $this->type)),
        };

        $this->checksum = $createChecksum ? new Bytes20(hash_hmac("sha1", $this->value, $this->key, true)) : null;
        $this->storedOn = time();
    }

    /**
     * @throws CachedEntityException
     */
    public function verifyChecksum(): void
    {
        if (!$this->checksum) {
            throw new CachedEntityException(CachedEntityError::CHECKSUM_NOT_STORED);
        }

        $compare = hash_hmac("sha1", $this->value, $this->key, true);
        if (!$this->checksum->equals($compare)) {
            throw CachedEntityException::ChecksumError(
                CachedEntityError::BAD_CHECKSUM,
                $this->checksum,
                new Bytes20($compare)
            );
        }
    }

    /**
     * @throws CachedEntityException
     */
    public function getStoredItem(): int|float|string|null|bool|array|object
    {
        if ($this->ttl) {
            $epoch = time();
            if ($this->ttl > $epoch || ($epoch - $this->storedOn) >= $this->ttl) {
                throw new CachedEntityException(CachedEntityError::IS_EXPIRED);
            }
        }

        if (!in_array($this->type, ["array", "object"])) {
            return $this->value;
        }

        $obj = unserialize($this->value);
        if (!$obj) {
            throw new CachedEntityException(CachedEntityError::UNSERIALIZE_FAIL);
        }

        return $obj;
    }

    public static function Prepare(
        CacheClient $cache,
        string      $key,
        mixed       $value,
        bool        $createChecksum,
        ?int        $ttl = null
    ): int|string|static
    {
        if ($value instanceof static) {
            return $value;
        }

        if ($createChecksum) {
            return new static($key, $value, $ttl, true);
        }

        if (is_string($value) && strlen($value) <= $cache->plainStringsMaxLength) {
            return $value;
        }

        if (is_int($value)) {
            return $value;
        }

        return new static($key, $value, $ttl, false);
    }

    public static function Serialize(
        CacheClient $cache,
        CachedEntity $entity
    ): string
    {
        $serialized = serialize($entity);
        $nullBytesReq = $cache->plainStringsMaxLength - strlen($serialized);
        if ($nullBytesReq > 0) {
            $serialized .= str_repeat("\0", $nullBytesReq);
        }

        return $cache->serializedEntityPrefix . base64_encode($serialized);
    }

    /**
     * @throws CachedEntityException
     */
    public static function Restore(
        CacheClient $cache,
        string $serialized,
        bool $expectInteger = false
    ): int|string|static
    {
        if ($expectInteger && preg_match('/^-?\d+$/', $serialized)) {
            return intval($serialized);
        }

        if (strlen($serialized) <= $cache->plainStringsMaxLength) {
            return $serialized;
        }

        if (!str_starts_with($serialized, $cache->serializedEntityPrefix)) {
            return $serialized;
        }

        $cachedEntity = unserialize(rtrim(base64_decode(substr($serialized, $cache->serializePrefixLen))));
        if (!$cachedEntity instanceof static) {
            throw new CachedEntityException(
                CachedEntityError::BAD_BYTES,
                "Could not restore serialized CachedEntity object"
            );
        }

        return $cachedEntity;
    }
}

