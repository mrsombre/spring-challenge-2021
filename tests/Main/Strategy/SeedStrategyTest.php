<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\SeedStrategy;

use function Tests\makeField;
use function Tests\makeGame;
use function Tests\makeActions;

final class SeedStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataNothing()
    {
        // no mine
        yield [0, ['0 0 0 0']];
        // no sun
        yield [0, ['0 0 1 0']];
    }

    /**
     * @dataProvider dataNothing
     */
    public function testNothing(int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertNull($strategy->action($game));
    }

    public function dataFilter()
    {
        // ok
        yield [1, ['0 1 1 0']];
        // only seeds
        yield [0, ['0 0 1 0']];
        // dormant
        yield [0, ['0 1 1 1']];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);

        $this->assertCount($expected, $strategy->filterTrees($game->trees->getMine()));
    }

    public function dataMatch()
    {
        // one
        yield [[0, 1], ['SEED 0 1'], ['0 1 1 0']];
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch(array $expected, array $actions, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);
        $game->actions = makeActions($actions);

        $this->assertSame($expected, $strategy->action($game)->params);
    }

    public function dataScore()
    {
        yield [5, 1, ['0 1 1 0']];
        yield [3, 18, ['6 1 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $index, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countScore($index, $game->trees));
    }
}
