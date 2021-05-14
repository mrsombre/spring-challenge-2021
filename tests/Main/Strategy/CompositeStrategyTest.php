<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\CompositeStrategy;
use App\AbstractStrategy;
use App\Game;
use App\Action;

use function Tests\makeGameTrees;

final class CompositeStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function testAction()
    {
        $strategy = new CompositeStrategy(
            [
                new class() extends AbstractStrategy {
                    public function action(Game $game): ?Action
                    {
                        return null;
                    }
                },
                new class() extends AbstractStrategy {
                    public function action(Game $game): ?Action
                    {
                        return Action::factory();
                    }
                },
            ]
        );
        $game = makeGameTrees();

        $this->assertEquals(Action::factory(), $strategy->action($game));
    }
}
