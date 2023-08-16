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

/**
 * Class Cache
 * @package Charcoal\Cache
 */
class Cache
{
    /**
     * @param \Charcoal\Cache\CacheDriverInterface $storageDriver
     * @param bool $nullIfExpired
     * @param bool $deleteIfExpired
     */
    public function __construct(
        public readonly CacheDriverInterface $storageDriver,
        public bool                          $nullIfExpired = true,
        public bool                          $deleteIfExpired = true,
    )
    {
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->storageDriver->isConnected();
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverConnectionException
     */
    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $this->storageDriver->connect();
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        $this->storageDriver->disconnect();
    }
}

