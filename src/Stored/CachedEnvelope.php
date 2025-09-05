<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Stored;

use Charcoal\Buffers\Types\Bytes20;
use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Enums\CachedEntityError;
use Charcoal\Cache\Exceptions\CachedEnvelopeException;

/**
 * Represents a cached envelope that stores a value with optional checksum and TTL (time-to-live).
 * This immutable class ensures integrity of the stored value by optionally attaching a checksum
 * and provides methods for verifying it. It is used to manage various cacheable entities
 * with support for serialization and restoration.
 */
final readonly class CachedEnvelope
{
    public string $type;
    public bool|int|float|string|null $value;
    public int $storedOn;
    public ?Bytes20 $checksum;

    public function __construct(
        public string $key,
        mixed         $value,
        public ?int   $ttl = null,
        bool          $withChecksum = true,
    )
    {
        $this->type = gettype($value);
        $this->value = match ($this->type) {
            "boolean", "integer", "double", "string", "NULL" => $value,
            "object", "array" => serialize($value),
            default => throw new \UnexpectedValueException(sprintf('Cannot store value of type "%s"', $this->type)),
        };

        $this->checksum = $withChecksum ? new Bytes20(hash_hmac("sha1", $this->value, $this->key, true)) : null;
        $this->storedOn = time();
    }

    /**
     * @throws CachedEnvelopeException
     */
    public function verifyChecksum(): void
    {
        if (!$this->checksum) {
            throw new CachedEnvelopeException(CachedEntityError::CHECKSUM_NOT_STORED);
        }

        $compare = hash_hmac("sha1", $this->value, $this->key, true);
        if (!$this->checksum->equals($compare)) {
            throw CachedEnvelopeException::ChecksumError(
                CachedEntityError::BAD_CHECKSUM,
                $this->checksum,
                new Bytes20($compare)
            );
        }
    }

    /**
     * @throws CachedEnvelopeException
     */
    public function getStoredItem(): int|float|string|null|bool|array|object
    {
        if ($this->ttl) {
            $epoch = time();
            if ($this->ttl > $epoch || ($epoch - $this->storedOn) >= $this->ttl) {
                throw new CachedEnvelopeException(CachedEntityError::IS_EXPIRED);
            }
        }

        if (!in_array($this->type, ["array", "object"])) {
            return $this->value;
        }

        $obj = unserialize($this->value);
        if (!$obj) {
            throw new CachedEnvelopeException(CachedEntityError::UNSERIALIZE_FAIL);
        }

        return $obj;
    }

    /**
     * @param CacheClient $cache
     * @param string $key
     * @param mixed $value
     * @param bool $withChecksum
     * @param int|null $ttl
     * @return int|string|self
     */
    public static function Prepare(
        CacheClient $cache,
        string      $key,
        mixed       $value,
        bool        $withChecksum,
        ?int        $ttl = null
    ): int|string|self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($withChecksum) {
            return new self($key, $value, $ttl, true);
        }

        if (is_string($value) && strlen($value) <= $cache->plainStringsMaxLength) {
            return $value;
        }

        if (is_int($value)) {
            return $value;
        }

        return new self($key, $value, $ttl, false);
    }

    /**
     * @param CacheClient $cache
     * @param CachedEnvelope $entity
     * @return string
     */
    public static function Seal(
        CacheClient    $cache,
        CachedEnvelope $entity
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
     * @throws CachedEnvelopeException
     */
    public static function Open(
        CacheClient $cache,
        string      $serialized,
        bool        $expectInteger = false
    ): int|string|self
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
        if (!$cachedEntity instanceof self) {
            throw new CachedEnvelopeException(
                CachedEntityError::BAD_BYTES,
                "Could not restore serialized CachedEntity object"
            );
        }

        return $cachedEntity;
    }
}

