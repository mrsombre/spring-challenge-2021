<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Field;
use App\Game;
use App\Step;

use function Tests\streamFromString;

final class GameTest extends \PHPUnit\Framework\TestCase
{
    public function testInit()
    {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/field.txt');
        $fixture .= file_get_contents(__DIR__ . '/../fixtures/step.txt');

        $stream = streamFromString($fixture);
        $game = Game::init($stream);

        $this->assertInstanceOf(Field::class, $game->field);
        $this->assertInstanceOf(Step::class, $game->step);
    }
}
