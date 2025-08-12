<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Exception;

use Charcoal\Buffers\Frames\Bytes20;

/**
 * Class CachedEntityException
 * @package Charcoal\Cache\Exception
 */
class CachedEntityException extends CacheException
{
    public ?Bytes20 $checksum1 = null;
    public ?Bytes20 $checksum2 = null;

    /**
     * @param \Charcoal\Cache\Exception\CachedEntityError $error
     * @param string $msg
     * @param \Throwable|null $previous
     */
    public function __construct(
        public readonly CachedEntityError $error,
        string                            $msg = "",
        ?\Throwable                       $previous = null
    )
    {
        parent::__construct($msg, $this->error->value, $previous);
    }

    /**
     * @param \Charcoal\Cache\Exception\CachedEntityError $flag
     * @param \Charcoal\Buffers\Frames\Bytes20|null $checksum1
     * @param \Charcoal\Buffers\Frames\Bytes20|null $checksum2
     * @param \Throwable|null $previous
     * @return static
     */
    public static function ChecksumError(
        CachedEntityError $flag,
        ?Bytes20          $checksum1 = null,
        ?Bytes20          $checksum2 = null,
        ?\Throwable       $previous = null,
    ): static
    {
        $ex = new static($flag, "", $previous);
        $ex->checksum1 = $checksum1;
        $ex->checksum2 = $checksum2;
        return $ex;
    }
}
