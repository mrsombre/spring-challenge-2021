<?php

declare(strict_types=1);

namespace Tests;

use App\Field;
use App\Game;
use App\Step;

function streamFromString(string $string)
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $string);
    rewind($stream);
    return $stream;
}

function initGame(Step $step = null): Game
{
    $fixture = file_get_contents(__DIR__ . '/fixtures/field.txt');

    $game = new Game();
    $game->field = Field::fromStream(streamFromString($fixture));

    if (!$step) {
        $step = new Step();
    }
    $game->step($step);

    return $game;
}

function gameTrees(Game $game, array $trees)
{
    $stepData = [
        '0',
        '0',
        '0 0',
        '0 0 0',
        count($trees),
    ];

    foreach ($trees as $tree) {
        $stepData[] = $tree;
    }

    $stepData[] = 1;
    $stepData[] = 'WAIT';

    $game->step(Step::fromStream(streamFromString(implode("\n", $stepData))));
}
