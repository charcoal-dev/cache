<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Exceptions;

/**
 * Class CacheStoreException
 * @package Charcoal\Cache\Exceptions
 */
class CacheStoreException extends CacheException
{
    public function __construct(\Exception $previous)
    {
        parent::__construct($previous->getMessage(), $previous->getCode(), previous: $previous);
    }
}