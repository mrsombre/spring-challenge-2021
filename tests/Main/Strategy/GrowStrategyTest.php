<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\Action;
use App\GrowStrategy;

use function Tests\makeGame;

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
        $strategy = new GrowStrategy();
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertCount($expected, $strategy->filterTrees($game), json_encode(func_get_args()));
    }

    public function dataMatch()
    {
        // one
        yield [0, 99, ['0 0 1 0', '1 0 1 0']];
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch(int $expected, int $sun, array $trees)
    {
        $strategy = new GrowStrategy();
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertEquals(Action::factory(Action::TYPE_GROW, $expected), $strategy->action($game), json_encode(func_get_args()));
    }

    public function dataScore()
    {
        yield [1, 3, ['0 0 1 0', '1 1 1 0']];
        yield [1, 7, ['0 1 1 0', '1 2 1 0']];
    }

    /**
     * @dataProvider dataScore
     */
    public function testScore(int $expected, int $sun, array $trees)
    {
        $strategy = new GrowStrategy();
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertSame($expected, $strategy->action($game)->params[0], json_encode(func_get_args()));
    }
}
