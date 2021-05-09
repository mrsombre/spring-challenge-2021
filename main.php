<?php

declare(strict_types=1);

namespace App;

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
            $cells[] = Cell::factory(...fscanf($stream, '%d %d %d %d %d %d %d %d'));
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

    public static function factory(int $index, int $richness, int ...$neighs): self
    {
        return new self($index, $richness, $neighs);
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
    /** @var \App\Player */
    public $me;
    /** @var \App\Player */
    public $opp;
    /** @var \App\Trees */
    public $trees;
    /** @var \App\Action[] */
    public $actions;

    public static function fromStream($stream): self
    {
        fscanf($stream, '%d', $day);
        fscanf($stream, '%d', $nutrients);
        $me = Player::factory(...fscanf($stream, '%d %d'));
        $opp = Player::factory(...fscanf($stream, '%d %d %d'));

        $trees = Trees::fromStream($stream);

        fscanf($stream, '%d', $num);
        $actions = [];
        for ($i = 0; $i < $num; $i++) {
            $actions[] = Action::factory(...fscanf($stream, '%s'));
        }

        return new self(
            $day,
            $nutrients,
            $me,
            $opp,
            $trees,
            $actions
        );
    }

    public function __construct(
        int $day,
        int $nutrients,
        Player $me,
        Player $opp,
        Trees $trees,
        array $actions
    ) {
        $this->day = $day;
        $this->nutrients = $nutrients;
        $this->me = $me;
        $this->opp = $opp;
        $this->trees = $trees;
        $this->actions = $actions;
    }

    public function countGrowCost(): array
    {
        $treesBySize = array_fill_keys(Tree::SIZE, 0);
        foreach ($this->trees->getMine() as $tree) {
            $treesBySize[$tree->size]++;
        }

        return [
            0 => 1 + $treesBySize[1],
            1 => 3 + $treesBySize[2],
            2 => 7 + $treesBySize[3],
        ];
    }
}

final class Player
{
    /** @var int */
    public $sun;
    /** @var int */
    public $score;
    /** @var bool */
    public $isWaiting;

    public static function factory(int $sun = 0, int $score = 0, int $isWaiting = 0): self
    {
        return new self($sun, $score, (bool) $isWaiting);
    }

    public function __construct(int $sun, int $score, bool $isWaiting)
    {
        $this->sun = $sun;
        $this->score = $score;
        $this->isWaiting = $isWaiting;
    }
}

final class Action
{
    public const TYPE_WAIT = 'WAIT';

    /** @var string */
    public $type;
    /** @var array */
    public $params = [];

    public static function factory(string $command = self::TYPE_WAIT): self
    {
        $parts = explode(' ', $command);
        return new self(array_shift($parts), $parts);
    }

    public function __construct(string $type, array $params = [])
    {
        $this->type = $type;
        $this->params = $params;
    }
}

final class Tree
{
    public const SIZE = [0, 1, 2, 3];

    /** @var int */
    public $index;
    /** @var int */
    public $size;
    /** @var bool */
    public $isMine;
    /** @var bool */
    public $isDormant;

    public static function factory(int $index = 0, int $size = 0, int $isMine = 1, int $isDormant = 0): self
    {
        return new self($index, $size, (bool) $isMine, (bool) $isDormant);
    }

    public function __construct(int $index, int $size, bool $isMine, bool $isDormant)
    {
        $this->index = $index;
        $this->size = $size;
        $this->isMine = $isMine;
        $this->isDormant = $isDormant;
    }
}

class Trees
{
    /** @var \App\Tree[] */
    public $trees;
    /** @var int */
    public $numberOfTrees;

    public static function fromStream($stream): self
    {
        fscanf($stream, '%d', $num);

        $trees = [];
        for ($i = 0; $i < $num; $i++) {
            $trees[] = Tree::factory(...fscanf($stream, '%d %d %d %d'));
        }

        return new self($trees);
    }

    /**
     * @param \App\Tree[] $trees
     */
    public function __construct(array $trees = [])
    {
        $treesIndexed = [];
        foreach ($trees as $tree) {
            $treesIndexed[$tree->index] = $tree;
        }
        $this->trees = $treesIndexed;
        $this->numberOfTrees = count($this->trees);
    }

    /**
     * @return \App\Tree[]
     */
    public function getMine(): array
    {
        $result = [];
        foreach ($this->trees as $tree) {
            if (!$tree->isMine) {
                continue;
            }
            $result[$tree->index] = $tree;
        }
        return $result;
    }

    public function byIndex(int $index): ?Tree
    {
        return $this->trees[$index] ?? null;
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
