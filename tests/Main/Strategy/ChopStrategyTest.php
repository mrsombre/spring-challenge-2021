<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\ChopStrategy;
use App\Step;
use App\Tree;

use function Tests\initGame;

final class ChopStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataNothing()
    {
        // no trees
        yield [ChopStrategy::SUN_COST, []];
        // no mine trees
        yield [ChopStrategy::SUN_COST, [Tree::factory(0, ChopStrategy::LEVEL, 0)]];
        // dormant
        yield [ChopStrategy::SUN_COST, [Tree::factory(0, ChopStrategy::LEVEL, 1, 1)]];
        // level
        yield [ChopStrategy::SUN_COST, [Tree::factory(0, ChopStrategy::LEVEL - 1)]];
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
        $strategy = new ChopStrategy($game);

        $this->assertFalse($strategy->isActive());
    }

    public function dataRichness()
    {
        // only one
        yield [[Tree::factory(0, ChopStrategy::LEVEL)], 0];
        // by soil
        yield [
            [
                Tree::factory(10, 3),
                Tree::factory(0, 3),
            ],
            0,
        ];
    }

    /**
     * @dataProvider dataRichness
     */
    public function testRichness(array $trees, int $expected)
    {
        $game = initGame();
        $game->step->sun = 4;
        $game->step->setTrees($trees);
        $strategy = new ChopStrategy($game);
        $strategy->isActive();

        $this->assertSame("COMPLETE $expected", $strategy->move());
    }
}
