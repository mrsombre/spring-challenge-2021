<?php

declare(strict_types=1);

namespace App;

use ArrayObject;
use InvalidArgumentException;
use Throwable;

function l($message)
{
    if ($_ENV['APP_ENV'] === 'test') {
        return;
    }

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
        } catch (Throwable $e) {
            l($e);
            exit(1);
        }
    }

    public static function run(Game $game, $stream = STDIN)
    {
        try {
            $strategies = [
                new ChopStrategy($game),
                new GrowStrategy($game),
            ];
            $bot = new Bot($strategies);

            while (true) {
                echo $bot->move() . "\n";

                if (!is_resource($stream)) {
                    return;
                }

                $game->step(Step::fromStream($stream));
            }
        } catch (Throwable $e) {
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
    public $oppIsWaiting = 0;
    /** @var int */
    public $numberOfTrees = 0;
    /** @var \App\Trees */
    public $trees;
    /** @var int */
    public $numberOfActions = 0;
    /** @var array */
    public $actions = [];

    public static function fromStream($stream): self
    {
        $step = new self();

        fscanf($stream, '%d', $step->day);
        fscanf($stream, '%d', $step->nutrients);
        fscanf($stream, '%d %d', $step->sun, $step->score);
        fscanf($stream, '%d %d %d', $step->oppSun, $step->oppScore, $step->oppIsWaiting);

        fscanf($stream, '%d', $num);
        $trees = [];
        for ($i = 0; $i < $num; $i++) {
            $trees[] = Tree::fromStream($stream);
        }
        $step->setTrees($trees);

        fscanf($stream, '%d', $num);
        for ($i = 0; $i < $num; $i++) {
            fscanf($stream, '%s', $action);
            $step->actions[] = $action;
            $step->numberOfActions++;
        }

        return $step;
    }

    /**
     * @param \App\Tree[] $trees
     */
    public function setTrees(array $trees): self
    {
        $this->trees = new Trees($trees);
        $this->numberOfTrees = $this->trees->count();
        return $this;
    }

    public function countGrowCost(int $size): int
    {
        // Growing a size 1 tree into a size 2 tree costs 3 sun points + the number of size 2 trees you already own.
        if ($size === 1) {
            $num = 0;
            foreach ($this->trees->getMine() as $tree) {
                if ($tree->size === 2) {
                    $num++;
                }
            }
            return 3 + $num;
        }
        // Growing a size 2 tree into a size 3 tree costs 7 sun points + the number of size 3 trees you already own.
        if ($size === 2) {
            $num = 0;
            foreach ($this->trees->getMine() as $tree) {
                if ($tree->size === 3) {
                    $num++;
                }
            }
            return 7 + $num;
        }
        throw new InvalidArgumentException("Invalid size.");
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

    public function byTree(Tree $tree): Cell
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

    public static function fromStream($stream): self
    {
        $tree = new Tree();
        fscanf($stream, '%d %d %d %d', $tree->cellIndex, $tree->size, $tree->isMine, $tree->isDormant);
        return $tree;
    }

    public static function factory(int $cellIndex, int $size = 1, int $isMine = 1, int $isDormant = 0): self
    {
        $tree = new self();
        $tree->cellIndex = $cellIndex;
        $tree->size = $size;
        $tree->isMine = $isMine;
        $tree->isDormant = $isDormant;
        return $tree;
    }
}

class Trees extends ArrayObject
{
    /** @var int[] */
    public $mine = [];

    public function __construct($list = [])
    {
        $indexedList = [];
        /** @var \App\Tree $tree */
        foreach ($list as $tree) {
            $indexedList[$tree->cellIndex] = $tree;
            if ($tree->isMine) {
                $this->mine[$tree->cellIndex] = $tree->cellIndex;
            }
        }
        parent::__construct($indexedList);
    }

    /**
     * @return \App\Tree[]
     */
    public function getMine(): array
    {
        $result = [];
        foreach ($this->mine as $index) {
            $result[$index] = $this->byIndex($index);
        }
        return $result;
    }

    public function byIndex(int $index): Tree
    {
        return $this->offsetGet($index);
    }
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
            if (!$strategy->isActive()) {
                continue;
            }
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
    /** @var \App\Field */
    public $field;

    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->field = $game->field;
    }

    function isActive(): bool
    {
        return true;
    }

    abstract function move(): ?string;
}

final class ChopStrategy extends AbstractStrategy
{
    public const LEVEL = 3;
    public const SUN_COST = 4;

    /** @var \App\Tree[] */
    private $mine;

    function isActive(): bool
    {
        $step = $this->game->step;

        if ($step->numberOfTrees === 0) {
            return false;
        }
        if ($step->sun < self::SUN_COST) {
            return false;
        }

        $mine = $step->trees->getMine();
        if (count($mine) === 0) {
            return false;
        }

        $mine = array_filter(
            $mine,
            function (Tree $tree) {
                if ($tree->size !== self::LEVEL) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                return true;
            }
        );
        if ($mine === []) {
            return false;
        }
        $this->mine = $mine;

        return true;
    }

    function move(): ?string
    {
        $cell = $this->findCell();
        if ($cell === null) {
            return null;
        }
        return "COMPLETE $cell";
    }

    private function findCell(): ?int
    {
        $trees = [];
        foreach ($this->mine as $tree) {
            $score = 0;
            $score += $this->field->byTree($tree)->richness;

            $trees[$tree->cellIndex] = $score;
        }

        arsort($trees);
        return key($trees);
    }
}

final class GrowStrategy extends AbstractStrategy
{
    public const LEVEL = 3;
    public const SUN_COST = 3;

    private $lvl2cost;
    private $lvl3cost;
    /** @var \App\Tree[] */
    private $mine;

    function isActive(): bool
    {
        $step = $this->game->step;

        if ($step->numberOfTrees === 0) {
            return false;
        }
        if ($step->sun < self::SUN_COST) {
            return false;
        }

        $mine = $step->trees->getMine();
        if (count($mine) === 0) {
            return false;
        }

        $this->lvl2cost = $step->countGrowCost(1);
        $this->lvl3cost = $step->countGrowCost(2);
        // no sun to grow
        if ($step->sun < $this->lvl2cost && $step->sun < $this->lvl3cost) {
            return false;
        }

        $mine = array_filter(
            $mine,
            function (Tree $tree) {
                if ($tree->size === self::LEVEL) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                return true;
            }
        );
        if ($mine === []) {
            return false;
        }
        $this->mine = $mine;

        return true;
    }

    function move(): ?string
    {
        $cell = $this->findCell();
        if ($cell === null) {
            return null;
        }
        return "GROW $cell";
    }

    private function findCell(): ?int
    {
        if ($this->lvl2cost < $this->lvl3cost) {
            if (($id = $this->findTree(1)) !== null) {
                return $id;
            }
        }
        return $this->findTree(2);
    }

    private function findTree(int $lvl): ?int
    {
        $candidates = [];
        foreach ($this->mine as $tree) {
            if ($tree->size !== $lvl) {
                continue;
            }

            $score = 0;
            $score += $this->field->byTree($tree)->richness;

            $candidates[$tree->cellIndex] = $score;
        }

        arsort($candidates);
        return key($candidates);
    }
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';

if ($_ENV['APP_ENV'] !== 'prod') {
    return;
}

$game = Game::init();
Game::run($game);
