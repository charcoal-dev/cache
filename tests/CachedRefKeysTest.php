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
 * Class CachedRefKeysTest
 */
class CachedRefKeysTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testSameServerRef(): void
    {
        $cacheStore = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $refKey = \Charcoal\Cache\CachedReferenceKey::Serialize($cacheStore, "users_id:1100786");
        $this->assertEquals("~~charcoalCachedRef[~][users_id:1100786](*)", $refKey);

        $reference = \Charcoal\Cache\CachedReferenceKey::Unserialize($cacheStore, $refKey);
        $this->assertInstanceOf(\Charcoal\Cache\CachedReferenceKey::class, $reference);
        $this->assertNull($reference->targetServerId);
        $this->assertEquals("users_id:1100786", $reference->targetKey);
        $this->assertNull($reference->targetChecksum);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testLongSerializedReference(): void
    {
        $cacheStore = new \Charcoal\Cache\Cache(new DumbCacheStore(1), plainStringsMaxLength: 128);
        $this->expectException(\Charcoal\Cache\Exception\CachedEntityException::class);
        $this->expectExceptionCode(\Charcoal\Cache\Exception\CachedEntityError::REF_KEY_LENGTH->value);
        \Charcoal\Cache\CachedReferenceKey::Serialize(
            $cacheStore,
            str_repeat("a1b2c3d4e5f6", 8),
            null,
            new \Charcoal\Buffers\Frames\Bytes20(str_repeat("\0", 20)),
        );
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testInvalidChecksum(): void
    {
        $cacheStore = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $serialized = "~~charcoalCachedRef[~][users_id:1100786]()";
        $this->expectException("InvalidArgumentException");
        \Charcoal\Cache\CachedReferenceKey::Unserialize($cacheStore, $serialized);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CacheException
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testCacheReferences(): void
    {
        $cacheStore = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $model = new ExampleModelA(1, "charcoal", "test@charcoal.dev", new ExampleModelB("a", "b"));
        $checksum = $cacheStore->set("exampleModel_1", $model, createChecksum: true);
        $cacheStore->createReferenceKey("exampleModel_charcoal", "exampleModel_1", checksum: $checksum);
        unset($model); // unsets model in memory

        $retrieved = $cacheStore->get("exampleModel_charcoal");
        $this->assertInstanceOf(\Charcoal\Cache\CachedReferenceKey::class, $retrieved);
        $model = $retrieved->resolve($cacheStore);
        $this->assertInstanceOf(ExampleModelA::class, $model);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheException
     */
    public function testCacheReferenceArray1(): void
    {
        $cacheStore1 = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $cacheStore2 = new \Charcoal\Cache\Cache(new DumbCacheStore(2));
        $cacheStore3 = new \Charcoal\Cache\Cache(new DumbCacheStore(3));
        $cacheArray = new \Charcoal\Cache\CacheArray();
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore2)
            ->addServer($cacheStore3);

        // Store model in Cache Store # 3
        $model = new ExampleModelA(1, "charcoal", "test@charcoal.dev", new ExampleModelB("a", "b"));
        $cacheStore3->set("user_1", $model, createChecksum: false);
        unset($model); // unset $model

        // Set reference in Cache Store # 1
        $cacheStore1->createReferenceKey("user_charcoal", "user_1", targetKeyServer: $cacheStore3, checksum: null);

        // find reference key in array
        $rfSearch = $cacheArray->getFromAll("user_charcoal");
        foreach ($rfSearch->successList as $cacheStoreValue) {
            if ($cacheStoreValue instanceof \Charcoal\Cache\CachedReferenceKey) {
                $foundReferenceKey = $cacheStoreValue;
                break;
            }
        }

        if (!isset($foundReferenceKey)) {
            $this->fail('Could not find reference key');
        }

        $model = $foundReferenceKey->resolve($cacheArray->getServer($foundReferenceKey->targetServerId));
        $this->assertInstanceOf(ExampleModelA::class, $model);
        $this->assertEquals("charcoal", $model->username);
    }

    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CacheDriverOpException
     * @throws \Charcoal\Cache\Exception\CacheException
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testCacheReferenceArray2(): void
    {
        $cacheStore1 = new \Charcoal\Cache\Cache(new DumbCacheStore(1));
        $cacheStore2 = new \Charcoal\Cache\Cache(new DumbCacheStore(2));
        $cacheStore3 = new \Charcoal\Cache\Cache(new DumbCacheStore(3));

        // Store model in Cache Store # 3
        $model = new ExampleModelA(1, "charcoal", "test@charcoal.dev", new ExampleModelB("a", "b"));
        $cacheStore3->set("user_1", $model, createChecksum: false);
        unset($model); // unset $model

        // Set reference in Cache Store # 2
        $cacheStore2->createReferenceKey("user_charcoal", "user_1", targetKeyServer: $cacheStore3, checksum: null);

        // Create CacheArray and pass it to resolve method
        $cacheArray = new \Charcoal\Cache\CacheArray();
        $cacheArray->addServer($cacheStore1)
            ->addServer($cacheStore2)
            ->addServer($cacheStore3);

        // Fetch key from cacheStore2 but pass CacheArray to resolve
        $ref = $cacheStore2->get("user_charcoal");
        $this->assertInstanceOf(\Charcoal\Cache\CachedReferenceKey::class, $ref);
        $model = $ref->resolve($cacheArray);
        $this->assertInstanceOf(ExampleModelA::class, $model);
        $this->assertEquals("charcoal", $model->username);
    }
}
