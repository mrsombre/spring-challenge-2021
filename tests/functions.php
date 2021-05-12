<?php

declare(strict_types=1);

namespace Tests;

use App\Field;
use App\Game;
use App\Player;
use App\Tree;
use App\Action;

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
    $stream = streamFromString($fixture);

    return Field::fromStream($stream);
}

function makeGame(array $treesData = []): Game
{
    $field = makeField();

    $trees = [];
    foreach ($treesData as $tree) {
        if (is_string($tree)) {
            $params = array_filter(
                sscanf($tree, '%d %d %d %d'),
                function ($value) {
                    return !is_null($value);
                }
            );
            $tree = Tree::factory(...$params);
        }
        $trees[] = $tree;
    }

    return new Game(
        $field,
        0,
        20,
        Player::factory(),
        Player::factory(),
        $trees,
        []
    );
}

/**
 * @param array $actionsData
 * @return \App\Action[]
 */
function makeActions(array $actionsData): array
{
    $actions = [];
    foreach ($actionsData as $datum) {
        $actions[] = Action::factory(...sscanf($datum, '%s %d %d'));
    }
    return $actions;
}
