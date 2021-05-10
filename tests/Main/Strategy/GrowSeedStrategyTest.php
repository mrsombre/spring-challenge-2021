<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\GrowSeedStrategy;

use function Tests\makeField;
use function Tests\makeGame;

final class GrowSeedStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function dataFilter()
    {
        // ok
        yield [1, 1, ['0 0 1 0']];
        // big
        yield [0, 3, ['0 1 1 0']];
    }

    /**
     * @dataProvider dataFilter
     */
    public function testFilter(int $expected, int $sun, array $trees)
    {
        $field = makeField();
        $strategy = new GrowSeedStrategy($field);
        $game = makeGame($trees);
        $game->me->sun = $sun;

        $this->assertCount($expected, $strategy->filterTrees($game));
    }
}
