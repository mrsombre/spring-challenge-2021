<?php

use App\GrowStrategy;
use App\SeedStrategy;
use App\ChopStrategy;

use function Tests\makeField;
use function Tests\makeGame;

require_once __DIR__ . '/../tests/bootstrap.php';

$field = makeField(__DIR__ . '/game/field.txt');
$game = makeGame(__DIR__ . '/game/test.txt');

$strategy = new \App\CompositeStrategy(
    new ChopStrategy($field),
    new SeedStrategy($field),
    new GrowStrategy($field),
);
$action = $strategy->action($game);

print_r($action);
