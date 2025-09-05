<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests;

use Charcoal\Buffers\Types\Bytes20;
use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Enums\CachedEnvelopeError;
use Charcoal\Cache\Exceptions\CachedEnvelopeException;
use Charcoal\Cache\Pool\CachePool;
use Charcoal\Cache\Stored\CachedReferenceKey;
use Charcoal\Cache\Tests\Fixtures\SampleObjectA;
use Charcoal\Cache\Tests\Fixtures\SampleObjectB;
use Charcoal\Cache\Tests\Stubs\LocalCache;

/**
 * Class CachedRefKeysTest
 */
class CachedRefKeysTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @throws CachedEnvelopeException
     */
    public function testSameServerRef(): void
    {
        $cacheStore = new CacheClient(new LocalCache());
        $refKey = CachedReferenceKey::Serialize($cacheStore, "users_id:1100786");
        $this->assertEquals("~~charcoalCachedRef[~][users_id:1100786](*)", $refKey);

        $reference = CachedReferenceKey::Unserialize($cacheStore, $refKey);
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(CachedReferenceKey::class, $reference);
        $this->assertNull($reference->targetServerId);
        $this->assertEquals("users_id:1100786", $reference->targetKey);
        $this->assertNull($reference->targetChecksum);
    }

    /**
     * @throws CachedEnvelopeException
     */
    public function testLongSerializedReference(): void
    {
        $cacheStore = new CacheClient(new LocalCache(), plainStringsMaxLength: 128);
        $this->expectException(CachedEnvelopeException::class);
        $this->expectExceptionCode(CachedEnvelopeError::REF_KEY_LENGTH->value);
        CachedReferenceKey::Serialize(
            $cacheStore,
            str_repeat("a1b2c3d4e5f6", 8),
            null,
            new Bytes20(str_repeat("\0", 20)),
        );
    }

    /**
     * @return void
     * @throws CachedEnvelopeException
     */
    public function testInvalidChecksum(): void
    {
        $cacheStore = new CacheClient(new LocalCache());
        $serialized = "~~charcoalCachedRef[~][users_id:1100786]()";
        $this->expectException("InvalidArgumentException");
        CachedReferenceKey::Unserialize($cacheStore, $serialized);
    }

    /**
     * @return void
     * @throws CachedEnvelopeException
     * @throws \Charcoal\Cache\Exceptions\CacheStoreOpException
     * @throws \Charcoal\Cache\Exceptions\CacheException
     */
    public function testCacheReferences(): void
    {
        $cacheStore = new CacheClient(new LocalCache());
        $model = new SampleObjectA(1, "charcoal", "test@charcoal.dev", new SampleObjectB("a", "b"));
        $checksum = $cacheStore->set("exampleModel_1", $model, withChecksum: true);
        $cacheStore->createReferenceKey("exampleModel_charcoal", "exampleModel_1", checksum: $checksum);
        unset($model); // unsets model in memory

        $retrieved = $cacheStore->get("exampleModel_charcoal");
        $this->assertInstanceOf(CachedReferenceKey::class, $retrieved);
        $model = $retrieved->resolve($cacheStore);
        $this->assertInstanceOf(SampleObjectA::class, $model);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CacheException
     */
    public function testCacheReferenceArray1(): void
    {
        $cacheStore1 = new CacheClient(new LocalCache(1));
        $cacheStore2 = new CacheClient(new LocalCache(2));
        $cacheStore3 = new CacheClient(new LocalCache(3));
        $cacheArray = new CachePool("pool1");
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore2)
            ->addServer($cacheStore3);

        // Store model in Cache Store # 3
        $model = new SampleObjectA(1, "charcoal", "test@charcoal.dev", new SampleObjectB("a", "b"));
        $cacheStore3->set("user_1", $model, withChecksum: false);
        unset($model); // unset $model

        // Set reference in Cache Store # 1
        $cacheStore1->createReferenceKey("user_charcoal", "user_1", targetKeyServer: $cacheStore3, checksum: null);

        // find reference key in an array
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
     * @throws CachedEnvelopeException
     * @throws \Charcoal\Cache\Exceptions\CacheStoreOpException
     * @throws \Charcoal\Cache\Exceptions\CacheException
     */
    public function testCacheReferenceArray2(): void
    {
        $cacheStore1 = new CacheClient(new LocalCache(1));
        $cacheStore2 = new CacheClient(new LocalCache(2));
        $cacheStore3 = new CacheClient(new LocalCache(3));

        // Store model in Cache Store # 3
        $model = new SampleObjectA(1, "charcoal", "test@charcoal.dev", new SampleObjectB("a", "b"));
        $cacheStore3->set("user_1", $model, withChecksum: false);
        unset($model); // unset $model

        // Set reference in Cache Store # 2
        $cacheStore2->createReferenceKey("user_charcoal", "user_1", targetKeyServer: $cacheStore3, checksum: null);

        // Create CacheArray and pass it to resolve method
        $cacheArray = new CachePool("pool2");
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
