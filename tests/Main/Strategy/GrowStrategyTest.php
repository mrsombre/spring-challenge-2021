<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\GrowStrategy;
use App\Step;
use App\Tree;

use function Tests\initGame;

final class GrowStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataNothing()
    {
        // no trees
        yield [GrowStrategy::SUN_COST, []];
        // no mine trees
        yield [GrowStrategy::SUN_COST, [Tree::factory(0, GrowStrategy::LEVEL, 0)]];
        // dormant
        yield [GrowStrategy::SUN_COST, [Tree::factory(0, GrowStrategy::LEVEL, 1, 1)]];
        // level
        yield [GrowStrategy::SUN_COST, [Tree::factory(0, GrowStrategy::LEVEL)]];
    }

    /**
     * @dataProvider dataNothing
     */
    public function testNothing(int $sun, array $trees)
    {
        $step = new Step();
        $step->sun = $sun;
        $step->setTrees($trees);
        $game = initGame($step);
        $strategy = new GrowStrategy($game);

        $this->assertFalse($strategy->isActive());
    }

    public function dataGrow()
    {
        // only one
        yield [GrowStrategy::LEVEL, [Tree::factory(0, 1)], 0];
        // not dormant
        yield [
            GrowStrategy::LEVEL,
            [
                Tree::factory(10, 1),
                Tree::factory(0, 1),
            ],
            0,
        ];
        // not dormant
        yield [
            GrowStrategy::LEVEL,
            [
                Tree::factory(10, 1),
                Tree::factory(0, 1, 1, 1),
            ],
            10,
        ];
    }

    /**
     * @dataProvider dataGrow
     */
    public function testGrow(int $sun, array $trees, int $expected)
    {
        $game = initGame();
        $game->step->sun = $sun;
        $game->step->setTrees($trees);
        $strategy = new GrowStrategy($game);
        $this->assertTrue($strategy->isActive());

        $this->assertSame("GROW $expected", $strategy->move());
    }
}
