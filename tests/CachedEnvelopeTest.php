<?php
/**
 * Part of the "charcoal-dev/cache" package.
 * @link https://github.com/charcoal-dev/cache
 */

declare(strict_types=1);

namespace Charcoal\Cache\Tests;

use Charcoal\Cache\CacheClient;
use Charcoal\Cache\Stored\CachedEnvelope;
use Charcoal\Cache\Tests\Stubs\LocalCache;

/**
 * Class CachedEnvelopeTest
 * @package Charcoal\Cache\Tests
 */
class CachedEnvelopeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     * @throws \Charcoal\Cache\Exceptions\CachedEnvelopeException
     */
    public function testStringsEncoding(): void
    {
        $cache = new CacheClient(new LocalCache(), plainStringsMaxLength: 32);
        $smallString = "This is a smaller string";
        $longString = "This long string definitely exceeds 32 bytes";
        $exactLenString = str_repeat("\0", 32);

        $encodedSmall = CachedEnvelope::Prepare($cache, "smallStr", $smallString, false);
        $this->assertTrue(is_string($encodedSmall), "Shorter string is still string");
        $this->assertEquals($smallString, $encodedSmall, "Shorter string value is untouched");

        $encodedExactLen = CachedEnvelope::Prepare($cache, "exactLenStr", $exactLenString, false);
        $this->assertEquals($cache->plainStringsMaxLength, strlen($exactLenString), "Exact len string is same as configured maxLength");
        $this->assertTrue(is_string($encodedExactLen), "Exact-len-string was not wrapped in CachedEntity");
        $this->assertEquals($exactLenString, $encodedExactLen, "Exact len value is untouched");

        $encodedLong = CachedEnvelope::Prepare($cache, "longStr", $longString, false);
        $this->assertInstanceOf(CachedEnvelope::class, $encodedLong, "Longer string got wrapped in CachedEntity");
        $serializedLong = CachedEnvelope::Seal($cache, $encodedLong);
        $this->assertTrue(str_starts_with($serializedLong, $cache->serializedEntityPrefix));

        // Test decoding
        $decodeSmall = CachedEnvelope::Open($cache, $encodedSmall, false);
        $this->assertEquals($smallString, $decodeSmall, "Decoding smaller length string");

        $decodeExactLen = CachedEnvelope::Open($cache, $encodedExactLen, false);
        $this->assertEquals($exactLenString, $decodeExactLen, "Decoding exact length string");

        $decodeLong = CachedEnvelope::Open($cache, $serializedLong, false);
        $this->assertEquals($longString, $decodeLong->getStoredItem(), "Decoding longer-length string");
    }
}
