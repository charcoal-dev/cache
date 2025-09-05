<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Events;

use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Events\Connection\ConnectionError;
use Charcoal\Cache\Events\Connection\ConnectionStateContext;
use Charcoal\Cache\Events\Connection\ConnectionSuccess;
use Charcoal\Events\BehaviorEvent;
use Charcoal\Events\Dispatch\DispatchReport;
use Charcoal\Events\Subscriptions\Subscription;

/**
 * Represents cache-related events, such as connection changes or state transitions.
 * Extends functionality from the BehaviorEvent class and provides specific event-handling
 * capabilities for cache interactions.
 */
final class CacheEvents extends BehaviorEvent
{
    /**
     * @param CacheClient $client
     */
    public function __construct(public readonly CacheClient $client)
    {
        parent::__construct("connectionEvents", [
            ConnectionStateContext::class,
            ConnectionSuccess::class,
            ConnectionError::class,
        ]);
    }

    /**
     * @return Subscription
     */
    public function subscribe(): Subscription
    {
        return $this->createSubscription("cache-conn-event-" .
            count($this->subscribers()) . "-" . substr(uniqid(), 0, 4));
    }

    /**
     * @param ConnectionStateContext $context
     * @return DispatchReport
     */
    public function dispatch(ConnectionStateContext $context): DispatchReport
    {
        return $this->dispatchEvent($context);
    }
}