<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests;

use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Pool\CachePool;
use Charcoal\Cache\Tests\Fixtures\SampleObjectA;
use Charcoal\Cache\Tests\Fixtures\SampleObjectB;
use Charcoal\Cache\Tests\Stubs\LocalCache;

/**
 * This class contains test cases for validating the functionality and behavior of a cache pool.
 */
class CachePoolTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     */
    public function testUniqueKeysAndIterator(): void
    {
        $cacheStore1 = new CacheClient(new LocalCache(1));
        $cacheStore1a = new CacheClient(new LocalCache(1)); // This generates same identifier as above
        $cacheStore2 = new CacheClient(new LocalCache(2));
        $cacheArray = new CachePool("pool_one");
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore1a)
            ->addServer($cacheStore2);

        $this->assertEquals(2, $cacheArray->count());

        $iteratorCount = 0;
        foreach ($cacheArray as $store) {
            $iteratorCount++;
            $this->assertInstanceOf(CacheClient::class, $store);
            $this->assertEquals($iteratorCount, $store->store->salt);
        }
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CacheStoreOpException
     * @throws \Charcoal\Cache\Exceptions\CacheException
     */
    public function testBulkOps1(): void
    {
        $cacheStore1 = new CacheClient(new LocalCache(1));
        $cacheStore2 = new CacheClient(new LocalCache(2));
        $cacheStore3 = new CacheClient(new LocalCache(3));
        $cacheArray = new CachePool("pool_two");
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore2)
            ->addServer($cacheStore3);

        $cacheArray->setToAll("testExampleModel", new SampleObjectA(1, "test", "test@test.com", new SampleObjectB("a", "b")));
        /** @var CacheClient $store */
        foreach ($cacheArray as $store) {
            $this->assertTrue($store->has("testExampleModel"));
        }

        $cacheStore2->delete("testExampleModel");

        $bulkHave = $cacheArray->haveInAll("testExampleModel");
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
        $this->assertEquals($cacheStore2->store->getId(), $falseServers[0]);

        $read1 = $cacheArray->get("testExampleModel");
        $this->assertInstanceOf(SampleObjectA::class, $read1);
        $readAll = $cacheArray->getFromAll("testExampleModel");
        $this->assertInstanceOf(SampleObjectA::class, $readAll->successList[$cacheStore1->store->getId()]);
        $this->assertNull($readAll->successList[$cacheStore2->store->getId()], "This was deleted, should return NULL");
        $this->assertInstanceOf(SampleObjectA::class, $readAll->successList[$cacheStore3->store->getId()]);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CacheException
     */
    public function testPrimaryOps1(): void
    {
        $cacheStore1 = new CacheClient(new LocalCache(1));
        $cacheStore2 = new CacheClient(new LocalCache(2));
        $cacheStore3 = new CacheClient(new LocalCache(3));
        $cacheArray = new CachePool("pool_three");
        $cacheArray->addServer($cacheStore3)
            ->addServer($cacheStore2)
            ->addServer($cacheStore1);

        // Set in primary
        $cacheArray->set("exampleModel", new SampleObjectA(1, "test", "test@test.com", new SampleObjectB("a", "b")));

        // Check in all
        $bulkHave = $cacheArray->haveInAll("exampleModel");

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
