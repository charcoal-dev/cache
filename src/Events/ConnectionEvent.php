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
use Charcoal\Events\Support\Traits\EventStaticScopeTrait;

/**
 * Class StoreConnectionEvent
 * @package Charcoal\Cache\Events
 * @template T of ConnectionEvent
 * @template S of CacheClient
 * @template E of ConnectionSuccess|ConnectionError
 */
class ConnectionEvent extends BehaviorEvent
{
    use EventStaticScopeTrait;

    /**
     * @param CacheClient $client
     * @param bool $staticScopeReplaceExisting
     */
    public function __construct(
        public readonly CacheClient $client,
        bool                        $staticScopeReplaceExisting = false
    )
    {
        parent::__construct("connectionEvents", [
            ConnectionStateContext::class,
            ConnectionSuccess::class,
            ConnectionError::class,
        ]);

        $this->registerStaticEventStore($this->client, $staticScopeReplaceExisting);
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
     * @param E $context
     * @return DispatchReport
     */
    public function dispatch(ConnectionStateContext $context): DispatchReport
    {
        return $this->dispatchEvent($context);
    }
}