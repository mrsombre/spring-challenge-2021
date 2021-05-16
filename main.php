<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use SplFixedArray;

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

    public const CELL_SCORE = [
        1 => 0,
        2 => 2,
        3 => 4,
    ];

    /** @var SplFixedArray */
    public $cells;
    /** @var int[][][] */
    public $neighs;
    /** @var SplFixedArray */
    public $vectors;

    public static function fromStream($stream): array
    {
        fscanf($stream, '%d', $num);
        $data = [];
        for ($i = 0; $i < $num; $i++) {
            $data[] = fscanf($stream, '%d %d %d %d %d %d %d %d');
        }

        return $data;
    }

    public static function oppositeDirection(int $direction): int
    {
        return ($direction + 3) % 6;
    }

    public function __construct(array $cells)
    {
        // base cells
        foreach ($cells as $datum) {
            $this->cells[$datum[0]] = $datum[1];
            $neighs = array_slice($datum, 2);
            $neighs = array_filter(
                $neighs,
                function ($value) {
                    return $value !== -1;
                }
            );
            $this->neighs[$datum[0]][0] = $neighs;
        }

        foreach (range(0, 36) as $index) {
            // count vectors
            foreach (self::DIRECTIONS as $direction) {
                $this->vectors[$index][$direction] = $this->countVector($index, $direction);
            }
            // count neighs distance 2
            $this->neighs[$index][1] = [];
            foreach ($this->neighs[$index][0] as $neigh) {
                foreach ($this->neighs($neigh) as $check) {
                    if ($check === $index) {
                        continue;
                    }
                    if (in_array($check, $this->neighs[$index][0])) {
                        continue;
                    }
                    $this->neighs[$index][1][$check] = $check;
                }
            }
            sort($this->neighs[$index][1]);

            // count neighs distance 3
            $this->neighs[$index][2] = [];
            foreach ($this->neighs[$index][1] as $neigh) {
                foreach ($this->neighs($neigh) as $check) {
                    if ($check === $index) {
                        continue;
                    }
                    if (in_array($check, $this->neighs[$index][0])) {
                        continue;
                    }
                    if (in_array($check, $this->neighs[$index][1])) {
                        continue;
                    }
                    $this->neighs[$index][2][$check] = $check;
                }
            }
            sort($this->neighs[$index][2]);
        }
    }

    public function byIndex(int $index): int
    {
        return $this->cells[$index];
    }

    public function score(int $index): int
    {
        return self::CELL_SCORE[$this->byIndex($index)];
    }

    /**
     * @param int $index
     * @param int|null $distance
     * @return array
     */
    public function neighs(int $index, int $distance = 1): array
    {
        return $this->neighs[$index][--$distance];
    }

    public function neigh(int $index, int $direction): ?int
    {
        return $this->neighs[$index][0][$direction] ?? null;
    }

    /**
     * @param int $index
     * @param int $direction
     * @return array
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

    public function export(): string
    {
        $result = ['37'];
        foreach ($this->cells as $index => $rich) {
            $neighs = [];
            foreach (self::DIRECTIONS as $direction) {
                if (!isset($this->neighs[$index][1][$direction])) {
                    $neighs[] = -1;
                    continue;
                }
                $neighs[] = $this->neighs[$index][1][$direction];
            }
            $neighs = implode(' ', $neighs);
            $result[] = "$index $rich $neighs";
        }

        return implode("\n", $result) . "\n";
    }
}

final class Game
{
    public const DAYS = 24;

    public const SEED_COST = 0;
    public const GROW_COST = [0 => 1, 1 => 3, 2 => 7];
    public const CHOP_COST = 4;

    use Cache;

    /** @var \App\Field */
    public $field;

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

    public $actions = [];

    public static function fromStream($stream): array
    {
        $data = [];
        fscanf($stream, '%d', $day);
        $data[] = $day;
        fscanf($stream, '%d', $nutrients);
        $data[] = $nutrients;
        $data[] = Player::factory(true, ...fscanf($stream, '%d %d'));
        $data[] = Player::factory(false, ...fscanf($stream, '%d %d %d'));

        $trees = [];
        fscanf($stream, '%d', $numTrees);
        if ($numTrees > 0) {
            $trees = [];
            for ($i = 0; $i < $numTrees; $i++) {
                $trees[] = Tree::factory(...fscanf($stream, '%d %d %d %d'));
            }
        }
        $data[] = $trees;

        $actions = [];
        fscanf($stream, '%d', $numActions);
        if ($numActions > 0) {
            $actions = [];
            for ($i = 0; $i < $numActions; $i++) {
                $actions[] = stream_get_line($stream, 32, "\n");
            }
        }
        $data[] = [];

        return $data;
    }

    public function __construct(
        Field $field,
        int $day,
        int $nutrients,
        Player $me,
        Player $opp,
        array $trees
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
        }
    }

    public function actions(bool $isMine): array
    {
        $actions = [Action::factory()];

        $player = $isMine ? $this->me : $this->opp;
        $growCost = $this->countGrowCost($isMine);
        $seedCost = $this->countSeedCost($isMine);
        $bySize = $this->countTreesBySize($isMine);
        foreach ($this->trees as $tree) {
            if ($tree->isMine !== $isMine) {
                continue;
            }
            // dormant
            if ($tree->isDormant) {
                continue;
            }
            // can grow
            if ($tree->size < 3 && $player->sun >= $growCost[$tree->size]) {
                $actions[] = Action::factory(Action::TYPE_GROW, $tree->index);
            }
            // can seed
            if ($tree->size > 0 && $seedCost < 1 && $player->sun >= $seedCost) {
                foreach (range(1, $tree->size) as $size) {
                    foreach ($this->field->neighs($tree->index, $size) as $neigh) {
                        $actions[] = Action::factory(Action::TYPE_SEED, $tree->index, $neigh);
                    }
                }
            }
            // can chop
            if ($tree->size === 3 && $player->sun >= 4 && ($bySize[3] > 3 || $this->day > 18)) {
                $actions[] = Action::factory(Action::TYPE_COMPLETE, $tree->index);
            }
        }

        return $actions;
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

    public function countSunIncome(bool $isMine): int
    {
        $sun = 0;
        $bySize = $this->countTreesBySize($isMine);
        foreach ($bySize as $size => $count) {
            $sun += $size * $count;
        }
        return $sun;
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

    public function countCellScore(int $rich): int
    {
        return $rich + $this->nutrients;
    }

    public function export(): string
    {
        $result = [$this->day];
        $result[] = $this->nutrients;
        $result[] = "{$this->me->sun} {$this->me->score}";
        $isWaiting = (int) $this->opp->isWaiting;
        $result[] = "{$this->opp->sun} {$this->opp->score} $isWaiting";

        $result[] = count($this->trees);
        foreach ($this->trees as $tree) {
            $isMine = (int) $tree->isMine;
            $isDormant = (int) $tree->isDormant;
            $result[] = "$tree->index $tree->size $isMine $isDormant";
        }

        if ($this->actions !== []) {
            $result[] = count($this->actions);
            foreach ($this->actions as $action) {
                $result[] = $action;
            }
        }

        return implode("\n", $result) . "\n";
    }
}

final class Player
{
    /** @var bool */
    public $isMine;
    /** @var int */
    public $sun;
    /** @var int */
    public $score;
    /** @var bool */
    public $isWaiting;

    public static function factory(bool $isMine = true, int $sun = 0, int $score = 0, int $isWaiting = 0): self
    {
        return new self($isMine, $sun, $score, (bool) $isWaiting);
    }

    public function __construct(bool $isMine, int $sun, int $score, bool $isWaiting)
    {
        $this->isMine = $isMine;
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

    public static function factory(string $action = self::TYPE_WAIT, ...$params): self
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
    /** @var \App\Field */
    public $field;
    /** @var array */
    public $config;

    public function __construct(Field $field, ...$config)
    {
        $this->field = $field;
        $this->config = $config;
    }

    abstract public function action(Game $game): ?Action;
}

final class CompositeStrategy extends AbstractStrategy
{
    /** @var \App\AbstractStrategy[] */
    public $strategies = [];

    public function __construct(...$strategies)
    {
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

final class SeedStrategy extends AbstractStrategy
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

        $cells = [];
        $cellsToTree = [];
        foreach ($trees as $tree) {
            $find = $this->findCells($game, $tree);
            foreach ($find as $cell) {
                $cells[$cell] = $cell;
                $cellsToTree[$cell][] = $tree;
            }
        }

        $shadow = new Shadow($this->field);
        $daysRemaining = $game->countDaysRemaining();
        $cellsScore = [];
        foreach ($cells as $cell) {
            $sunBalance = 0;

            if ($daysRemaining > 6) {
                $start = $game->day + 5;
                foreach (range($start, $start + min(5, $daysRemaining)) as $day) {
                    if ($shadow->isShadow($game->trees, $cell, 1, $day)) {
                        $sunBalance--;
                    }

                    $shadowOut = $shadow->shadowOut($cell, $day);
                    foreach ($shadowOut as $outCell) {
                        $neigh = $game->tree($outCell);
                        if ($neigh === null) {
                            continue;
                        }

                        if ($neigh->isMine) {
                            $sunBalance -= 1;
                        } else {
                            $sunBalance += 0.5;
                        }
                    }
                }
            }

            $score = $game->countCellScore($this->field->byIndex($cell));

            $cellsScore[] = [
                'index' => $cell,
                'shadow' => $sunBalance,
                'score' => $score,
            ];
        }
        if ($cellsScore === []) {
            return null;
        }

        usort(
            $cellsScore,
            function (array $a, array $b) use ($game) {
                $sort = $b['shadow'] <=> $a['shadow'];
                if ($sort === 0) {
                    $sort = $b['score'] <=> $a['score'];
                }
                return $sort;
            }
        );
        $bestCell = array_shift($cellsScore)['index'];

        $bestTrees = $cellsToTree[$bestCell];
        usort(
            $bestTrees,
            function (Tree $a, Tree $b) use ($game) {
                $sort = $b->size <=> $a->size;
                return $sort;
            }
        );
        $bestTree = array_shift($bestTrees);

        return $this->factory($bestTree->index, $bestCell);
    }

    public function isActive(Game $game): bool
    {
        $cost = $game->countSeedCost();

        if ($cost > 0) {
            return false;
        }

        return true;
    }

    /**
     * @param \App\Game $game
     * @return \App\Tree[]
     */
    public function filterTrees(Game $game): array
    {
        return array_filter(
            $game->mine,
            function (Tree $tree) use ($game) {
                if ($tree->isDormant) {
                    return false;
                }
                if ($tree->size < 2) {
                    return false;
                }
                return true;
            }
        );
    }

    /**
     * @param \App\Game $game
     * @param \App\Tree $target
     * @return int[]
     */
    public function findCells(Game $game, Tree $target): array
    {
        $available = [];
        for ($i = 1; $i <= $target->size; $i++) {
            foreach ($this->field->neighs($target->index, $i) as $neigh) {
                if ($this->field->byIndex($neigh) === 0) {
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

    public function factory(int $tree, int $target): Action
    {
        return Action::factory(Action::TYPE_SEED, $tree, $target);
    }
}

class GrowStrategy extends AbstractStrategy
{
    public const MAX_LEVEL = 3;

    public function action(Game $game): ?Action
    {
        if (!$this->isActive($game)) {
            return null;
        }

        $trees = $this->filterTrees($game);
        if ($trees === []) {
            return null;
        }

        $bySize = array_fill_keys(Tree::SIZE, 0);
        foreach ($trees as $tree) {
            $bySize[$tree->size]++;
        }

        $shadow = new Shadow($this->field);
        $growCost = $game->countGrowCost();
        $bestSize = null;

        // choose size 3
        if ((function () use ($game, $growCost, $bySize) {
            if ($game->countDaysRemaining() > 6) {
                return false;
            }
            if ($bySize[2] < 1) {
                return false;
            }
            return true;
        })()) {
            $bestSize = 2;
        }

        // choose size 2
        if ((function () use ($game, $growCost, $bySize) {
            if ($bySize[1] < 1) {
                return false;
            }
            $cost2 = $growCost[1] + $growCost[0] - 1;
            if ($cost2 > $game->me->sun) {
                return false;
            }
            $cost3 = $growCost[2];
            if ($cost2 >= $cost3) {
                return false;
            }
            return true;
        })()) {
            $bestSize = 1;
        }

        if ($bestSize === null) {
            foreach (range(2, 0, -1) as $size) {
                if ($bySize[$size] > 0 && $growCost[$size] <= $game->me->sun) {
                    $bestSize = $size;
                    break;
                }
            }
        }

        $sizeUp = $bestSize + 1;
        $oppGrowCost = $game->countGrowCost(false);
        $treesScore = [];
        foreach ($trees as $tree) {
            if ($tree->size !== $bestSize) {
                continue;
            }

            $sunBalance = $sizeUp;

            if ($game->countDaysRemaining() > 6) {
                $shadowIn = $shadow->shadowIn($tree->index, $game->day + 1);
                foreach ($shadowIn as $distance => $cell) {
                    $neigh = $game->tree($cell);
                    if ($neigh === null) {
                        continue;
                    }
                    if ($neigh->isMine) {
                        if ($distance <= $neigh->size && $neigh->size >= $sizeUp) {
                            $sunBalance = 0;
                            break;
                        }
                    } else {
                        $oppSize = $game->oppPessimisticSize($neigh, $oppGrowCost);
                        if ($distance <= $oppSize && $oppSize <= $sizeUp) {
                            $sunBalance = 0;
                            break;
                        }
                    }
                }

                $shadowOut = $shadow->shadowOut($tree->index, $game->day + 1);
                $shadowOut = array_slice($shadowOut, 0, $sizeUp);
                foreach ($shadowOut as $index) {
                    $neigh = $game->tree($index);
                    if ($neigh === null) {
                        continue;
                    }
                    if ($neigh->isMine) {
                        if ($neigh->size <= $sizeUp) {
                            $sunBalance -= $neigh->size;
                            continue;
                        }
                    } else {
                        $oppSize = $game->oppPessimisticSize($neigh, $oppGrowCost);
                        if ($oppSize <= $sizeUp) {
                            $sunBalance += $neigh->size;
                        }
                    }
                }
            }

            $score = $game->countCellScore($this->field->byIndex($tree->index));

            $treesScore[] = [
                'tree' => $tree,
                'shadow' => $sunBalance,
                'score' => $score,
            ];
        }
        if ($treesScore === []) {
            return null;
        }

        usort(
            $treesScore,
            function (array $a, array $b) {
                $sort = $b['shadow'] <=> $a['shadow'];
                if ($sort === 0) {
                    $sort = $b['score'] <=> $a['score'];
                }
                return $sort;
            }
        );
        $best = array_shift($treesScore);

        return $this->factory($best['tree']->index);
    }

    public function isActive(Game $game): bool
    {
        $bySize = $game->countTreesBySize();
        if ($bySize[3] > 3) {
            return false;
        }

        return true;
    }

    /**
     * @param \App\Game $game
     * @return \App\Tree[]
     */
    public function filterTrees(Game $game): array
    {
        $growCost = $game->countGrowCost();

        return array_filter(
            $game->mine,
            function (Tree $tree) use ($game, $growCost) {
                if ($tree->size === self::MAX_LEVEL) {
                    return false;
                }
                if ($tree->isDormant) {
                    return false;
                }
                if ($growCost[$tree->size] > $game->me->sun) {
                    return false;
                }

                if ($tree->size > 0 && $game->countDaysRemaining() === 0) {
                    return false;
                }
                if ($tree->size > 1 && $game->countDaysRemaining() === 1) {
                    return false;
                }

                return true;
            }
        );
    }

    public function factory(int $index): Action
    {
        return Action::factory(Action::TYPE_GROW, $index);
    }
}

final class ChopStrategy extends AbstractStrategy
{
    public const CHOP_SIZE = 3;

    public function action(Game $game): ?Action
    {
        if (!$this->isActive($game)) {
            return null;
        }

        $trees = $this->filterTrees($game);
        if ($trees === []) {
            return null;
        }

        $shadow = new Shadow($this->field);
        $oppGrowCost = $game->countGrowCost(false);
        $treesScore = [];
        foreach ($trees as $tree) {
            $cellScore = $game->countCellScore($this->field->byIndex($tree->index));

            $sunBalance = 3;
            $shadowIn = $shadow->shadowIn($tree->index, $game->day + 1);
            foreach ($shadowIn as $distance => $cell) {
                $neigh = $game->tree($cell);
                if ($neigh === null) {
                    continue;
                }
                if ($neigh->isMine && $neigh->size === 3) {
                    $sunBalance = 0;
                    break;
                } else {
                    $oppSize = $game->oppPessimisticSize($neigh, $oppGrowCost);
                    if ($oppSize === 3) {
                        $sunBalance = 0;
                        break;
                    }
                }
            }

            $shadowOut = $shadow->shadowOut($tree->index, $game->day + 1);
            foreach ($shadowOut as $index) {
                $neigh = $game->tree($index);
                if ($neigh === null) {
                    continue;
                }
                if ($neigh->isMine && $neigh->size <= 3) {
                    $sunBalance -= $neigh->size;
                } else {
                    $sunBalance += $neigh->size;
                }
            }

            $treesScore[] = [
                'tree' => $tree,
                'cellScore' => $cellScore,
                'sunBalance' => $sunBalance,
            ];
        }
        if ($treesScore === []) {
            return null;
        }

        usort(
            $treesScore,
            function (array $a, array $b) use ($game) {
                $sort = $b['cellScore'] <=> $a['cellScore'];
                if ($sort === 0) {
                    $sort = $b['sunBalance'] <=> $a['sunBalance'];
                }
                return $sort;
            }
        );
        $best = array_shift($treesScore);

        return $this->factory($best['tree']->index);
    }

    public function isActive(Game $game): bool
    {
        if (Game::CHOP_COST > $game->me->sun) {
            return false;
        }

        if ($game->countDaysRemaining() < 5) {
            return true;
        }

        $bySize = $game->countTreesBySize();
        if ($bySize[self::CHOP_SIZE] < 4) {
            return false;
        }

        return true;
    }

    /**
     * @param \App\Game $game
     * @return \App\Tree[]
     */
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

    public function factory(int $index): Action
    {
        return Action::factory(Action::TYPE_COMPLETE, $index);
    }
}

class Shadow
{
    /** @var \App\Field */
    public $field;

    public function __construct(Field $field)
    {
        $this->field = $field;
    }

    public function sunDirection(int $day): int
    {
        return $day % 6;
    }

    public function shadowIn(int $index, int $day): array
    {
        $direction = $this->sunDirection($day);
        $direction = $this->field->oppositeDirection($direction);
        return $this->field->vector($index, $direction);
    }

    public function shadowOut(int $index, int $day): array
    {
        $direction = $this->sunDirection($day);
        return $this->field->vector($index, $direction);
    }

    public function isShadow(array $trees, int $index, int $size, int $day): bool
    {
        $vector = $this->shadowIn($index, $day);

        foreach ($vector as $distance => $neighCell) {
            $neighTree = $trees[$neighCell] ?? null;
            if ($neighTree === null) {
                continue;
            }

            $neighSize = $neighTree->size;
            if ($neighSize < $distance) {
                continue;
            }
            if ($neighSize >= $size) {
                return true;
            }
        }

        return false;
    }

    public function countSun(array $trees, int $index, int $day, int $days = 1): int
    {
        $sun = 0;
        $start = 1;
        $size = $trees[$index]->size ?? 0;
        if ($size === 0) {
            $start++;
        }
        if ($days < 1) {
            throw new InvalidArgumentException("Expected at least 1 day.");
        }

        foreach (range($start, $days) as $interval) {
            $nextDay = $day + $interval;
            if ($nextDay > Game::DAYS - 1) {
                break;
            }

            if ($size < 3) {
                $size++;
            }
            if (!$this->isShadow($trees, $index, $interval > 1 ? 1 : $size, $nextDay)) {
                $sun += $size;
            }
        }

        return $sun;
    }

    public function countShadows(array $trees, int $index, int $day): float
    {
        $sun = 0;
        $size = $trees[$index]->size ?? 0;

        $vector = $this->shadowOut($index, $day);
        $vector = array_slice($vector, 0, $size);
        foreach ($vector as $cellIndex) {
            $neighTree = $trees[$cellIndex] ?? null;
            if ($neighTree === null) {
                continue;
            }
            if ($neighTree->isMine) {
                $sun -= $neighTree->size;
            } else {
                $sun += 0.5 * $neighTree->size;
            }
        }

        return $sun;
    }
}

class McstNode
{
    /** @var \App\McstNode|null */
    public $parent = null;
    /** @var \App\McstNode[] */
    public $childs = [];

    public $visitCount = 0;
    public $winScore = 0;

    /** @var \App\Game */
    public $game;
    public $isMine;
    /** @var \App\Action */
    public $action;

    public function __construct(Game $game, bool $isMine)
    {
        $this->game = $game;
        $this->isMine = $isMine;
    }
}

class MctsSearch
{
    public function next(Game $game, int $maxPlays = 1000): Action
    {
        $node = new McstNode($game, false);
        $this->expand($node);

        $start = microtime(true);
        $plays = 0;
        while (true) {
            if ($plays > $maxPlays) {
                break;
            }

            $next = $this->select($node);
            $this->expand($next);
            $result = $this->simulate($next);

            $this->propogation($next, $result);
            $plays++;
        }

        $moves = [];
        foreach ($node->childs as $child) {
            $moves[] = [
                'action' => $child->action,
                'score' => $child->winScore,
            ];
        }

        usort(
            $moves,
            function ($a, $b) {
                return $b['score'] <=> $a['score'];
            }
        );
        $best = array_shift($moves);

        l($best);

        return $best['action'];
    }

    public function select(McstNode $rootNode)
    {
        $node = $rootNode;
        while ($node->childs !== []) {
            $node = $this->findBestNodeWithUCT($node);
        }
        return $node;
    }

    public function expand(McstNode $node)
    {
        $actions = $node->game->actions(!$node->isMine);

        foreach ($actions as $action) {
            $child = new McstNode($node->game, !$node->isMine);
            $child->action = $action;
            $child->parent = $node;
            $node->childs[] = $child;
        }
    }

    public function propogation(McstNode $node, int $result)
    {
        while (($node = $node->parent) !== null) {
            $node->visitCount++;
            $node->winScore += $result;
        }
    }

    public function simulate(McstNode $node): int
    {
        $game = clone $node->game;
        $game->me = clone $game->me;
        $game->opp = clone $game->opp;

        $trees = [];
        foreach ($game->trees as $index => $tree) {
            $trees[$index] = clone $tree;
        }
        $game->trees = $trees;

        $action = $node->action;
        $isMine = $node->isMine;

        // simulate
        while (true) {
            $player = $isMine ? $game->me : $game->opp;

            switch ($action->type) {
                case Action::TYPE_SEED:
                    $seedCost = $game->countSeedCost($isMine);
                    $player->sun -= $seedCost;
                    $game->trees[$action->params[1]] = new Tree($action->params[1], 0, $isMine, true);
                    $game->trees[$action->params[0]]->isDormant = true;
                    break;

                case Action::TYPE_GROW:
                    $growCost = $game->countGrowCost($isMine);
                    $tree = clone($game->tree($action->params[0]));
                    $player->sun -= $growCost[$tree->size];
                    $tree->size++;
                    $tree->isDormant = true;
                    $game->trees[$tree->index] = $tree;
                    break;

                case Action::TYPE_COMPLETE:
                    $player->sun -= 4;
                    $player->score += $game->countCellScore($game->field->byIndex($action->params[0]));
                    unset($game->trees[$action->params[0]]);
                    break;

                case Action::TYPE_WAIT:
                    $player->isWaiting = true;
                    break;
            }

            // next move ?
            $action = null;
            if (($isMine && !$game->opp->isWaiting) || (!$isMine && !$game->me->isWaiting)) {
                $isMine = !$isMine;
            }

            // next turn ?
            if ($game->me->isWaiting && $game->opp->isWaiting) {
                $game->day++;
                foreach ($game->trees as $tree) {
                    $tree->isDormant = false;
                }
                $game->me->sun += $game->countSunIncome(true);
                $game->opp->sun += $game->countSunIncome(false);
                $isMine = true;

                if ($game->day > 23) {
                    return $node->isMine && $game->me->score > $game->opp->score ? 1 : 0;
                }
            }

            $actions = $game->actions($isMine);
            if ($actions !== []) {
                $action = $actions[array_rand($actions)];
            }
        }
    }

    public function findBestNodeWithUCT(McstNode $node): McstNode
    {
        $childs = [];
        foreach ($node->childs as $child) {
            $magic = 0;
            switch ($child->action->type) {
                case $child->action->type === Action::TYPE_COMPLETE:
                    $magic += 3;
                    break;
                case $child->action->type === Action::TYPE_GROW:
                    $magic += 2;
                    break;
                case $child->action->type === Action::TYPE_SEED:
                    $magic += 1;
                    break;
                case $child->action->type === Action::TYPE_WAIT:
                    $magic -= 1;
                    break;
            }

            $childs[] = [
                'node' => $child,
                'uct' => $this->uctValue($child, $node->visitCount),
                'magic' => $magic,
            ];
        }

        usort(
            $childs,
            function ($a, $b) {
                $sort = $b['uct'] <=> $a['uct'];
                if ($sort === 0) {
                    $sort = $b['magic'] <=> $a['magic'];
                }
                return $sort;
            }
        );

        return array_shift($childs)['node'];
    }

    public function uctValue(McstNode $node, int $totalVisits)
    {
        if ($node->visitCount === 0) {
            return PHP_INT_MAX;
        }
        $c = sqrt(2);
        return ($node->winScore / $node->visitCount) + $c * sqrt(log($totalVisits) / $node->visitCount);
    }
}

class Mcts extends AbstractStrategy
{
    public function action(Game $game): ?Action
    {
        $search = new MctsSearch();
        return $search->next($game);
    }
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';
$_ENV['VERBOSE'] = $_ENV['VERBOSE'] ?? false;

if ($_ENV['APP_ENV'] !== 'prod') {
    return;
}

$_ENV['VERBOSE'] = true;

$field = new Field(Field::fromStream(STDIN));
$game = new Game($field, ...Game::fromStream(STDIN));

$game->day++;
$game->me->sun = 4;
$game->opp->sun = 4;

$search = new MctsSearch();
$action = $search->next($game);

echo Action::factory(Action::TYPE_WAIT, 'GL HF!');

while (true) {
    $game = new Game($field, ...Game::fromStream(STDIN));

    $action = $search->next($game, 200);
    echo $action;
}
