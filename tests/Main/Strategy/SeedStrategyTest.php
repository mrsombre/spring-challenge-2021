<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\SeedStrategy;

use function Tests\makeField;
use function Tests\makeGame;
use function Tests\makeActions;

final class SeedStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataIsActive()
    {
        // no sun
        yield [0, ['0 0 1 0']];
        // > grow 1
        yield [99, ['0 0 1 0', '1 0 1 0']];
    }

    /**
     * @dataProvider dataIsActive
     */
    public function testIsActive(int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;
        $game->actions = makeActions(['SEED 0 1']);

        $this->assertFalse($strategy->isActive($game), json_encode(func_get_args()));
    }

    public function dataDisabledEndGame()
    {
        // disabled
        yield [false, ['0 0 1 0', '0 0 1 0']];
        // enabled free
        yield [true, ['0 1 1 0']];
    }

    /**
     * @dataProvider dataDisabledEndGame
     */
    public function testDisabledEndGame(bool $expected, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = 99;
        $game->day = 17;
        $game->actions = makeActions(['SEED 0 1']);

        $this->assertSame($expected, $strategy->isActive($game), json_encode(func_get_args()));
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

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function testDontUseBig()
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame(['0 3 1 0']);

        $game->day = 17;
        $this->assertCount(0, $strategy->filterTrees($game));

        $game->day--;
        $this->assertCount(1, $strategy->filterTrees($game));
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
        // basic
        yield [0, 1, ['SEED 1 0', 'SEED 1 7'], ['1 1 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $index, array $actions, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);
        $game->actions = makeActions($actions);

        $this->assertSame($expected, $strategy->countScore($game, $game->trees->byIndex($index))->params[0]);
    }

    public function dataCellScore()
    {
        // basic soil 3 + neigh
        yield [3, 0, ['0 1 1 0', '1 1 1 0']];
        // basic soil 2 + neigh
        yield [1, 7, ['7 1 1 0', '1 1 1 0']];
        // basic soil 2 + neigh seed
        yield [2, 7, ['7 1 1 0', '1 0 1 0']];
    }

    /**
     * @dataProvider dataCellScore
     */
    public function testCellScore(int $expected, int $index, array $trees)
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countCellScore($game, $index)->score);
    }
}
