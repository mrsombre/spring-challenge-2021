<?php

use function Tests\makeField;
use function Tests\makeGame;

require_once __DIR__ . '/../tests/bootstrap.php';

$field = makeField(__DIR__ . '/game/field.txt');
$game = makeGame(__DIR__ . '/game/test.txt');

$strategy = new \App\Mcts($field);
echo $strategy->action($game);
