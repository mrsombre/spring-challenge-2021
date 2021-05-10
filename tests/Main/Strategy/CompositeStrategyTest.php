<?php

declare(strict_types=1);

namespace Tests\Main\Strategy;

use App\CompositeStrategy;
use App\AbstractStrategy;
use App\Game;
use App\Action;

use function Tests\makeField;
use function Tests\makeGame;

final class CompositeStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function testAction()
    {
        $field = makeField();
        $strategy = new CompositeStrategy(
            $field,
            [
                new class($field) extends AbstractStrategy {
                    public function action(Game $game): ?Action
                    {
                        return null;
                    }
                },
                new class($field) extends AbstractStrategy {
                    public function action(Game $game): ?Action
                    {
                        return Action::factory();
                    }
                },
            ]
        );
        $game = makeGame();

        $this->assertEquals(Action::factory(), $strategy->action($game));
    }
}
