<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
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
    public function onConnected(): Event
    {
        return $this->on("connection");
    }

    public function onDisconnect(): Event
    {
        return $this->on("disconnect");
    }
}
