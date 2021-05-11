<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
use App\GrowStrategy;

use function Tests\makeField;
use function Tests\makeGame;

final class GrowStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataIsActive()
    {
        // no sun
        yield [0, ['0 0 1 0']];
    }

    /**
     * @dataProvider dataIsActive
     */
    public function testIsActive(int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new GrowStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertFalse($strategy->isActive($game), json_encode(func_get_args()));
    }

    public function dataFilter()
    {
        // ok
        yield [2, 99, ['0 0 1 0', '1 0 1 0']];
        // no sun
        yield [0, 0, ['0 0 1 0']];
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
        $field = makeField();
        $strategy = new GrowStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
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
        $field = makeField();
        $strategy = new GrowStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertEquals(Action::factory(Action::TYPE_GROW, $expected), $strategy->action($game), json_encode(func_get_args()));
    }

    public function testChooseSeed()
    {
        $field = makeField();
        $strategy = new GrowStrategy($field);
        $game = makeGame(['6 1 1 1', '17 0 1 0', '34 1 1 0', '5 3 1 0', '15 2 1 0', '31 2 1 0',]);
        $game->me->sun = 5;

        $this->assertNull($strategy->action($game));
    }

    public function dataScore()
    {
        // 3 soil + 0 size
        yield [4, 0, ['0 0 1 0']];
        yield [6, 0, ['0 2 1 0']];
        // 2 soi + 1 size
        yield [3, 7, ['7 1 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $index, array $trees)
    {
        $field = makeField();
        $strategy = new GrowStrategy($field);
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countScore($game, $game->trees->byIndex($index))->score);
    }
}
