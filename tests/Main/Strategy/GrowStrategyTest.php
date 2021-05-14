<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
use App\GrowStrategy;

use function Tests\makeField;
use function Tests\makeGameTrees;

final class GrowStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataFilter()
    {
        // dormant
        yield [0, 99, ['0 0 1 1']];
        // big
        yield [0, 99, ['0 3 1 0']];
        // good
        yield [1, 1, ['0 0 1 0']];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, int $sun, array $trees)
    {
        $strategy = new GrowStrategy(makeField());
        $game = makeGameTrees($trees);
        $game->me->sun = $sun;

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function dataMatch()
    {
        // one
        yield [29, 99, ['29 1 1 0', '32 1 1 0']];
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch(int $expected, int $sun, array $trees)
    {
        $strategy = new GrowStrategy(makeField());
        $game = makeGameTrees($trees);
        $game->me->sun = $sun;

        $this->assertEquals(Action::factory(Action::TYPE_GROW, $expected), $strategy->action($game), json_encode(func_get_args()));
    }
}
