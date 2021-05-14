<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Shadow;

use function Tests\makeField;
use function Tests\makeGameTrees;

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
        $field = makeField();
        $shadow = new Shadow($field);

        $this->assertSame($expected, $shadow->sunDirection($day), json_encode(func_get_args()));
    }

    public function dataShadowIn()
    {
        yield [[1 => 4, 2 => 13, 3 => 28], 0, 0];
        yield [[], 31, 7];
        yield [[1 => 11, 2 => 25], 3, 5];
    }

    /**
     * @dataProvider dataShadowIn
     */
    public function testShadowIn(array $expected, int $index, int $day)
    {
        $field = makeField();
        $shadow = new Shadow($field);

        $this->assertSame($expected, $shadow->shadowIn($index, $day), json_encode(func_get_args()));
    }

    public function dataShadowOut()
    {
        yield [[1 => 1, 2 => 7, 3 => 19], 0, 0];
        yield [[], 22, 7];
        yield [[1 => 17, 2 => 34], 6, 5];
    }

    /**
     * @dataProvider dataShadowOut
     */
    public function testShadowOut(array $expected, int $index, int $day)
    {
        $field = makeField();
        $shadow = new Shadow($field);

        $this->assertSame($expected, $shadow->shadowOut($index, $day), json_encode(func_get_args()));
    }

    public function dataIsShadow()
    {
        // basic
        yield [true, 15, 1, 0, ['15 1 1 0', '31 1 0 0']];
        // long
        yield [true, 19, 6, 0, ['19 3 1 0', '0 3 0 0']];
        // no spooky
        yield [false, 17, 3, 0, ['17 3 1 0', '34 2 0 0']];
    }

    /**
     * @dataProvider dataIsShadow
     */
    public function testIsShadow(bool $expected, int $index, int $day, int $interval, array $trees)
    {
        $field = makeField();
        $shadow = new Shadow($field);
        $game = makeGameTrees($trees);

        $this->assertSame($expected, $shadow->isShadow($game->trees, $index, $game->tree($index)->size ?? 0, $day), json_encode(func_get_args()));
    }

    public function dataCountSun()
    {
        yield [6, 0, 0, 4, []];
        yield [3, 0, 22, 1, ['0 3 1 0']];
        yield [0, 2, 0, 1, ['0 3 1 0', '2 3 1 0']];
    }

    /**
     * @dataProvider dataCountSun
     */
    public function testCountSun(int $expected, int $index, int $day, int $days, array $trees)
    {
        $field = makeField();
        $shadow = new Shadow($field);
        $game = makeGameTrees($trees);

        $this->assertSame($expected, $shadow->countSun($game->trees, $index, $day, $days), json_encode(func_get_args()));
    }
}
