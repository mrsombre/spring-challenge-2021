<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
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
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

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
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function dataMatch()
    {
        // one
        yield [0, 22, ['0 3 1 0']];
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch(int $expected, int $day, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);
        $game->day = $day;
        $game->me->sun = 4;

        $this->assertEquals(Action::factory(Action::TYPE_COMPLETE, $expected), $strategy->action($game), json_encode(func_get_args()));
    }

    public function dataScore()
    {
        yield [4, 0, ['0 3 1 0']];
        yield [2, 7, ['7 3 1 0']];
        yield [3, 7, ['7 3 1 0', '1 3 1 0']];
        yield [1, 7, ['7 3 1 0', '1 3 0 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $index, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countScore($game, $game->trees->byIndex($index))->score, json_encode(func_get_args()));
    }
}
