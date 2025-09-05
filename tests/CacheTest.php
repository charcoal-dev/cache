<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests;

use Charcoal\Buffers\Types\Bytes20;
use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Exceptions\CacheException;
use Charcoal\Cache\Stored\CachedEnvelope;
use Charcoal\Cache\Tests\Fixtures\SampleObjectA;
use Charcoal\Cache\Tests\Fixtures\SampleObjectB;
use Charcoal\Cache\Tests\Stubs\LocalCache;

/**
 * Class CacheTest
 */
class CacheTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     * @throws CacheException
     */
    public function testNullIfExpired(): void
    {
        $cacheStore1 = new CacheClient(new LocalCache(), nullIfExpired: false);
        $cacheStore2 = new CacheClient(new LocalCache(), nullIfExpired: true);
        $item = new SampleObjectB("char", "coal");
        $cacheStore1->set("testKey", $item, 2);
        $cacheStore2->set("testKey", $item, 2);

        $this->assertInstanceOf(SampleObjectB::class, $cacheStore2->get("testKey"));
        $this->assertInstanceOf(SampleObjectB::class, $cacheStore1->get("testKey"));
        sleep(3);
        $this->assertNull($cacheStore2->get("testKey"), "Cache Store 2 will return NULL");
        $this->expectException(CacheException::class);
        $cacheStore1->get("testKey");
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CacheStoreOpException
     * @throws \Charcoal\Cache\Exceptions\CachedEnvelopeException
     */
    public function testDeleteIfExpired(): void
    {
        $cacheStore = new CacheClient(new LocalCache(), nullIfExpired: true, deleteIfExpired: true);
        $item = new SampleObjectB("char", "coal");
        $cacheStore->set("testItem", $item, 2);
        $this->assertTrue($cacheStore->has("testItem"));
        sleep(3);
        $this->assertTrue($cacheStore->has("testItem"), "Has method returns TRUE, value still not retrieved");
        $this->assertNull($cacheStore->get("testItem"), "Return NULL but also delete the key");
        $this->assertFalse($cacheStore->has("testItem"));
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CacheStoreOpException
     * @throws \Charcoal\Cache\Exceptions\CachedEnvelopeException
     */
    public function testChecksum(): void
    {
        $cacheStore = new CacheClient(new LocalCache());
        $checksum = $cacheStore->set("test", "some-value", withChecksum: true);
        $this->assertInstanceOf(Bytes20::class, $checksum);
        /** @var CachedEnvelope $value */
        $value = $cacheStore->get("test", returnEnvelope: true);
        $this->assertEquals(20, $value->checksum->length());
        $this->assertTrue($checksum->equals($value->checksum));
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CacheStoreOpException
     */
    public function testNoChecksum(): void
    {
        $cacheStore = new CacheClient(new LocalCache(), useChecksumsByDefault: false);
        $set1 = $cacheStore->set("test", new SampleObjectA(1, "test", "test@test.com", new SampleObjectB("a", "b")));
        $this->assertIsBool($set1);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CacheStoreOpException
     */
    public function testChecksumByDefault(): void
    {
        $cacheStore = new CacheClient(new LocalCache(), useChecksumsByDefault: true);
        $set1 = $cacheStore->set("test", new SampleObjectA(1, "test", "test@test.com", new SampleObjectB("a", "b")));
        $this->assertInstanceOf(\Charcoal\Buffers\Types\Bytes20::class, $set1);
    }
}
