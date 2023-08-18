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
 * Class CachedEntityTest
 */
class CachedEntityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     * @throws \Charcoal\Cache\Exception\CachedEntityException
     */
    public function testStringsEncoding(): void
    {
        $cache = new \Charcoal\Cache\Cache(new DumbCacheStore(), plainStringsMaxLength: 32);
        $smallString = "This is a smaller string";
        $longString = "This long string definitely exceeds 32 bytes";
        $exactLenString = str_repeat("\0", 32);

        $encodedSmall = \Charcoal\Cache\CachedEntity::Prepare($cache, "smallStr", $smallString, false);
        $this->assertTrue(is_string($encodedSmall), "Shorter string is still string");
        $this->assertEquals($smallString, $encodedSmall, "Shorter string value is untouched");

        $encodedExactLen = \Charcoal\Cache\CachedEntity::Prepare($cache, "exactLenStr", $exactLenString, false);
        $this->assertEquals($cache->plainStringsMaxLength, strlen($exactLenString), "Exact len string is same as configured maxLength");
        $this->assertTrue(is_string($encodedExactLen), "Exact-len-string was not wrapped in CachedEntity");
        $this->assertEquals($exactLenString, $encodedExactLen, "Exact len value is untouched");

        $encodedLong = \Charcoal\Cache\CachedEntity::Prepare($cache, "longStr", $longString, false);
        $this->assertInstanceOf(\Charcoal\Cache\CachedEntity::class, $encodedLong, "Longer string got wrapped in CachedEntity");
        $serializedLong = \Charcoal\Cache\CachedEntity::Serialize($cache, $encodedLong);
        $this->assertTrue(str_starts_with($serializedLong, $cache->serializedEntityPrefix));

        // Test decoding
        $decodeSmall = \Charcoal\Cache\CachedEntity::Restore($cache, $encodedSmall, false);
        $this->assertEquals($smallString, $decodeSmall, "Decoding smaller length string");

        $decodeExactLen = \Charcoal\Cache\CachedEntity::Restore($cache, $encodedExactLen, false);
        $this->assertEquals($exactLenString, $decodeExactLen, "Decoding exact length string");

        $decodeLong = \Charcoal\Cache\CachedEntity::Restore($cache, $serializedLong, false);
        $this->assertEquals($longString, $decodeLong->getStoredItem(), "Decoding longer-length string");
    }
}
