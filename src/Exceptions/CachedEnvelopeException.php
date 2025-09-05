<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Exceptions;

use Charcoal\Buffers\Types\Bytes20;
use Charcoal\Cache\Enums\CachedEnvelopeError;

/**
 * Class CachedEnvelopeException
 * @package Charcoal\Cache\Exceptions
 */
final class CachedEnvelopeException extends CacheException
{
    public function __construct(
        public readonly CachedEnvelopeError $error,
        string                              $msg = "",
        ?\Throwable                         $previous = null,
        public readonly ?Bytes20            $checksum1 = null,
        public readonly ?Bytes20            $checksum2 = null,
    )
    {
        parent::__construct($msg, $this->error->value, $previous);
    }

    public static function ChecksumError(
        CachedEnvelopeError $flag,
        ?Bytes20            $checksum1 = null,
        ?Bytes20            $checksum2 = null,
        ?\Throwable         $previous = null,
    ): self
    {
        return new self($flag, "", $previous, $checksum1, $checksum2);
    }
}
