<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\GrowStrategy;

use function Tests\makeField;
use function Tests\makeGame;
use function Tests\makeTree;

final class GrowStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataFilter()
    {
        // level
        yield [0, [makeTree(0, 0)]];
        // dormant
        yield [0, [makeTree(0, 1, true, true)]];
        // ok
        yield [1, [makeTree(0, 1)]];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, array $trees)
    {
        $field = makeField();
        $strategy = new GrowStrategy($field, 1);
        $game = makeGame($trees);

        $this->assertCount($expected, $strategy->filter($game->trees->getMine()));
    }

    public function dataNothing()
    {
        // no trees
        yield [GrowStrategy::SUN_COST, []];
        // no mine trees
        yield [GrowStrategy::SUN_COST, [makeTree(0, 1, false)]];
        // no sun
        yield [2, [makeTree(0, 1)]];
    }

    /**
     * @dataProvider dataNothing
     */
    public function testNothing(int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new GrowStrategy($field, 1);
        $game = makeGame($trees);
        $game->sun = $sun;

        $this->assertNull($strategy->action($game));
    }

    public function dataGrow()
    {
        // only one
        yield [0, [makeTree(0, 1)]];
        // by soil
        yield [
            0,
            [
                makeTree(10, 1),
                makeTree(0, 1),
            ],
        ];
    }

    /**
     * @dataProvider dataGrow
     */
    public function testGrow(int $expected, array $trees)
    {
        $field = makeField();
        $strategy = new GrowStrategy($field, 1);
        $game = makeGame($trees);
        $game->sun = 3;

        $this->assertSame("GROW $expected", $strategy->action($game));
    }
}
