<?php

declare(strict_types=1);

namespace Tests;

use App\Field;
use App\Game;
use App\Tree;

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

function makeGame(
    array $trees,
    int $day = 0,
    int $nutrients = 20,
    int $sun = 0,
    int $score = 0,
    int $oppSun = 0,
    int $oppScore = 0,
    bool $oppIsWaiting = false
) {
    return new Game(
        $day,
        $nutrients,
        $sun,
        $score,
        $oppSun,
        $oppScore,
        $oppIsWaiting,
        $trees,
        []
    );
}

function makeTree(int $index, int $size, bool $isMine = true, bool $isDormant = false)
{
    return new Tree($index, $size, $isMine, $isDormant);
}
