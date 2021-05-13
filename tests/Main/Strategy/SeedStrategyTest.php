<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\SeedStrategy;

use function Tests\makeGame;

final class SeedStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataIsActive()
    {
        // seed cost
        yield [false, ['0 0 1 0']];
        yield [true, ['0 1 1 0']];
    }

    /**
     * @dataProvider dataIsActive
     */
    public function testIsActive(bool $expected, array $trees)
    {
        $strategy = new SeedStrategy();
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->isActive($game), json_encode(func_get_args()));
    }

    public function dataFilter()
    {
        // dormant
        yield [0, ['0 0 1 1']];
        // small
        yield [0, ['0 1 1 0', '1 0 1 0']];
        // good
        yield [1, ['0 2 1 0']];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, array $trees)
    {
        $game = makeGame($trees);
        $strategy = new SeedStrategy($game);

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function dataMatch()
    {
        yield [[32, 33], ['32 3 1 0']];
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

    public function dataCellScore()
    {
        yield [2, 0, 0, []];
    }

    /**
     * @dataProvider dataCellScore
     */
    public function testCellScore(int $expected, int $index, int $day, array $trees)
    {
        $strategy = new SeedStrategy();
        $game = makeGame($trees);

        $this->assertSame($expected, $strategy->countCellScore($game, $index), json_encode(func_get_args()));
    }

    public function testFindCells()
    {
        $strategy = new SeedStrategy();
        $game = makeGame(['0 3 0 0', '1 0 0 0', '2 2 0 0']);
        $game->field->cells[5] = 0;

        $cells = $strategy->findCells($game, $game->tree(0));
        $this->assertContains(7, $cells);
        $this->assertContains(28, $cells);
        $this->assertNotContains(0, $cells);
        $this->assertNotContains(5, $cells);
        $this->assertNotContains(1, $cells);

        $cells = $strategy->findCells($game, $game->tree(2));
        $this->assertContains(4, $cells);
        $this->assertNotContains(16, $cells);
        $this->assertNotContains(32, $cells);
    }
}
