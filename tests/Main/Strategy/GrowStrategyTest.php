<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
use App\GrowStrategy;

use function Tests\makeGame;

final class GrowStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataFilter()
    {
        // one
        yield [1, 1, ['0 0 1 0']];
        // max
        yield [0, 99, ['0 3 1 0']];
        // dormant
        yield [0, 99, ['0 0 1 1']];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, int $sun, array $trees)
    {
        $strategy = new GrowStrategy();
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function testChooseBiggest()
    {
        $strategy = new GrowStrategy();

        $game = makeGame(['0 0 1 0', '1 1 1 0']);
        $game->me->sun = 1;
        $this->assertSame([], $strategy->filterTrees($game), json_encode(func_get_args()));

        $game = makeGame(['0 0 1 0', '1 1 1 0']);
        $game->me->sun = 3;
        $this->assertSame([$game->tree(1)], $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function dataMatch()
    {
        // one
        yield [0, 99, ['0 0 1 0', '1 0 1 0']];
        // choose soil
        yield [1, 99, ['7 0 1 0', '1 0 1 0']];
        // choose size
        yield [31, 3, ['15 0 1 0', '17 0 1 0', '31 1 1 0', '34 1 1 0']];
        // choose size
        yield [5, 8, ['6 0 1 0', '17 0 1 0', '34 1 1 0', '5 2 1 0', '15 2 1 0', '31 2 1 0',]];
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch(int $expected, int $sun, array $trees)
    {
        $strategy = new GrowStrategy();
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertEquals(Action::factory(Action::TYPE_GROW, $expected), $strategy->action($game), json_encode(func_get_args()));
    }

    public function dataScore()
    {
        // 3 soil + 0 size
        yield [3, 0, ['0 0 1 0']];
        yield [3, 0, ['0 2 1 0']];
        // 2 soil + 1 size
        yield [2, 7, ['7 1 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $index, array $trees)
    {
        $strategy = new GrowStrategy();
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countScore($game, $game->tree($index))->score, json_encode(func_get_args()));
    }
}
