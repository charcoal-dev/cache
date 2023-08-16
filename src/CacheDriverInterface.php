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
 * Interface CacheDriverInterface
 * @package Charcoal\Cache
 */
interface CacheDriverInterface
{
    /**
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverConnectionException
     */
    public function connect(): void;

    /**
     * @return void
     */
    public function disconnect(): void;

    /**
     * @return bool
     */
    public function supportsPing(): bool;

    /**
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function ping(): bool;

    /**
     * @param string $key
     * @param int|string $value
     * @param int|null $ttl
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function store(string $key, int|string $value, ?int $ttl = null): void;

    /**
     * @param string $key
     * @return int|string|bool|null
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function resolve(string $key): int|string|null|bool;

    /**
     * @param string $key
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function isStored(string $key): bool;

    /**
     * @param string $key
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function delete(string $key): bool;

    /**
     * @return bool
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     */
    public function truncate(): bool;
}