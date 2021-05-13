<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\ChopStrategy;

use function Tests\makeField;
use function Tests\makeGame;

final class ChopStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataIsActive()
    {
        // no sun
        yield [0, ['0 3 1 0']];
    }

    /**
     * @dataProvider dataIsActive
     */
    public function testIsActive(int $sun, array $trees)
    {
        $game = makeGame($trees);
        $game->me->sun = $sun;
        $strategy = new ChopStrategy($game);

        $this->assertFalse($strategy->isActive($game), json_encode(func_get_args()));
    }

    public function dataFilter()
    {
        // ok
        yield [1, ['0 3 1 0']];
        // small
        yield [0, ['0 0 1 0']];
        // dormant
        yield [0, ['0 3 1 1']];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, array $trees)
    {
        $game = makeGame($trees);
        $strategy = new ChopStrategy($game);

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function dataScore()
    {
        yield [null, 0, ['0 3 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore($expected, int $index, array $trees)
    {
        $game = makeGame($trees);
        $game->me->sun = 4;
        $strategy = new ChopStrategy($game);

        $this->assertSame($expected, $strategy->action($game)->params[0] ?? null, json_encode(func_get_args()));
    }
}
