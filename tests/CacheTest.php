<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
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
        $item = new SampleObjectB("char", "coal");
        $cacheStore1->set("testKey", $item, 2);
        $cacheStore2->set("testKey", $item, 2);

        $this->assertInstanceOf(SampleObjectB::class, $cacheStore2->get("testKey"));
        $this->assertInstanceOf(SampleObjectB::class, $cacheStore1->get("testKey"));
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
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CacheException
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testChecksum(): void
    {
        $cacheStore = new \Charcoal\Cache\Cache(new DumbCacheStore());
        $checksum = $cacheStore->set("test", "some-value", createChecksum: true);
        $this->assertInstanceOf(\Charcoal\Buffers\Frames\Bytes20::class, $checksum);
        /** @var \Charcoal\Cache\CachedEntity $value */
        $value = $cacheStore->get("test", returnCachedEntity: true);
        $this->assertEquals(20, $value->checksum->len());
        $this->assertTrue($checksum->equals($value->checksum));
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function testNoChecksum(): void
    {
        $cacheStore = new Charcoal\Cache\Cache(new DumbCacheStore(), useChecksumsByDefault: false);
        $set1 = $cacheStore->set("test", new SampleObjectA(1, "test", "test@test.com", new SampleObjectB("a", "b")));
        $this->assertIsBool($set1);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function testChecksumByDefault(): void
    {
        $cacheStore = new \Charcoal\Cache\Cache(new DumbCacheStore(), useChecksumsByDefault: true);
        $set1 = $cacheStore->set("test", new SampleObjectA(1, "test", "test@test.com", new SampleObjectB("a", "b")));
        $this->assertInstanceOf(\Charcoal\Buffers\Frames\Bytes20::class, $set1);
    }
}
