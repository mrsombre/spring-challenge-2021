<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Cache;

class CacheTest extends \PHPUnit\Framework\TestCase
{
    public function dataGetSet()
    {
        yield [1];
        yield [true];
        yield [null];
        yield [['array']];
    }

    /**
     * @dataProvider dataGetSet
     */
    public function testGetSet($data)
    {
        $cache = $this->mockCache();
        $cache->cacheSet('test', $data);

        $this->assertSame($data, $cache->cacheGet('test'), json_encode(func_get_args()));
    }

    public function testDefault()
    {
        $cache = $this->mockCache();

        $this->assertSame('test', $cache->cacheGet('test', 'test'));
    }

    public function testClear()
    {
        $cache = $this->mockCache();
        $cache->cacheSet('test', 'test');
        $cache->cacheClear();

        $this->assertNull($cache->cacheGet('test'));
    }

    /**
     * @return Cache
     */
    private function mockCache()
    {
        return $this->getMockForTrait(Cache::class);
    }
}
