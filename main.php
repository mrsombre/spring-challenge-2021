<?php

declare(strict_types=1);

namespace App;

use SplMaxHeap;

function l($message)
{
    $verbose = $_ENV['VERBOSE'] ?? false;
    if (!$verbose) {
        return;
    }

    if (!is_string($message)) {
        $message = var_export($message, true);
    }
    error_log($message);
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
        // base cells
        foreach ($cells as $cell) {
            $cellsIndexed[$cell->index] = $cell;
        }
        // neighs
        foreach ($cellsIndexed as $cell) {
            foreach ($cell->neighs as $index => $neighCell) {
                if ($neighCell === -1) {
                    continue;
                }
                $cell->neighCells[$index] = $cellsIndexed[$neighCell];
            }
        }

        $this->cells = $cellsIndexed;
        $this->numberOfCells = count($cells);
    }

    public function byIndex($index): Cell
    {
        return $this->cells[$index];
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

    public function oppositeDirection(int $direction): int
    {
        return ($direction + 3) % 6;
    }

    /**
     * @param \App\Cell $cell
     * @param int $direction
     * @return \App\Cell[]
     */
    public function vector(Cell $cell, int $direction): array
    {
        $result = [];
        $distance = 1;
        while (($cell = $cell->neight($direction)) !== null) {
            $result[$distance] = $cell;
            if (count($result) === 3) {
                break;
            }
            $distance++;
        }

        return $result;
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

    /** @var \App\Cell[] */
    public $neighCells;

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

    public function neight(int $direction): ?Cell
    {
        return $this->neighCells[$direction] ?? null;
    }
}

final class Game
{
    public const DAYS = 24;

    public const CHOP_COST = 4;

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

    public function getDaysRemaining(): int
    {
        return self::DAYS - $this->day - 1;
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
        if ($cost === 0) {
            return true;
        }
        if ($cost > $game->me->sun) {
            return false;
        }

        if ($game->getDaysRemaining() <= 6) {
            return false;
        }

        $growCost = $game->countGrowCost();
        if ($cost > $growCost[0]) {
            return false;
        }

        return true;
    }

    public function filterTrees(Game $game): array
    {
        $trees = $game->trees->getMine();

        return array_filter(
            $trees,
            function (Tree $tree) use ($game) {
                if ($tree->size === 0) {
                    return false;
                }
                if ($tree->size === 3 && $game->getDaysRemaining() <= 6) {
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

        $neighs = $this->field->neighsByCell($cell);
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
        l(__CLASS__);
        l($score->score);

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
        $cell = $this->field->byIndex($target->index);

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

        $available = array_filter(
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
        if ($available === []) {
            return [];
        }

        $choosen = $this->chooseSize($available);
        // prefer seed
        $treesBySize = $game->countTreesBySize();
        if ($treesBySize[0] < 2 && $game->getDaysRemaining() >= 6) {
            l('Seed preffered');
            return [];
        }

        return $choosen['trees'];
    }

    /**
     * @param \App\Tree[] $trees
     * @return array
     */
    public function chooseSize(array $trees): array
    {
        $bySize = [];
        foreach ($trees as $tree) {
            if (!isset($bySize[$tree->size])) {
                $bySize[$tree->size] = [
                    'size' => $tree->size,
                    'cnt' => 0,
                    'trees' => [],
                ];
            }
            $bySize[$tree->size]['cnt']++;
            $bySize[$tree->size]['trees'][] = $tree;
        }
        usort(
            $bySize,
            function ($a, $b) {
                // sort by count in group
                $sort = $b['cnt'] <=> $a['cnt'];
                if ($sort === 0) {
                    // then sort by size bigger first
                    $sort = $b['size'] <=> $a['size'];
                }
                return $sort;
            }
        );

        return array_shift($bySize);
    }

    public function countScore(Game $game, Tree $target): ?Score
    {
        $cell = $this->field->byIndex($target->index);

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
        l(__CLASS__);
        l($score->score);

        return Action::factory(Action::TYPE_GROW, $score->target);
    }
}

class Shadow
{
    /** @var \App\Field */
    public $field;
    /** @var \App\Game */
    public $game;

    public function __construct(Field $field, Game $game)
    {
        $this->field = $field;
        $this->game = $game;
    }

    public function countSunDirection(int $day): int
    {
        return $day % 6;
    }

    public function isShadow(int $index, int $day): bool
    {
        $cell = $this->field->byIndex($index);
        $target = $this->game->trees->byIndex($index);
        $spooky = $target->size ?? 0;

        $sun = $this->countSunDirection($day);
        $vector = $this->field->vector($cell, $this->field->oppositeDirection($sun));

        foreach ($vector as $distance => $cell) {
            $tree = $this->game->trees->byIndex($cell->index);
            if (!$tree || $tree->size < $distance) {
                continue;
            }
            if ($tree->size >= $spooky) {
                return true;
            }
        }

        return false;
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
        new GrowStrategy($field),
        new SeedStrategy($field),
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
