<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\ChopStrategy;

use function Tests\gameTrees;
use function Tests\initGame;
use function Tests\makeStep;

final class ChopStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function testNothing()
    {
        $game = initGame();

        $strategy = new ChopStrategy($game);
        $this->assertNull($strategy->move());
    }

    public function testSun()
    {
        $game = initGame();
        $game->step->sun = 3;

        $strategy = new ChopStrategy($game);
        $this->assertNull($strategy->move());
    }

    public function testSoil()
    {
        $game = initGame();
        gameTrees(
            $game,
            [
                '2 3 1 0',
                '10 3 1 0',
            ]
        );
        $game->step->sun = 4;

        $strategy = new ChopStrategy($game);
        $this->assertSame('COMPLETE 2', $strategy->move());
    }
}
