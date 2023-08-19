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
}
