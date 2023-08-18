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
 * Class CacheArrayTest
 */
class CacheArrayTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     */
    public function testUniqueKeysAndIterator(): void
    {
        $cacheStore1 = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $cacheStore1a = new \Charcoal\Cache\Cache(new DumbCacheStore(1)); // This generates same metaUniqueId as above
        $cacheStore2 = new \Charcoal\Cache\Cache(new DumbCacheStore(2));
        $cacheArray = new \Charcoal\Cache\CacheArray();
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore1a)
            ->addServer($cacheStore2);

        $this->assertEquals(2, $cacheArray->count());

        $iteratorCount = 0;
        foreach ($cacheArray as $store) {
            $iteratorCount++;
            $this->assertInstanceOf(\Charcoal\Cache\Cache::class, $store);
            $this->assertEquals($iteratorCount, $store->storageDriver->salt);
        }
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function testBulkOps1(): void
    {
        $cacheStore1 = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $cacheStore2 = new \Charcoal\Cache\Cache(new DumbCacheStore(2));
        $cacheStore3 = new \Charcoal\Cache\Cache(new DumbCacheStore(3));
        $cacheArray = new \Charcoal\Cache\CacheArray();
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore2)
            ->addServer($cacheStore3);

        $cacheArray->setToAll("testExampleModel", new ExampleModelA(1, "test", "test@test.com", new ExampleModelB("a", "b")));
        /** @var \Charcoal\Cache\Cache $store */
        foreach ($cacheArray as $store) {
            $this->assertTrue($store->has("testExampleModel"));
        }

        $cacheStore2->delete("testExampleModel");

        $bulkHave = $cacheArray->allHave("testExampleModel");
        $this->assertEquals(3, $bulkHave->total);
        $this->assertEquals(3, $bulkHave->success);
        $this->assertEquals(0, $bulkHave->exceptions);

        $trueServers = [];
        $falseServers = [];
        foreach ($bulkHave->successList as $serverId => $result) {
            $this->assertIsBool($result);
            if ($result) {
                $trueServers[] = $serverId;
            } else {
                $falseServers[] = $serverId;
            }
        }

        $this->assertCount(2, $trueServers);
        $this->assertCount(1, $falseServers);
        $this->assertEquals($cacheStore2->storageDriver->metaUniqueId(), $falseServers[0]);

        $read1 = $cacheArray->get("testExampleModel");
        $this->assertInstanceOf(ExampleModelA::class, $read1);
        $readAll = $cacheArray->getFromAll("testExampleModel");
        $this->assertInstanceOf(ExampleModelA::class, $readAll->successList[$cacheStore1->storageDriver->metaUniqueId()]);
        $this->assertNull($readAll->successList[$cacheStore2->storageDriver->metaUniqueId()], "This was deleted, should return NULL");
        $this->assertInstanceOf(ExampleModelA::class, $readAll->successList[$cacheStore3->storageDriver->metaUniqueId()]);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function testPrimaryOps1(): void
    {
        $cacheStore1 = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $cacheStore2 = new \Charcoal\Cache\Cache(new DumbCacheStore(2));
        $cacheStore3 = new \Charcoal\Cache\Cache(new DumbCacheStore(3));
        $cacheArray = new \Charcoal\Cache\CacheArray();
        $cacheArray->addServer($cacheStore3)
            ->addServer($cacheStore2)
            ->addServer($cacheStore1);

        // Set in primary
        $cacheArray->set("exampleModel", new ExampleModelA(1, "test", "test@test.com", new ExampleModelB("a", "b")));

        // Check in all
        $bulkHave = $cacheArray->allHave("exampleModel");

        $trueServers = [];
        $falseServers = [];
        foreach ($bulkHave->successList as $serverId => $result) {
            $this->assertIsBool($result);
            if ($result) {
                $trueServers[] = $serverId;
            } else {
                $falseServers[] = $serverId;
            }
        }

        $this->assertCount(1, $trueServers);
        $this->assertCount(2, $falseServers);
    }
}
