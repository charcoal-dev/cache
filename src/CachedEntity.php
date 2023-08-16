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

        if (!$this->checksum->equals(hash_hmac("sha1", $this->value, $this->key, true))) {
            throw new CachedEntityException(CachedEntityError::BAD_CHECKSUM);
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
}

