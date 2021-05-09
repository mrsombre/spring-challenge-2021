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

    /**
     * @return \App\Cell[]
     */
    public function neighsByCell(Cell $cell): array
    {
        $neighs = [];
        foreach ($cell->neighs as $index) {
            if ($index === -1) {
                continue;
            }
            $neighs[$index] = $this->byIndex($index);
        }
        return $neighs;
    }

    /**
     * @return \App\Cell[]
     */
    public function neighsByIndex(int $index): array
    {
        return $this->neighsByCell($this->byIndex($index));
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
            $actions[] = Action::factory(...fscanf($stream, '%s %d %d'));
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

    public function countSeedCost(): int
    {
        $seeds = 0;
        foreach ($this->trees->getMine() as $tree) {
            if ($tree->size === 0) {
                $seeds++;
            }
        }

        return $seeds;
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
    public const TYPE_SEED = 'SEED';
    public const TYPE_GROW = 'GROW';
    public const TYPE_COMPLETE = 'COMPLETE';

    /** @var string */
    public $type;
    /** @var ?int[] */
    public $params = [];

    public static function factory(string $action = self::TYPE_WAIT, ?int ...$params): self
    {
        $params = array_filter(
            $params,
            function ($value) {
                return !is_null($value);
            }
        );
        return new self($action, $params);
    }

    public function __construct(string $type, array $params = [])
    {
        $this->type = $type;
        $this->params = $params;
    }

    public function __toString(): string
    {
        $action = $this->type;
        if ($this->params !== []) {
            $action .= ' ' . implode(' ', $this->params);
        }
        return $action . "\n";
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

abstract class AbstractStrategy
{
    /** @var \App\Field */
    public $field;
    /** @var array */
    public $config;

    public function __construct(Field $field, array $config = [])
    {
        $this->field = $field;
        $this->config = $config;
    }

    abstract public function action(Game $game): ?Action;
}

final class CompositeStrategy extends AbstractStrategy
{
    public function action(Game $game): ?Action
    {
        /** @var \App\AbstractStrategy $strategy */
        foreach ($this->config['strategies'] as $strategy) {
            $action = $strategy->action($game);
            if ($action) {
                return $action;
            }
        }
        return null;
    }
}

final class SeedStrategy extends AbstractStrategy
{
    public const MIN_SIZE = 1;

    public function action(Game $game): ?Action
    {
        $cost = $game->countSeedCost();
        if ($cost > $game->me->sun) {
            return null;
        }

        $trees = $game->trees->getMine();
        $trees = $this->filterTrees($trees);
        if ($trees === []) {
            return null;
        }

        // actually hack
        $pool = [];
        foreach ($game->actions as $actionId => $action) {
            if ($action->type !== Action::TYPE_SEED) {
                continue;
            }
            $pool[$actionId] = $this->countScore($action->params[1], $game->trees);
        }
        if ($pool === []) {
            return null;
        }

        arsort($pool);
        return $game->actions[key($pool)];
    }

    /**
     * @param \App\Tree[] $trees
     * @return \App\Tree[]
     */
    public function filterTrees(array $trees): array
    {
        return array_filter(
            $trees,
            function (Tree $tree) {
                if ($tree->size < self::MIN_SIZE) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                return true;
            }
        );
    }

    public function countScore(int $index, Trees $trees)
    {
        $score = 0;
        $score += $this->field->byIndex($index)->richness;

        $neighs = $this->field->neighsByIndex($index);
        foreach ($neighs as $cell) {
            if ($trees->byIndex($cell->index)) {
                continue;
            }
            if ($cell->richness === 3) {
                $score++;
            }
        }

        return $score;
    }
}

final class ChopStrategy extends AbstractStrategy
{
    public const MIN_LEVEL = 3;
    public const SUN_COST = 4;

    function action(Game $game): ?Action
    {
        if (self::SUN_COST > $game->me->sun) {
            return null;
        }

        $trees = $game->trees->getMine();
        $trees = $this->filterTrees($trees);
        if ($trees === []) {
            return null;
        }

        $pool = [];
        foreach ($trees as $tree) {
            $pool[$tree->index] = $this->countScore($tree);
        }
        if ($pool === []) {
            return null;
        }

        arsort($pool);
        return Action::factory(Action::TYPE_COMPLETE, key($pool));
    }

    /**
     * @param \App\Tree[] $trees
     * @return \App\Tree[]
     */
    public function filterTrees(array $trees): array
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

    public function countScore(Tree $tree)
    {
        $score = 0;
        $score += $this->field->byTree($tree)->richness;

        return $score;
    }
}

final class GrowStrategy extends AbstractStrategy
{
    public const MAX_LEVEL = 3;

    function action(Game $game): ?Action
    {
        $cost = $game->countGrowCost();
        if (min($cost) > $game->me->sun) {
            return null;
        }

        $trees = $game->trees->getMine();
        $trees = $this->filterTrees($trees);
        if ($trees === []) {
            return null;
        }

        $pool = [];
        foreach ($trees as $tree) {
            $pool[$tree->index] = $this->countScore($tree);
        }
        if ($pool === []) {
            return null;
        }

        arsort($pool);
        return Action::factory(Action::TYPE_GROW, key($pool));
    }

    /**
     * @param \App\Tree[] $trees
     * @return \App\Tree[]
     */
    public function filterTrees(array $trees): array
    {
        return array_filter(
            $trees,
            function (Tree $tree) {
                if ($tree->size >= self::MAX_LEVEL) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                return true;
            }
        );
    }

    public function countScore(Tree $tree)
    {
        $score = 0;
        $score += $this->field->byTree($tree)->richness;

        $score += $tree->size;

        return $score;
    }
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';

if ($_ENV['APP_ENV'] !== 'prod') {
    return;
}

$field = Field::fromStream(STDIN);

$strategy = new CompositeStrategy(
    $field,
    [
        'strategies' => [
            new SeedStrategy($field),
        ],
    ]
);

while (true) {
    $game = Game::fromStream(STDIN);
    $action = $strategy->action($game);
    if (!$action) {
        $action = Action::factory();
    }
    echo $action;
}
