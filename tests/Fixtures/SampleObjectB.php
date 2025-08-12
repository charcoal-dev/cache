<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests\Fixtures;

readonly class SampleObjectB
{
    public function __construct(
        public string $propA,
        public string $propB,
    )
    {
    }
}