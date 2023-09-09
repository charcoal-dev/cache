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

use Charcoal\Events\Event;
use Charcoal\Events\EventsRegistry;

/**
 * Class Events
 * @package Charcoal\Cache
 */
class Events extends EventsRegistry
{
    /**
     * @return \Charcoal\Events\Event
     */
    public function onConnected(): Event
    {
        return $this->on("connection");
    }

    /**
     * @return \Charcoal\Events\Event
     */
    public function onDisconnect(): Event
    {
        return $this->on("disconnect");
    }
}
