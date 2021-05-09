<?php

declare(strict_types=1);

namespace Tests;

use App\Field;
use App\Game;
use App\Player;
use App\Tree;
use App\Trees;

function streamFromString(string $string)
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $string);
    rewind($stream);
    return $stream;
}

function makeField(string $file = null): Field
{
    if ($file === null) {
        $file = __DIR__ . '/fixtures/field.txt';
    }

    $fixture = file_get_contents($file);
    return Field::fromStream(streamFromString($fixture));
}

function makeGame(array $trees = [])
{
    return new Game(
        0,
        20,
        Player::factory(),
        Player::factory(),
        new Trees($trees),
        []
    );
}

function makeTree(int $index, int $size, bool $isMine = true, bool $isDormant = false)
{
    return new Tree($index, $size, $isMine, $isDormant);
}
