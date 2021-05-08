<?php

declare(strict_types=1);

namespace App;

use ArrayObject;
use InvalidArgumentException;

function l($message)
{
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'test') {
        return;
    }

    if (is_string($message)) {
        error_log($message);
        return;
    }
    error_log(var_export($message, true));
}

final class Field
{
    /** @var \App\Cell[] */
    public $cells;
    /** @var int */
    public $numberOfCells;

    public static function fromStream($stream): self
    {
        fscanf($stream, '%d', $num);

        $cells = [];
        for ($i = 0; $i < $num; $i++) {
            $cell = Cell::fromStream($stream);
            $cells[$cell->index] = $cell;
        }

        return new self($cells);
    }

    /**
     * @param \App\Cell[] $cells
     */
    public function __construct(array $cells)
    {
        $cellsIndexed = [];
        foreach ($cells as $cell) {
            $cellsIndexed[$cell->index] = $cell;
        }
        $this->cells = $cellsIndexed;
        $this->numberOfCells = count($cells);
    }

    public function byIndex($index): Cell
    {
        return $this->cells[$index];
    }

    public function byTree(Tree $tree): Cell
    {
        return $this->byIndex($tree->index);
    }
}

final class Cell
{
    /** @var int */
    public $index;
    /** @var int */
    public $richness;
    /** @var int[] */
    public $neighs;

    public static function fromStream($stream): self
    {
        $data = fscanf($stream, '%d %d %d %d %d %d %d %d');
        return new self($data[0], $data[1], array_slice($data, 2));
    }

    public function __construct(int $index, int $richness, array $neighs)
    {
        $this->index = $index;
        $this->richness = $richness;
        $this->neighs = $neighs;
    }
}

final class Game
{
    /** @var int */
    public $day;
    /** @var int */
    public $nutrients;
    /** @var int */
    public $sun;
    /** @var int */
    public $score;
    /** @var int */
    public $oppSun;
    /** @var int */
    public $oppScore;
    /** @var bool */
    public $oppIsWaiting;
    /** @var int */
    public $numberOfTrees;
    /** @var \App\Trees */
    public $trees;
    /** @var int */
    public $numberOfActions;
    /** @var array */
    public $actions;

    public static function fromStream($stream): self
    {
        fscanf($stream, '%d', $day);
        fscanf($stream, '%d', $nutrients);
        fscanf($stream, '%d %d', $sun, $score);
        fscanf($stream, '%d %d %d', $oppSun, $oppScore, $oppIsWaiting);

        fscanf($stream, '%d', $num);
        $trees = [];
        for ($i = 0; $i < $num; $i++) {
            $trees[] = Tree::fromStream($stream);
        }

        fscanf($stream, '%d', $num);
        $actions = [];
        for ($i = 0; $i < $num; $i++) {
            fscanf($stream, '%s', $action);
            $actions[] = $action;
        }

        return new self(
            $day,
            $nutrients,
            $sun,
            $score,
            $oppSun,
            $oppScore,
            (bool) $oppIsWaiting,
            $trees,
            $actions
        );
    }

    public function __construct(
        int $day,
        int $nutrients,
        int $sun,
        int $score,
        int $oppSun,
        int $oppScore,
        bool $oppIsWaiting,
        array $trees,
        array $actions
    ) {
        $this->day = $day;
        $this->nutrients = $nutrients;
        $this->sun = $sun;
        $this->score = $score;
        $this->oppSun = $oppSun;
        $this->oppScore = $oppScore;
        $this->oppIsWaiting = $oppIsWaiting;

        $this->trees = new Trees($trees);
        $this->numberOfTrees = $this->trees->count();

        $this->actions = $actions;
        $this->numberOfActions = count($actions);
    }

    public function countGrowCost(int $size): int
    {
        // Growing a seed into a size 1 tree costs 1 sun point + the number of size 1 trees you already own.
        if ($size === 0) {
            $num = 0;
            foreach ($this->trees->getMine() as $tree) {
                if ($tree->size === 1) {
                    $num++;
                }
            }
            return 1 + $num;
        }
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

final class Tree
{
    /** @var int */
    public $index;
    /** @var int */
    public $size;
    /** @var bool */
    public $isMine;
    /** @var bool */
    public $isDormant;

    public static function fromStream($stream): self
    {
        $data = fscanf($stream, '%d %d %d %d');
        return new self($data[0], $data[1], (bool) $data[2], (bool) $data[3]);
    }

    public function __construct(int $index, int $size, bool $isMine, bool $isDormant)
    {
        $this->index = $index;
        $this->size = $size;
        $this->isMine = $isMine;
        $this->isDormant = $isDormant;
    }
}

class Trees extends ArrayObject
{
    /** @var int */
    public $numberOfMine;
    /** @var int[] */
    public $mine = [];

    public function __construct($trees = [])
    {
        $treesIndexed = [];
        /** @var \App\Tree $tree */
        foreach ($trees as $tree) {
            $treesIndexed[$tree->index] = $tree;
            if ($tree->isMine) {
                $this->mine[$tree->index] = $tree->index;
            }
        }
        $this->numberOfMine = count($this->mine);

        parent::__construct($treesIndexed);
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
    /** @var \App\Field */
    public $field;

    public function __construct(Field $field)
    {
        $this->field = $field;
    }

    abstract function move(Game $game): ?string;
}

final class ChopStrategy extends AbstractStrategy
{
    public const MIN_LEVEL = 3;
    public const SUN_COST = 4;

    function move(Game $game): ?string
    {
        if ($game->sun < self::SUN_COST) {
            return null;
        }
        if ($game->trees->numberOfMine === 0) {
            return null;
        }
        $mine = $this->filter($game->trees->getMine());
        if (count($mine) === 0) {
            return null;
        }

        $cell = $this->findCell($mine);
        if ($cell === null) {
            return null;
        }
        return "COMPLETE $cell";
    }

    /**
     * @param \App\Tree[] $trees
     * @return \App\Tree[]
     */
    public function filter(array $trees): array
    {
        return array_filter(
            $trees,
            function (Tree $tree) {
                if ($tree->size < self::MIN_LEVEL) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                return true;
            }
        );
    }

    /**
     * @param \App\Tree[] $mine
     * @return int
     */
    public function findCell(array $mine): ?int
    {
        $pool = [];
        foreach ($mine as $tree) {
            $score = 0;
            $score += $this->field->byTree($tree)->richness;

            $pool[$tree->index] = $score;
        }

        arsort($pool);
        return key($pool);
    }
}

final class GrowStrategy extends AbstractStrategy
{
    public const MAX_LEVEL = 3;
    public const SUN_COST = 1;

    private $size;

    public function __construct(Field $field, int $size)
    {
        if ($size >= self::MAX_LEVEL) {
            throw new InvalidArgumentException("Invalid size {$size}.");
        }
        $this->size = $size;
        parent::__construct($field);
    }

    function move(Game $game): ?string
    {
        $cost = $game->countGrowCost($this->size);
        if ($game->sun < $cost) {
            return null;
        }
        if ($game->trees->numberOfMine === 0) {
            return null;
        }
        $mine = $this->filter($game->trees->getMine());
        if (count($mine) === 0) {
            return null;
        }

        $cell = $this->findCell($mine);
        if ($cell === null) {
            return null;
        }
        return "GROW $cell";
    }

    /**
     * @param \App\Tree[] $trees
     * @return \App\Tree[]
     */
    public function filter(array $trees): array
    {
        return array_filter(
            $trees,
            function (Tree $tree) {
                if ($tree->size !== $this->size) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                return true;
            }
        );
    }

    /**
     * @param \App\Tree[] $mine
     * @return int
     */
    public function findCell(array $mine): ?int
    {
        $pool = [];
        foreach ($mine as $tree) {
            $score = 0;
            $score += $this->field->byTree($tree)->richness;

            $pool[$tree->index] = $score;
        }

        arsort($pool);
        return key($pool);
    }
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';

if ($_ENV['APP_ENV'] !== 'prod') {
    return;
}

$field = Field::fromStream(STDIN);
l($field);
