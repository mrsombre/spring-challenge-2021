<?php

declare(strict_types=1);

namespace App;

function l($message)
{
    if (is_string($message)) {
        error_log($message);
        return;
    }
    error_log(var_export($message, true));
}

final class Game
{
    /** @var \App\Step */
    public $step;
    /** @var \App\Step[] */
    public $steps = [];
    /** @var \App\Field */
    public $field;

    /** @var int */
    public $numberOfSteps = 0;

    public static function init($stream = STDIN): Game
    {
        try {
            $game = new self();

            $game->field = Field::fromStream($stream);
            $game->step(Step::fromStream($stream));

            return $game;
        } catch (\Throwable $e) {
            l($e);
            exit(1);
        }
    }

    public static function run(Game $game, $stream = STDIN)
    {
        try {
            $strategies = [
                new ChopStrategy($game),
            ];
            $bot = new Bot($strategies);

            while (true) {
                echo $bot->move() . "\n";

                if (!is_resource($stream)) {
                    return;
                }

                $game->step(Step::fromStream($stream));
            }
        } catch (\Throwable $e) {
            l($e);
            exit(1);
        }
    }

    public function step(Step $step)
    {
        $this->steps[] = $this->step = $step;
        $this->numberOfSteps++;
    }
}

final class Step
{
    /** @var int */
    public $day = 0;
    /** @var int */
    public $nutrients = 0;
    /** @var int */
    public $sun = 0;
    /** @var int */
    public $score = 0;
    /** @var int */
    public $oppSun = 0;
    /** @var int */
    public $oppScore = 0;
    /** @var int */
    public $oppIsWaiting = false;
    /** @var int */
    public $numberOfTrees = 0;
    /** @var \App\Tree[] */
    public $trees = [];
    /** @var int */
    public $numberOfActions = 0;
    /** @var array */
    public $actions = [];

    /** @var \App\Tree[] */
    public $myTrees = [];

    public static function fromStream($stream): self
    {
        $step = new self();

        fscanf($stream, '%d', $step->day);
        fscanf($stream, '%d', $step->nutrients);
        fscanf($stream, '%d %d', $step->sun, $step->score);
        fscanf($stream, '%d %d %d', $step->oppSun, $step->oppScore, $step->oppIsWaiting);

        fscanf($stream, '%d', $num);
        for ($i = 0; $i < $num; $i++) {
            $tree = new Tree();
            fscanf($stream, '%d %d %d %d', $tree->cellIndex, $tree->size, $tree->isMine, $tree->isDormant);
            $step->trees[$tree->cellIndex] = $tree;
            $step->numberOfTrees++;

            if ($tree->isMine) {
                $step->myTrees[$tree->cellIndex] = $tree;
            }
        }

        fscanf($stream, '%d', $num);
        for ($i = 0; $i < $num; $i++) {
            fscanf($stream, '%s', $action);
            $step->actions[] = $action;
            $step->numberOfActions++;
        }

        return $step;
    }
}

final class Field
{
    /** @var int */
    public $numberOfCells;
    /** @var \App\Cell[] */
    public $cells = [];

    public static function fromStream($stream): self
    {
        $field = new self();

        fscanf($stream, '%d', $num);
        for ($i = 0; $i < $num; $i++) {
            $values = fscanf($stream, '%d %d %d %d %d %d %d %d');
            $cell = new Cell();
            $cell->index = $values[0];
            $cell->richness = $values[1];
            $cell->neighs = array_slice($values, 2);
            $field->cells[$values[0]] = $cell;
            $field->numberOfCells++;
        }

        return $field;
    }

    public function cell($index): Cell
    {
        return $this->cells[$index];
    }

    public function treeCell(Tree $tree): Cell
    {
        return $this->cell($tree->cellIndex);
    }
}

final class Cell
{
    /** @var int */
    public $index;
    /** @var int */
    public $richness;
    /** @var int */
    public $neighs;
}

final class Tree
{
    /** @var int */
    public $cellIndex;
    /** @var int */
    public $size;
    /** @var int */
    public $isMine;
    /** @var int */
    public $isDormant;
}

final class Bot
{
    /** @var \App\AbstractStrategy[] */
    public $strategies = [];

    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
    }

    public function move(): string
    {
        foreach ($this->strategies as $strategy) {
            $move = $strategy->move();
            if ($move !== null) {
                return $move;
            }
        }
        return 'WAIT';
    }
}

abstract class AbstractStrategy
{
    /** @var \App\Game */
    public $game;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    abstract function move(): ?string;
}

final class ChopStrategy extends AbstractStrategy
{
    function move(): ?string
    {
        $field = $this->game->field;
        $step = $this->game->step;

        if ($step->numberOfTrees === 0) {
            return null;
        }
        if ($step->sun < 4) {
            return null;
        }
        if (count($step->myTrees) === 0) {
            return null;
        }

        $candidates = [];
        foreach ($step->myTrees as $tree) {
            $candidates[$tree->cellIndex] = $field->treeCell($tree)->richness;
        }

        arsort($candidates);
        $best = key($candidates);

        return "COMPLETE {$best}";
    }
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';

if ($_ENV['APP_ENV'] !== 'prod') {
    return;
}

$game = Game::init();
Game::run($game);
