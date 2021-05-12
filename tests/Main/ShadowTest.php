<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Shadow;

use function Tests\makeField;
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
        $field = makeField();
        $game = makeGame();
        $shadow = new Shadow($field, $game);

        $this->assertSame($expected, $shadow->sunDirection($day));
    }

    public function dataIsShadow()
    {
        // basic
        yield [true, 1, 15, ['15 1 0 0', '31 1 0 0']];
        // long
        yield [true, 6, 19, ['19 3 0 0', '0 3 0 0']];
        // no spooky
        yield [false, 3, 17, ['17 3 0 0', '34 2 0 0']];
    }

    /**
     * @dataProvider dataIsShadow
     */
    public function testIsShadow(bool $expected, int $day, int $index, array $trees)
    {
        $field = makeField();
        $game = makeGame($trees);
        $shadow = new Shadow($field, $game);

        $this->assertSame($expected, $shadow->isShadow($index, $day), json_encode(func_get_args()));
    }

    public function dataShadowVector()
    {
        yield [[], 6, 0, ['28 1 1 0']];
        yield [[13], 0, 28, ['28 1 1 0']];
        yield [[13, 4], 0, 28, ['28 2 1 0']];
        yield [[13, 4, 0], 0, 28, ['28 3 1 0']];
    }

    /**
     * @dataProvider dataShadowVector
     */
    public function testShadowVector(array $expected, int $day, int $index, array $trees)
    {
        $field = makeField();
        $game = makeGame($trees);
        $shadow = new Shadow($field, $game);

        $this->assertSame($expected, $shadow->shadowVector($index, $day), json_encode(func_get_args()));
    }
}
