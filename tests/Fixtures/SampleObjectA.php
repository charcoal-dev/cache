<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests\Fixtures;

readonly class SampleObjectA
{
    public function __construct(
        public int           $id,
        public string        $username,
        public string        $email,
        public SampleObjectB $model,
    )
    {
    }
}