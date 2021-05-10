<?php

declare(strict_types=1);

namespace App;

use SplMaxHeap;

function l($message)
{
    if (isset($_ENV['VERBOSE']) && $_ENV['VERBOSE']) {
        return;
    }

    if (is_string($message)) {
        error_log($message);
        return;
    }
    error_log(var_export($message, true));
}

function wait(int $days, callable $action = null)
{
    while (true) {
        $game = Game::fromStream(STDIN);
        if ($game->day > 3) {
            exit;
        }
        if ($action) {
            call_user_func_array($action, [$game]);
        }

        array_shift($game->actions);
        $action = array_shift($game->actions);
        if ($action === null) {
            echo "WAIT\n";
        } else {
            echo $action;
        }
    }
}

trait Cache
{
    private $cache = [];

    public function cacheGet(string $index, $default = null)
    {
        return array_key_exists($index, $this->cache) ? $this->cache[$index] : $default;
    }

    /**
     * @param string $index
     * @param $data
     * @return $this
     */
    public function cacheSet(string $index, $data)
    {
        $this->cache[$index] = $data;
        return $this;
    }

    /**
     * @return $this
     */
    public function cacheClear()
    {
        $this->cache = [];
        return $this;
    }
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
    public const DAYS = 23;

    use Cache;

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
        if (($cache = $this->cacheGet(__METHOD__)) !== null) {
            return $cache;
        }

        $treesBySize = $this->countTreesBySize();
        $result = [
            0 => 1 + $treesBySize[1],
            1 => 3 + $treesBySize[2],
            2 => 7 + $treesBySize[3],
        ];
        $this->cacheSet(__METHOD__, $result);

        return $result;
    }

    public function countSeedCost(): int
    {
        if (($cache = $this->cacheGet(__METHOD__)) !== null) {
            return $cache;
        }

        $seeds = 0;
        foreach ($this->trees->getMine() as $tree) {
            if ($tree->size === 0) {
                $seeds++;
            }
        }
        $this->cacheSet(__METHOD__, $seeds);

        return $seeds;
    }

    public function countTreesBySize(): array
    {
        if (($cache = $this->cacheGet(__METHOD__)) !== null) {
            return $cache;
        }

        $result = array_fill_keys(Tree::SIZE, 0);
        foreach ($this->trees->getMine() as $tree) {
            $result[$tree->size]++;
        }
        $this->cacheSet(__METHOD__, $result);

        return $result;
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
    use Cache;

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
        if (($cache = $this->cacheGet(__METHOD__)) !== null) {
            return $cache;
        }

        $result = [];
        foreach ($this->trees as $tree) {
            if (!$tree->isMine) {
                continue;
            }
            $result[$tree->index] = $tree;
        }
        $this->cacheSet(__METHOD__, $result);

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

class Score
{
    /** @var int */
    public $score;
    /** @var int */
    public $target;
    /** @var array */
    public $params;

    public function __construct(int $score, int $target, ...$params)
    {
        $this->score = $score;
        $this->target = $target;
        $this->params = $params;
    }
}

class ScoreMaxHeap extends SplMaxHeap
{
    protected function compare($value1, $value2)
    {
        return $value1->score <=> $value2->score;
    }
}

abstract class AbstractScoreStrategy extends AbstractStrategy
{
    function action(Game $game): ?Action
    {
        if (!$this->isActive($game)) {
            return null;
        }

        $trees = $this->filterTrees($game);
        if ($trees === []) {
            return null;
        }

        $heap = new ScoreMaxHeap();
        foreach ($trees as $tree) {
            $score = $this->countScore($game, $tree);
            if (!$score) {
                continue;
            }
            $heap->insert($score);
        }
        if ($heap->isEmpty()) {
            return null;
        }

        return $this->factory($heap->top());
    }

    public function isActive(Game $game): bool
    {
        return true;
    }

    /**
     * @param \App\Game $game
     * @return \App\Tree[]
     */
    abstract public function filterTrees(Game $game): array;

    abstract public function countScore(Game $game, Tree $target): ?Score;

    abstract public function factory(Score $score): ?Action;
}

final class CompositeStrategy extends AbstractStrategy
{
    /** @var \App\AbstractStrategy[] */
    public $strategies = [];

    public function __construct(Field $field, array $strategies)
    {
        parent::__construct($field);
        $this->strategies = $strategies;
    }

    public function action(Game $game): ?Action
    {
        foreach ($this->strategies as $strategy) {
            $action = $strategy->action($game);
            if ($action) {
                return $action;
            }
        }
        return null;
    }
}

final class SeedStrategy extends AbstractScoreStrategy
{
    public function isActive(Game $game): bool
    {
        $cost = $game->countSeedCost();
        if ($cost > $game->me->sun) {
            return false;
        }

        $treesBySize = $game->countTreesBySize();
        if ($treesBySize[0] >= 2) {
            return false;
        }
        if ($treesBySize[1] >= 5) {
            return false;
        }
        if ($treesBySize[2] >= 3) {
            return false;
        }

        $hold = 4 + $cost;
        if ($game->day > 12 && $game->me->sun < $hold) {
            return false;
        }

        return true;
    }

    public function filterTrees(Game $game): array
    {
        $trees = $game->trees->getMine();

        return array_filter(
            $trees,
            function (Tree $tree) {
                if ($tree->size === 0) {
                    return false;
                }
                if ($tree->size === 3) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                return true;
            }
        );
    }

    public function countScore(Game $game, Tree $target): ?Score
    {
        $cells = $this->findCells($game, $target);
        if ($cells === []) {
            return null;
        }

        $heap = new ScoreMaxHeap();
        foreach ($cells as $index) {
            $score = $this->countCellScore($game, $index);
            if (!$score) {
                continue;
            }
            $heap->insert($score);
        }
        if ($heap->isEmpty()) {
            return null;
        }

        return new Score($heap->top()->score, $target->index, $heap->top()->target);
    }

    public function countCellScore(Game $game, int $index): ?Score
    {
        $cell = $this->field->byIndex($index);

        $score = 0;
        $score += $cell->richness;

        $neighs = $this->field->neighsByIndex($cell->index);
        foreach ($neighs as $neigh) {
            // neigh trees
            $tree = $game->trees->byIndex($neigh->index);
            if ($tree && $cell->richness < 3) {
                $score -= $tree->size;
            }
        }

        return new Score($score, $index);
    }

    public function factory(Score $score): ?Action
    {
        return Action::factory(Action::TYPE_SEED, $score->target, $score->params[0]);
    }

    public function findCells(Game $game, Tree $target): array
    {
        $result = [];
        foreach ($game->actions as $action) {
            if ($action->type !== Action::TYPE_SEED) {
                continue;
            }
            if ($action->params[0] === $target->index) {
                $result[] = $action->params[1];
            }
        }
        return $result;
    }
}

final class ChopStrategy extends AbstractScoreStrategy
{
    public const MIN_LEVEL = 3;
    public const SUN_COST = 4;

    public function isActive(Game $game): bool
    {
        if (self::SUN_COST > $game->me->sun) {
            return false;
        }
        if ($game->day >= 22) {
            return true;
        }

        $treesBySize = $game->countTreesBySize();
        if ($treesBySize[3] <= 5) {
            return false;
        }

        return true;
    }

    public function filterTrees(Game $game): array
    {
        $trees = $game->trees->getMine();

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

    public function countScore(Game $game, Tree $target): ?Score
    {
        $cell = $this->field->byTree($target);

        $score = 0;
        $score += $cell->richness;

        if ($cell->richness === 3) {
            $score++;
        }

        return new Score($score, $cell->index);
    }

    public function factory(Score $score): ?Action
    {
        return Action::factory(Action::TYPE_COMPLETE, $score->target);
    }
}

class GrowStrategy extends AbstractScoreStrategy
{
    public const MAX_LEVEL = 3;

    public function isActive(Game $game): bool
    {
        $growCost = $game->countGrowCost();
        if (min($growCost) > $game->me->sun) {
            return false;
        }
        return true;
    }

    public function filterTrees(Game $game): array
    {
        $trees = $game->trees->getMine();
        $cost = $game->countGrowCost();

        return array_filter(
            $trees,
            function (Tree $tree) use ($game, $cost) {
                if ($tree->size >= self::MAX_LEVEL) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                if ($cost[$tree->size] > $game->me->sun) {
                    return false;
                }
                return true;
            }
        );
    }

    public function countScore(Game $game, Tree $target): ?Score
    {
        $cell = $this->field->byTree($target);

        $score = 0;
        $score += $cell->richness;
        $score += $target->size;

        if ($cell->richness === 3) {
            $score++;
        }

        return new Score($score, $cell->index);
    }

    public function factory(Score $score): ?Action
    {
        return Action::factory(Action::TYPE_GROW, $score->target);
    }
}

final class GrowSeedStrategy extends GrowStrategy
{
    public function filterTrees(Game $game): array
    {
        return array_filter(
            parent::filterTrees($game),
            function (Tree $tree) {
                if ($tree->size !== 0) {
                    return false;
                }
                return true;
            }
        );
    }
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';

if ($_ENV['APP_ENV'] !== 'prod') {
    return;
}

$_ENV['VERBOSE'] = true;

$field = Field::fromStream(STDIN);

$strategy = new CompositeStrategy(
    $field,
    [
        new ChopStrategy($field),
        new GrowSeedStrategy($field),
        new SeedStrategy($field),
        new GrowStrategy($field),
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
