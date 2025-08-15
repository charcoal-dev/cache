<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests;

use Charcoal\Cache\CacheClient;
use Charcoal\Cache\CachedEntity;
use Charcoal\Cache\Tests\Polyfill\LocalCache;

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
        $cache = new CacheClient(new LocalCache(), plainStringsMaxLength: 32, staticScopeReplaceExisting: true);
        $smallString = "This is a smaller string";
        $longString = "This long string definitely exceeds 32 bytes";
        $exactLenString = str_repeat("\0", 32);

        $encodedSmall = CachedEntity::Prepare($cache, "smallStr", $smallString, false);
        $this->assertTrue(is_string($encodedSmall), "Shorter string is still string");
        $this->assertEquals($smallString, $encodedSmall, "Shorter string value is untouched");

        $encodedExactLen = CachedEntity::Prepare($cache, "exactLenStr", $exactLenString, false);
        $this->assertEquals($cache->plainStringsMaxLength, strlen($exactLenString), "Exact len string is same as configured maxLength");
        $this->assertTrue(is_string($encodedExactLen), "Exact-len-string was not wrapped in CachedEntity");
        $this->assertEquals($exactLenString, $encodedExactLen, "Exact len value is untouched");

        $encodedLong = CachedEntity::Prepare($cache, "longStr", $longString, false);
        $this->assertInstanceOf(CachedEntity::class, $encodedLong, "Longer string got wrapped in CachedEntity");
        $serializedLong = CachedEntity::Serialize($cache, $encodedLong);
        $this->assertTrue(str_starts_with($serializedLong, $cache->serializedEntityPrefix));

        // Test decoding
        $decodeSmall = CachedEntity::Restore($cache, $encodedSmall, false);
        $this->assertEquals($smallString, $decodeSmall, "Decoding smaller length string");

        $decodeExactLen = CachedEntity::Restore($cache, $encodedExactLen, false);
        $this->assertEquals($exactLenString, $decodeExactLen, "Decoding exact length string");

        $decodeLong = CachedEntity::Restore($cache, $serializedLong, false);
        $this->assertEquals($longString, $decodeLong->getStoredItem(), "Decoding longer-length string");
    }
}
