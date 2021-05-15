<?php

use App\CompositeStrategy;
use App\Game;
use App\GrowStrategy;
use App\SeedStrategy;
use App\ChopStrategy;
use App\Action;
use App\Tree;

use function Tests\makeField;
use function Tests\makeGame;
use function Tests\streamFromString;

require_once __DIR__ . '/../tests/bootstrap.php';

$field = makeField(__DIR__ . '/field.txt');
$game = makeGame(__DIR__ . '/game/common.txt');

foreach (range($game->day, 22) as $day) {
    $game->day++;
    $game->me->sun += $game->countSunIncome();

    foreach ($game->trees as $tree) {
        $tree->isDormant = false;
    }

    echo $game->export();
    echo "---\n";

    while (true) {
        $game = Game::fromStream(streamFromString($game->export()));

        $strategy = new CompositeStrategy(
            new ChopStrategy($field),
            new GrowStrategy($field),
            new SeedStrategy($field),
        );
        $action = $strategy->action($game);

        if ($action !== null) {
            echo $action;
        } else {
            echo "---\n";
            break;
        }

        switch ($action->type) {
            case Action::TYPE_GROW:
                $tree = $game->tree($action->params[0]);
                $game->me->sun -= $game->countGrowCost()[$tree->size];
                $tree->size++;
                $tree->isDormant = true;
                break;
            case Action::TYPE_SEED:
                $tree = $game->tree($action->params[0]);
                $game->me->sun -= $game->countSeedCost();
                $tree->isDormant = true;
                $seed = new Tree($action->params[1], 0, true, true);
                $game->trees[$seed->index] = $seed;
                ksort($game->trees);
                break;
            case Action::TYPE_COMPLETE:
                $tree = $game->tree($action->params[0]);
                $game->me->sun -= Game::CHOP_COST;
                $game->me->score += $game->countCellScore($field->byIndex($tree->index));
                if ($game->nutrients > 0) {
                    $game->nutrients -= 1;
                }
                unset($game->trees[$tree->index]);
                ksort($game->trees);
                break;
        }
    }
}

echo "---\n";

$game = Game::fromStream(streamFromString($game->export()));
$trees = json_encode($game->countTreesBySize());

echo "sun = {$game->me->sun}\n";
echo "trees = $trees\n";
