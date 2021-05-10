<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
use App\ChopStrategy;

use function Tests\makeField;
use function Tests\makeGame;

final class ChopStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function testWaitForBig()
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);

        // don't allow if big < 3
        $game = makeGame(['0 3 1 0']);
        $game->me->sun = 4;
        $this->assertFalse($strategy->isActive($game));

        // big >= 3
        $game = makeGame(['0 3 1 0', '1 3 1 0', '2 3 1 0', '3 3 1 0']);
        $game->me->sun = 4;
        $this->assertTrue($strategy->isActive($game));

        // allow after day 22
        $game = makeGame(['0 3 1 0']);
        $game->me->sun = 4;
        $game->day = 22;
        $this->assertTrue($strategy->isActive($game));
    }

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

        $this->assertCount($expected, $strategy->filterTrees($game));
    }

    public function dataMatch()
    {
        // one
        yield [0, 4, ['0 3 1 0']];
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch(int $expected, int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;
        $game->day = 23;

        $this->assertEquals(Action::factory(Action::TYPE_COMPLETE, $expected), $strategy->action($game));
    }

    public function dataScore()
    {
        yield [4, 0, ['0 3 1 0']];
        yield [2, 7, ['7 3 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $index, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countScore($game, $game->trees->byIndex($index))->score);
    }
}
