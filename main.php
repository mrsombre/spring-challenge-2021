<?php

declare(strict_types=1);

namespace App;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
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

trait Cache
{
    private $cache = [];

    public function cacheGet(string $index, $default = null)
    {
        return array_key_exists($index, $this->cache) ? $this->cache[$index] : $default;
    }

    public function cacheSet(string $index, $data)
    {
        $this->cache[$index] = $data;
        return $this;
    }

    public function cacheClear()
    {
        $this->cache = [];
        return $this;
    }
}

final class Field
{
    public const DIRECTION_RIGHT = 0;
    public const DIRECTION_TOP_RIGHT = 1;
    public const DIRECTION_TOP_LEFT = 2;
    public const DIRECTION_LEFT = 3;
    public const DIRECTION_BOTTOM_LEFT = 4;
    public const DIRECTION_BOTTOM_RIGHT = 5;

    public const DIRECTIONS = [
        self::DIRECTION_RIGHT,
        self::DIRECTION_TOP_RIGHT,
        self::DIRECTION_TOP_LEFT,
        self::DIRECTION_LEFT,
        self::DIRECTION_BOTTOM_LEFT,
        self::DIRECTION_BOTTOM_RIGHT,
    ];

    /** @var int[] */
    public $cells;
    /** @var int[][][] */
    public $neighs;
    /** @var int[] */
    public $vectors;

    public static function fromStream($stream): self
    {
        fscanf($stream, '%d', $num);
        $cellsData = [];
        for ($i = 0; $i < $num; $i++) {
            $cellsData[] = fscanf($stream, '%d %d %d %d %d %d %d %d');
        }

        return new self($cellsData);
    }

    public function __construct(array $cellsData)
    {
        $cellsIndexed = [];
        // base cells
        foreach ($cellsData as $datum) {
            $cellsIndexed[$datum[0]] = $datum[1];
            $neighs = array_slice($datum, 2);
            $neighs = array_filter(
                $neighs,
                function ($value) {
                    return $value !== -1;
                }
            );
            $this->neighs[$datum[0]][1] = $neighs;
        }
        $this->cells = $cellsIndexed;

        foreach (array_keys($this->cells) as $index) {
            // count vectors
            foreach (self::DIRECTIONS as $direction) {
                $this->vectors[$index][$direction] = $this->countVector($index, $direction);
            }
            // count neighs distance 2
            $this->neighs[$index][2] = [];
            foreach ($this->neighs[$index][1] as $neigh) {
                foreach ($this->neighs($neigh) as $check) {
                    if ($check === $index) {
                        continue;
                    }
                    if (in_array($check, $this->neighs[$index][1])) {
                        continue;
                    }
                    $this->neighs[$index][2][$check] = $check;
                }
            }
            sort($this->neighs[$index][2]);

            // count neighs distance 3
            $this->neighs[$index][3] = [];
            foreach ($this->neighs[$index][2] as $neigh) {
                foreach ($this->neighs($neigh) as $check) {
                    if ($check === $index) {
                        continue;
                    }
                    if (in_array($check, $this->neighs[$index][1])) {
                        continue;
                    }
                    if (in_array($check, $this->neighs[$index][2])) {
                        continue;
                    }
                    $this->neighs[$index][3][$check] = $check;
                }
            }
            sort($this->neighs[$index][3]);
        }
    }

    public function byIndex($index): int
    {
        return $this->cells[$index];
    }

    /**
     * @param int $index
     * @param int|null $distance
     * @return int[]
     */
    public function neighs(int $index, int $distance = 1): array
    {
        return $this->neighs[$index][$distance];
    }

    public function neigh(int $index, int $direction): ?int
    {
        return $this->neighs[$index][1][$direction] ?? null;
    }

    public function oppositeDirection(int $direction): int
    {
        return ($direction + 3) % 6;
    }

    /**
     * @param int $index
     * @param int $direction
     * @return int[]
     */
    public function vector(int $index, int $direction): array
    {
        return $this->vectors[$index][$direction];
    }

    private function countVector(int $index, int $direction): array
    {
        $result = [];
        $distance = 1;
        while (($index = $this->neigh($index, $direction)) !== null) {
            $result[$distance] = $index;
            if (count($result) === 3) {
                break;
            }
            $distance++;
        }

        return $result;
    }
}

final class Game
{
    public const DAYS = 24;

    public const SEED_COST = 0;
    public const GROW_COST = [0 => 1, 1 => 3, 2 => 7];
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
    /** @var \App\Tree[] */
    public $trees;
    /** @var \App\Action[] */
    public $actions;

    /** @var \App\Field */
    public $field;
    /** @var \App\Tree[] */
    public $mine;
    /** @var \App\Shadow */
    public $shadow;

    public static function fromStream($stream, Field $field): self
    {
        fscanf($stream, '%d', $day);
        fscanf($stream, '%d', $nutrients);
        $me = Player::factory(...fscanf($stream, '%d %d'));
        $opp = Player::factory(...fscanf($stream, '%d %d %d'));

        fscanf($stream, '%d', $num);
        $trees = [];
        for ($i = 0; $i < $num; $i++) {
            $trees[] = Tree::factory(...fscanf($stream, '%d %d %d %d'));
        }

        fscanf($stream, '%d', $num);
        $actions = [];
        for ($i = 0; $i < $num; $i++) {
            $actions[] = Action::factory(...fscanf($stream, '%s %d %d'));
        }

        return new self(
            $field,
            $day,
            $nutrients,
            $me,
            $opp,
            $trees,
            $actions
        );
    }

    public function __construct(
        Field $field,
        int $day,
        int $nutrients,
        Player $me,
        Player $opp,
        array $trees,
        array $actions
    ) {
        $this->field = $field;

        $this->day = $day;
        $this->nutrients = $nutrients;
        $this->me = $me;
        $this->opp = $opp;

        $this->trees = [];
        /** @var \App\Tree $tree */
        foreach ($trees as $tree) {
            $this->trees[$tree->index] = $tree;
            if ($tree->isMine) {
                $this->mine[$tree->index] = $tree;
            }
        }

        $this->actions = $actions;

        $this->shadow = new Shadow($field, $this);
    }

    public function tree(int $index): ?Tree
    {
        return $this->trees[$index] ?? null;
    }

    public function countTreesBySize(?bool $isMine = true): array
    {
        $result = array_fill_keys(Tree::SIZE, 0);
        foreach ($this->trees as $tree) {
            if ($isMine !== null && $isMine !== $tree->isMine) {
                continue;
            }
            $result[$tree->size]++;
        }

        return $result;
    }

    public function countGrowCost(bool $isMine = true): array
    {
        $bySize = $this->countTreesBySize($isMine);
        return [
            self::GROW_COST[0] + $bySize[1],
            self::GROW_COST[1] + $bySize[2],
            self::GROW_COST[2] + $bySize[3],
        ];
    }

    public function countSeedCost(bool $isMine = true): int
    {
        return self::SEED_COST + $this->countTreesBySize($isMine)[0];
    }

    public function countDaysRemaining(): int
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

final class Action
{
    public const TYPE_WAIT = 'WAIT';
    public const TYPE_SEED = 'SEED';
    public const TYPE_GROW = 'GROW';
    public const TYPE_COMPLETE = 'COMPLETE';

    /** @var string */
    public $type;
    /** @var int[] */
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

abstract class AbstractStrategy
{
    /** @var array */
    public $config;

    public function __construct(...$config)
    {
        $this->config = $config;
    }

    abstract public function action(Game $game): ?Action;
}

final class CompositeStrategy extends AbstractStrategy
{
    /** @var \App\AbstractStrategy[] */
    public $strategies = [];

    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
        parent::__construct();
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
    public function action(Game $game): ?Action
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

final class SeedStrategy extends AbstractScoreStrategy
{
    public function isActive(Game $game): bool
    {
        $cost = $game->countSeedCost();
        if ($cost > 0) {
            return false;
        }

        return true;
    }

    public function filterTrees(Game $game): array
    {
        return array_filter(
            $game->mine,
            function (Tree $tree) use ($game) {
                if ($tree->size !== 3) {
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
        $score = 0;
        $score += $game->field->byIndex($index);

        foreach (Field::DIRECTIONS as $direction) {
            $vector = $game->field->vector($index, $direction);
            foreach ($vector as $cell) {
                $tree = $game->tree($cell);
                if ($tree !== null) {
                    $score--;
                    break;
                }
            }
        }

        foreach (range($game->day + 2, min($game->countDaysRemaining(), 5)) as $day) {
            if ($game->shadow->isShadow($index, $day)) {
                $score--;
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
        $available = [];
        for ($i = 1; $i <= $target->size; $i++) {
            foreach ($game->field->neighs($target->index, $i) as $neigh) {
                if ($game->field->byIndex($neigh) === 0) {
                    continue;
                }
                if ($game->tree($neigh) !== null) {
                    continue;
                }
                $available[] = $neigh;
            }
        }
        return $available;
    }
}

final class ChopStrategy extends AbstractScoreStrategy
{
    public const CHOP_SIZE = 3;
    public const SUN_COST = 4;

    public function isActive(Game $game): bool
    {
        if (self::SUN_COST > $game->me->sun) {
            return false;
        }

        if ($game->countDaysRemaining() < 2) {
            return true;
        }

        $bySize = $game->countTreesBySize();
        if ($bySize[self::CHOP_SIZE] < 5) {
            return false;
        }

        return true;
    }

    public function filterTrees(Game $game): array
    {
        return array_filter(
            $game->mine,
            function (Tree $tree) {
                if ($tree->size < self::CHOP_SIZE) {
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
        $score = 0;
        $score += $game->field->byIndex($target->index);

        if ($game->shadow->isShadow($target->index, $game->day + 1)) {
            $score += 3;
        }

        return new Score($score, $target->index);
    }

    public function factory(Score $score): ?Action
    {
        return Action::factory(Action::TYPE_COMPLETE, $score->target);
    }
}

class GrowStrategy extends AbstractScoreStrategy
{
    public const MAX_LEVEL = 3;

    public function filterTrees(Game $game): array
    {
        $trees = [];
        $size = 0;
        foreach ($game->mine as $tree) {
            if ($tree->size >= self::MAX_LEVEL) {
                continue;
            }
            if ($tree->isDormant) {
                continue;
            }
            $trees[$tree->size][] = $tree;
            $size = max($size, $tree->size);
        }

        if ($trees === [] || $game->countGrowCost()[$size] > $game->me->sun) {
            return [];
        }

        return $trees[$size];
    }

    public function countScore(Game $game, Tree $target): ?Score
    {
        $score = 0;
        $score += $game->field->byIndex($target->index);

        return new Score($score, $target->index);
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

    public function sunDirection(int $day): int
    {
        return $day % 6;
    }

    public function shadowVector(int $index, int $day): array
    {
        $size = $this->game->tree($index)->size ?? 0;
        if ($size === 0) {
            return [];
        }

        $direction = $this->sunDirection($day);
        $vector = $this->field->vector($index, $direction);

        return array_slice($vector, 0, $size);
    }

    public function isShadow(int $index, int $day): bool
    {
        $target = $this->game->tree($index);
        $spooky = $target->size ?? 0;

        $sun = $this->sunDirection($day);
        $against = $this->field->oppositeDirection($sun);
        $vector = $this->field->vector($index, $against);

        foreach ($vector as $distance => $cell) {
            $tree = $this->game->tree($cell);
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

$_ENV['VERBOSE'] = false;

$field = Field::fromStream(STDIN);

$strategy = new CompositeStrategy(
    [
        new ChopStrategy($field),
        new GrowStrategy($field),
        new SeedStrategy($field),
    ]
);

while (true) {
    $game = Game::fromStream(STDIN, $field);

    $action = $strategy->action($game);
    if (!$action) {
        $action = Action::factory();
    }
    echo $action;
}
