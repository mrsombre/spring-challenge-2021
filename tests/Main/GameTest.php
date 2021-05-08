<?php

declare(strict_types=1);

namespace Tests\Main;

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
        $this->assertSame(18, $game->sun);
        $this->assertSame(1, $game->score);

        $this->assertSame(19, $game->oppSun);
        $this->assertSame(2, $game->oppScore);
        $this->assertSame(false, $game->oppIsWaiting);

        $this->assertSame(12, $game->numberOfTrees);
        $this->assertSame(7, $game->trees->byIndex(7)->index);
        $this->assertSame(3, $game->trees->byIndex(7)->size);
        $this->assertSame(false, $game->trees->byIndex(7)->isMine);
        $this->assertSame(false, $game->trees->byIndex(7)->isDormant);

        $this->assertSame(7, $game->numberOfActions);
        $this->assertSame('WAIT', $game->actions[0]);
    }

    public function dataGrowCost()
    {
        // size 0
        yield [[makeTree(0, 0)], 1];

        yield [
            [
                makeTree(0, 0),
                makeTree(1, 1),
            ],
            2,
        ];

        // size 1
        yield [[makeTree(0, 1)], 3];

        yield [
            [
                makeTree(0, 1),
                makeTree(1, 2),
            ],
            4,
        ];

        // size 2
        yield [[makeTree(0, 2)], 7];

        yield [
            [
                makeTree(0, 2),
                makeTree(1, 3),
            ],
            8,
        ];
    }

    /**
     * @dataProvider dataGrowCost
     */
    public function testGrowCost(array $trees, int $cost)
    {
        $game = makeGame($trees);

        $this->assertSame($cost, $game->countGrowCost($game->trees->byIndex(0)->size));
    }
}
