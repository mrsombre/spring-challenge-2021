<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Action;
use App\Game;

use function Tests\streamFromString;
use function Tests\makeGame;
use function Tests\makeTree;

final class GameTest extends \PHPUnit\Framework\TestCase
{
    public function testFactory()
    {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/step.txt');

        $game = Game::fromStream(streamFromString($fixture));

        $this->assertSame(0, $game->day);
        $this->assertSame(20, $game->nutrients);
        $this->assertSame(18, $game->me->sun);
        $this->assertSame(1, $game->me->score);

        $this->assertSame(19, $game->opp->sun);
        $this->assertSame(2, $game->opp->score);
        $this->assertSame(false, $game->opp->isWaiting);

        $this->assertSame(12, $game->trees->numberOfTrees);
        $this->assertSame(7, $game->trees->byIndex(7)->index);
        $this->assertSame(3, $game->trees->byIndex(7)->size);
        $this->assertSame(false, $game->trees->byIndex(7)->isMine);
        $this->assertSame(false, $game->trees->byIndex(7)->isDormant);

        $this->assertSame(7, count($game->actions));
        $this->assertSame('WAIT', $game->actions[0]->type);
        $this->assertEquals(Action::factory('COMPLETE 22'), $game->actions[1]);
    }

    public function dataGrowCost()
    {
        // size 0
        yield [1, 0, [makeTree(0, 0)]];
        yield [2, 0, [makeTree(0, 1)]];

        // size 1
        yield [3, 1, [makeTree(0, 1)]];
        yield [4, 1, [makeTree(0, 2)]];

        // size 2
        yield [7, 2, [makeTree(0, 2)]];
        yield [8, 2, [makeTree(0, 3)]];
    }

    /**
     * @dataProvider dataGrowCost
     */
    public function testGrowCost(int $expected, int $size, array $trees)
    {
        $game = makeGame($trees);

        $this->assertSame($expected, $game->countGrowCost()[$size]);
    }

    public function dataSeedCost()
    {
        // size 0
        yield [0, [makeTree(0, 1)]];
        yield [1, [makeTree(0, 0)]];
    }

    /**
     * @dataProvider dataSeedCost
     */
    public function testSeedCost(int $expected, array $trees)
    {
        $game = makeGame($trees);

        $this->assertSame($expected, $game->countSeedCost());
    }
}
