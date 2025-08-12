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

/**
 * Class CachedEntity
 * @package Charcoal\Cache
 */
class CachedEntity
{
    public readonly string $type;
    public readonly bool|int|float|string|null $value;
    public readonly int $storedOn;
    public readonly ?Bytes20 $checksum;

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param bool $createChecksum
     */
    public function __construct(
        public readonly string $key,
        mixed                  $value,
        public readonly ?int   $ttl = null,
        bool                   $createChecksum = true,
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
     * @return void
     * @throws \Charcoal\Cache\Exception\CachedEntityException
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
     * @return int|float|string|bool|array|object|null
     * @throws \Charcoal\Cache\Exception\CachedEntityException
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

    /**
     * @param \Charcoal\Cache\Cache $cache
     * @param string $key
     * @param mixed $value
     * @param bool $createChecksum
     * @param int|null $ttl
     * @return int|string|static
     */
    public static function Prepare(Cache $cache, string $key, mixed $value, bool $createChecksum, ?int $ttl = null): int|string|static
    {
        if ($value instanceof static) {
            return $value;
        }

        if ($createChecksum) { // when creating checksum, small strings and integers will be stored in CachedEntity
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

    /**
     * @param \Charcoal\Cache\Cache $cache
     * @param \Charcoal\Cache\CachedEntity $entity
     * @return string
     */
    public static function Serialize(Cache $cache, CachedEntity $entity): string
    {
        $serialized = serialize($entity);
        $nullBytesReq = $cache->plainStringsMaxLength - strlen($serialized);
        if ($nullBytesReq > 0) {
            $serialized .= str_repeat("\0", $nullBytesReq);
        }

        return $cache->serializedEntityPrefix . base64_encode($serialized);
    }

    /**
     * @param \Charcoal\Cache\Cache $cache
     * @param string $serialized
     * @param bool $expectInteger
     * @return int|string|static
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public static function Restore(Cache $cache, string $serialized, bool $expectInteger = false): int|string|static
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

