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
        yield [1, ['0 3 1 0']];
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

    public function testUseBig()
    {
        $field = makeField();
        $strategy = new SeedStrategy($field);

        $game = makeGame(['0 3 1 0']);
        $this->assertCount(1, $strategy->filterTrees($game));

        $game = makeGame(['0 2 1 0']);
        $this->assertCount(0, $strategy->filterTrees($game));
    }

    public function dataMatch()
    {
        // one
        yield [[0, 1], ['0 3 1 0']];
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch(array $expected, array $trees)
    {
        $strategy = new SeedStrategy();
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->action($game)->params, json_encode(func_get_args()));
    }

    public function dataScore()
    {
        yield [3, 0, ['0 1 1 0', '1 3 1 0']];
        yield [12, 0, ['0 3 1 0', '1 3 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $index, array $trees)
    {
        $strategy = new SeedStrategy();
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countScore($game, $game->tree($index))->params[0], json_encode(func_get_args()));
    }

    public function dataCellScore()
    {
        yield [1, 0, ['1 1 1 0']];
        yield [1, 7, ['1 1 1 0']];
        yield [-1, 7, ['1 1 1 0', '19 1 1 0']];
        yield [0, 2, ['0 3 1 0', '1 3 1 0']];
        yield [1, 8, ['1 3 1 0']];
        yield [2, 9, ['1 3 1 0']];
    }

    /**
     * @dataProvider dataCellScore
     */
    public function testCellScore(int $expected, int $index, array $trees)
    {
        $strategy = new SeedStrategy();
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countCellScore($game, $index)->score, json_encode(func_get_args()));
    }

    public function testFindCells()
    {
        $strategy = new SeedStrategy();
        $game = makeGame(['0 3 0 0', '1 0 0 0']);
        $game->field->cells[5] = 0;

        $cells = $strategy->findCells($game, $game->tree(0));
        $this->assertContains(7, $cells);
        $this->assertNotContains(0, $cells);
        $this->assertNotContains(5, $cells);
        $this->assertNotContains(1, $cells);
    }
}
