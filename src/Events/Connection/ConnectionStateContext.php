<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Events\Connection;

use Charcoal\Events\Contracts\BehaviourContextEnablerInterface;
use Charcoal\Events\Contracts\EventContextInterface;

/**
 * Interface ConnectionStateContext
 * @package Charcoal\Cache\Events\Connection
 */
interface ConnectionStateContext extends EventContextInterface,
    BehaviourContextEnablerInterface
{
}