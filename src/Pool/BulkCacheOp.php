<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Pool;

/**
 * Represents the result of a bulk cache operation, tracking totals, successes, and exceptions.
 */
final readonly class BulkCacheOp
{
    public int $total;
    public int $success;
    public int $exceptions;

    public function __construct(
        public array $successList = [],
        public array $exceptionsList = [],
    )
    {
        $this->success = count($successList);
        $this->exceptions = count($exceptionsList);
        $this->total = $this->success + $this->exceptions;
    }
}

