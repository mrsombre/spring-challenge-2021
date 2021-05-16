<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Game;

use function Tests\streamFromString;
use function Tests\makeGameTrees;

final class GameTest extends \PHPUnit\Framework\TestCase
{
    public function testFactory()
    {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/game.txt');
        $stream = streamFromString($fixture);
        $gameData = Game::fromStream($stream);
        $game = new Game(...$gameData);

        $this->assertSame(0, $game->day);
        $this->assertSame(20, $game->nutrients);
        $this->assertSame(18, $game->me->sun);
        $this->assertSame(1, $game->me->score);

        $this->assertSame(19, $game->opp->sun);
        $this->assertSame(2, $game->opp->score);
        $this->assertSame(false, $game->opp->isWaiting);

        $this->assertSame(12, count($game->trees));
        $this->assertSame(7, $game->tree(7)->index);
        $this->assertSame(3, $game->tree(7)->size);
        $this->assertFalse($game->tree(7)->isMine);
        $this->assertFalse($game->tree(7)->isDormant);
        $this->assertArrayHasKey(12, $game->mine);
        $this->assertEquals(6, $game->countTreesBySize(true)[3]);
        $this->assertEquals(0, $game->countTreesBySize(true)[0]);
        $this->assertEquals(6, $game->countTreesBySize(false)[3]);

        $this->assertSame(0, count($game->actions));
    }

    public function dataCountTreesBySize()
    {
        yield [[0 => 1, 1 => 0, 2 => 0, 3 => 0], ['0 0 1 0']];
    }

    /**
     * @dataProvider dataCountTreesBySize
     */
    public function testCountTreesBySize(array $expected, array $trees)
    {
        $game = makeGameTrees($trees);

        $this->assertSame($expected, $game->countTreesBySize());
    }

    public function dataCountGrowCost()
    {
        // size 0
        yield [1, 0, ['0 0 1 0']];
        yield [2, 0, ['0 1 1 0']];

        // size 1~
        yield [3, 1, ['0 1 1 0']];
        yield [4, 1, ['0 2 1 0']];

        // size 2
        yield [7, 2, ['0 2 1 0']];
        yield [8, 2, ['0 3 1 0']];
    }

    /**
     * @dataProvider dataCountGrowCost
     */
    public function testCountGrowCost(int $expected, int $size, array $trees)
    {
        $game = makeGameTrees($trees);

        $this->assertSame($expected, $game->countGrowCost()[$size], json_encode(func_get_args()));
    }

    public function dataCountSeedCost()
    {
        yield [0, ['0 1 1 0']];
        yield [1, ['0 0 1 0']];
    }

    /**
     * @dataProvider dataCountSeedCost
     */
    public function testCountSeedCost(int $expected, array $trees)
    {
        $game = makeGameTrees($trees);

        $this->assertSame($expected, $game->countSeedCost(), json_encode(func_get_args()));
    }
}
