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

require_once "DumbCacheStore.php";
require_once "ExampleModels.php";

/**
 * Class CacheTest
 */
class CacheTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CacheException
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testNullIfExpired(): void
    {
        $cacheStore1 = new \Charcoal\Cache\Cache(new DumbCacheStore(), nullIfExpired: false);
        $cacheStore2 = new \Charcoal\Cache\Cache(new DumbCacheStore(), nullIfExpired: true);
        $item = new ExampleModelB("char", "coal");
        $cacheStore1->set("testKey", $item, 2);
        $cacheStore2->set("testKey", $item, 2);

        $this->assertInstanceOf(ExampleModelB::class, $cacheStore2->get("testKey"));
        $this->assertInstanceOf(ExampleModelB::class, $cacheStore1->get("testKey"));
        sleep(3);
        $this->assertNull($cacheStore2->get("testKey"), "Cache Store 2 will return NULL");
        $this->expectException(\Charcoal\Cache\Exception\CachedEntityException::class);
        $cacheStore1->get("testKey");
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CacheException
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testDeleteIfExpired(): void
    {
        $cacheStore = new \Charcoal\Cache\Cache(new DumbCacheStore(), nullIfExpired: true, deleteIfExpired: true);
        $item = new ExampleModelB("char", "coal");
        $cacheStore->set("testItem", $item, 2);
        $this->assertTrue($cacheStore->has("testItem"));
        sleep(3);
        $this->assertTrue($cacheStore->has("testItem"), "Has method returns TRUE, value still not retrieved");
        $this->assertNull($cacheStore->get("testItem"), "Return NULL but also delete the key");
        $this->assertFalse($cacheStore->has("testItem"));
    }
}
