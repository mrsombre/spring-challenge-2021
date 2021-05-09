<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
use App\GrowStrategy;

use function Tests\makeField;
use function Tests\makeGame;

final class GrowStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataNothing()
    {
        // no mine
        yield [1, ['0 0 0 0']];
        // no sun
        yield [0, ['0 0 1 0']];
    }

    /**
     * @dataProvider dataNothing
     */
    public function testNothing(int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new GrowStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertNull($strategy->action($game));
    }

    public function dataFilter()
    {
        // ok
        yield [1, ['0 0 1 0']];
        // big
        yield [0, ['0 3 1 0']];
        // dormant
        yield [0, ['0 0 1 1']];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, array $trees)
    {
        $field = makeField();
        $strategy = new GrowStrategy($field);
        $game = makeGame($trees);

        $this->assertCount($expected, $strategy->filterTrees($game->trees->getMine()));
    }

    public function dataMatch()
    {
        // one
        yield [0, 1, ['0 0 1 0']];
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

        $this->assertEquals(Action::factory(Action::TYPE_GROW, $expected), $strategy->action($game));
    }

    public function dataScore()
    {
        yield [3, 0, ['0 0 1 0']];
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

        $this->assertSame($expected, $strategy->countScore($game->trees->byIndex($index)));
    }
}
