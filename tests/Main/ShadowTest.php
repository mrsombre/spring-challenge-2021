<?php

declare(strict_types=1);

namespace Tests\Main;

use function Tests\makeGame;

final class ShadowTest extends \PHPUnit\Framework\TestCase
{
    public function dataSunDirection()
    {
        yield [0, 0];
        yield [3, 3];
        yield [5, 5];
        yield [0, 6];
        yield [5, 23];
    }

    /**
     * @dataProvider dataSunDirection
     */
    public function testSunDirection(int $expected, int $day)
    {
        $game = makeGame();

        $this->assertSame($expected, $game->shadow->sunDirection($day), json_encode(func_get_args()));
    }

    public function dataIsShadow()
    {
        // basic
        yield [true, 15, 1, 0, ['15 1 1 0', '31 1 0 0']];
        // long
        yield [true, 19, 6, 0, ['19 3 1 0', '0 3 0 0']];
        // no spooky
        yield [false, 17, 3, 0, ['17 3 1 0', '34 2 0 0']];
        // interval
        yield [true, 17, 2, 1, ['17 3 1 0', '34 2 0 0']];
    }

    /**
     * @dataProvider dataIsShadow
     */
    public function testIsShadow(bool $expected, int $index, int $day, int $interval, array $trees)
    {
        $game = makeGame($trees);

        $this->assertSame($expected, $game->shadow->isShadow($index, $game->tree($index)->size, $day, $interval), json_encode(func_get_args()));
    }

    public function dataShadowVector()
    {
        yield [[], 28, 6, ['28 0 1 0']];
        yield [[13], 28, 0, ['28 1 1 0']];
        yield [[13, 4], 28, 0, ['28 2 1 0']];
        yield [[13, 4, 0], 28, 0, ['28 3 1 0']];
    }

    /**
     * @dataProvider dataShadowVector
     */
    public function testShadowVector(array $expected, int $index, int $day, array $trees)
    {
        $game = makeGame($trees);

        $this->assertSame($expected, $game->shadow->shadowVector($index, $game->tree($index)->size, $day), json_encode(func_get_args()));
    }

    public function dataCountSun()
    {
        yield [7, 35, 7, 4, ['35 3 1 0', '1 1 1 0', '2 1 1 0', '19 0 0 0']];

//        yield [3, 0, 0, 1, ['0 3 1 0']];
//        yield [6, 0, 0, 2, ['0 3 1 0']];
//        yield [6, 0, 0, 4, ['0 0 1 1']];
//        yield [2, 0, 0, 1, ['0 1 1 0']];
//        yield [0, 0, 0, 1, ['0 1 1 0', '2 1 1 0']];
//        yield [4, 0, 0, 1, ['0 1 1 0', '2 1 0 0']];
//        yield [3, 0, 0, 4, ['0 0 1 0', '2 1 1 0', '3 0 1 0']];
    }

    /**
     * @dataProvider dataCountSun
     */
    public function testCountSun(int $expected, int $index, int $day, int $days, array $trees)
    {
        $game = makeGame($trees);
        $game->day = $day;

        $this->assertSame($expected, $game->shadow->countSun($index, $game->tree($index)->size, $days), json_encode(func_get_args()));
    }
}
