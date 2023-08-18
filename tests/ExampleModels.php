<?php /** @noinspection PhpIllegalPsrClassPathInspection */
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

class ExampleModelA
{

    public function __construct(
        public readonly int           $id,
        public readonly string        $username,
        public readonly string        $email,
        public readonly ExampleModelB $model,
    )
    {
    }
}

class ExampleModelB
{
    public function __construct(
        public readonly string $propA,
        public readonly string $propB,
    )
    {
    }
}
