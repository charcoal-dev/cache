<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests;

use Charcoal\Buffers\Frames\Bytes20;
use Charcoal\Cache\CachedReferenceKey;
use Charcoal\Cache\Exception\CachedEntityError;
use Charcoal\Cache\Exception\CachedEntityException;
use Charcoal\Cache\Tests\Polyfill\LocalCache;

/**
 * Class CachedRefKeysTest
 */
class CachedRefKeysTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     * @throws Exception\CachedEntityException
     */
    public function testSameServerRef(): void
    {
        $cacheStore = new Cache(new LocalCache(1));
        $refKey = CachedReferenceKey::Serialize($cacheStore, "users_id:1100786");
        $this->assertEquals("~~charcoalCachedRef[~][users_id:1100786](*)", $refKey);

        $reference = CachedReferenceKey::Unserialize($cacheStore, $refKey);
        $this->assertInstanceOf(CachedReferenceKey::class, $reference);
        $this->assertNull($reference->targetServerId);
        $this->assertEquals("users_id:1100786", $reference->targetKey);
        $this->assertNull($reference->targetChecksum);
    }

    /**
     * @return void
     * @throws CachedEntityException
     */
    public function testLongSerializedReference(): void
    {
        $cacheStore = new Cache(new LocalCache(1), plainStringsMaxLength: 128);
        $this->expectException(CachedEntityException::class);
        $this->expectExceptionCode(CachedEntityError::REF_KEY_LENGTH->value);
        CachedReferenceKey::Serialize(
            $cacheStore,
            str_repeat("a1b2c3d4e5f6", 8),
            null,
            new Bytes20(str_repeat("\0", 20)),
        );
    }

    /**
     * @return void
     * @throws Exception\CachedEntityException
     */
    public function testInvalidChecksum(): void
    {
        $cacheStore = new Cache(new DumbCacheStore(1));
        $serialized = "~~charcoalCachedRef[~][users_id:1100786]()";
        $this->expectException("InvalidArgumentException");
        CachedReferenceKey::Unserialize($cacheStore, $serialized);
    }

    /**
     * @return void
     * @throws Exception\CacheDriverOpException
     * @throws Exception\CacheException
     * @throws Exception\CachedEntityException
     */
    public function testCacheReferences(): void
    {
        $cacheStore = new Cache(new DumbCacheStore(1));
        $model = new SampleObjectA(1, "charcoal", "test@charcoal.dev", new SampleObjectB("a", "b"));
        $checksum = $cacheStore->set("exampleModel_1", $model, createChecksum: true);
        $cacheStore->createReferenceKey("exampleModel_charcoal", "exampleModel_1", checksum: $checksum);
        unset($model); // unsets model in memory

        $retrieved = $cacheStore->get("exampleModel_charcoal");
        $this->assertInstanceOf(CachedReferenceKey::class, $retrieved);
        $model = $retrieved->resolve($cacheStore);
        $this->assertInstanceOf(SampleObjectA::class, $model);
    }

    /**
     * @return void
     * @throws Exception\CacheException
     */
    public function testCacheReferenceArray1(): void
    {
        $cacheStore1 = new Cache(new DumbCacheStore(1));
        $cacheStore2 = new Cache(new DumbCacheStore(2));
        $cacheStore3 = new Cache(new DumbCacheStore(3));
        $cacheArray = new CacheArray();
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore2)
            ->addServer($cacheStore3);

        // Store model in Cache Store # 3
        $model = new SampleObjectA(1, "charcoal", "test@charcoal.dev", new SampleObjectB("a", "b"));
        $cacheStore3->set("user_1", $model, createChecksum: false);
        unset($model); // unset $model

        // Set reference in Cache Store # 1
        $cacheStore1->createReferenceKey("user_charcoal", "user_1", targetKeyServer: $cacheStore3, checksum: null);

        // find reference key in array
        $rfSearch = $cacheArray->getFromAll("user_charcoal");
        foreach ($rfSearch->successList as $cacheStoreValue) {
            if ($cacheStoreValue instanceof CachedReferenceKey) {
                $foundReferenceKey = $cacheStoreValue;
                break;
            }
        }

        if (!isset($foundReferenceKey)) {
            $this->fail('Could not find reference key');
        }

        $model = $foundReferenceKey->resolve($cacheArray->getServer($foundReferenceKey->targetServerId));
        $this->assertInstanceOf(SampleObjectA::class, $model);
        $this->assertEquals("charcoal", $model->username);
    }

    /**
     * @return void
     * @throws Exception\CacheDriverOpException
     * @throws Exception\CacheException
     * @throws Exception\CachedEntityException
     */
    public function testCacheReferenceArray2(): void
    {
        $cacheStore1 = new Cache(new DumbCacheStore(1));
        $cacheStore2 = new Cache(new DumbCacheStore(2));
        $cacheStore3 = new Cache(new DumbCacheStore(3));

        // Store model in Cache Store # 3
        $model = new SampleObjectA(1, "charcoal", "test@charcoal.dev", new SampleObjectB("a", "b"));
        $cacheStore3->set("user_1", $model, createChecksum: false);
        unset($model); // unset $model

        // Set reference in Cache Store # 2
        $cacheStore2->createReferenceKey("user_charcoal", "user_1", targetKeyServer: $cacheStore3, checksum: null);

        // Create CacheArray and pass it to resolve method
        $cacheArray = new CacheArray();
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore2)
            ->addServer($cacheStore3);

        // Fetch key from cacheStore2 but pass CacheArray to resolve
        $ref = $cacheStore2->get("user_charcoal");
        $this->assertInstanceOf(CachedReferenceKey::class, $ref);
        $model = $ref->resolve($cacheArray);
        $this->assertInstanceOf(SampleObjectA::class, $model);
        $this->assertEquals("charcoal", $model->username);
    }
}
