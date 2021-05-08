<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\ChopStrategy;

use function Tests\makeField;
use function Tests\makeGame;
use function Tests\makeTree;

final class ChopStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataFilter()
    {
        // level
        yield [0, [makeTree(0, ChopStrategy::MIN_LEVEL - 1)]];
        // dormant
        yield [0, [makeTree(0, ChopStrategy::MIN_LEVEL, true, true)]];
        // ok
        yield [1, [makeTree(0, ChopStrategy::MIN_LEVEL)]];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);

        $this->assertCount($expected, $strategy->filter($game->trees->getMine()));
    }

    public function dataNothing()
    {
        // no trees
        yield [ChopStrategy::SUN_COST, []];
        // no mine trees
        yield [ChopStrategy::SUN_COST, [makeTree(0, ChopStrategy::MIN_LEVEL, false)]];
        // no sun
        yield [ChopStrategy::SUN_COST - 1, [makeTree(0, ChopStrategy::MIN_LEVEL)]];
    }

    /**
     * @dataProvider dataNothing
     */
    public function testNothing(int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);
        $game->sun = $sun;

        $this->assertNull($strategy->move($game));
    }

    public function dataRichness()
    {
        // only one
        yield [0, [makeTree(0, ChopStrategy::MIN_LEVEL)]];
        // by soil
        yield [
            0,
            [
                makeTree(10, 3),
                makeTree(0, 3),
            ],
        ];
    }

    /**
     * @dataProvider dataRichness
     */
    public function testRichness(int $expected, array $trees)
    {
        $field = makeField();
        $strategy = new ChopStrategy($field);
        $game = makeGame($trees);
        $game->sun = ChopStrategy::SUN_COST;

        $this->assertSame("COMPLETE $expected", $strategy->move($game));
    }
}
