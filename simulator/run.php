<?php

use App\Shadow;
use App\SeedStrategy;
use App\ChopStrategy;

use function Tests\makeField;
use function Tests\makeGame;

require_once __DIR__ . '/../tests/bootstrap.php';

$field = makeField(__DIR__ . '/field.txt');
$game = makeGame(__DIR__ . '/game/test.txt');

$strategy = new ChopStrategy($field);
$action = $strategy->action($game);

print_r($action);
