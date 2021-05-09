<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
use App\ChopStrategy;

use function Tests\makeField;
use function Tests\makeGame;

final class ChopStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataNothing()
    {
        // no mine
        yield [4, ['0 0 0 0']];
        // no sun
        yield [0, ['0 3 1 0']];
    }

    /**
     * @dataProvider dataNothing
     */
    public function testNothing(int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertNull($strategy->action($game));
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

        $this->assertCount($expected, $strategy->filterTrees($game->trees->getMine()));
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

        $this->assertEquals(Action::factory(Action::TYPE_COMPLETE, $expected), $strategy->action($game));
    }

    public function dataScore()
    {
        yield [3, 0, ['0 3 1 0']];
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

        $this->assertSame($expected, $strategy->countScore($game->trees->byIndex($index)));
    }
}
