<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

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

    /** @var \App\Tree[] */
    public $mine;

    public static function fromStream($stream): self
    {
        fscanf($stream, '%d', $day);
        fscanf($stream, '%d', $nutrients);
        $me = Player::factory(...fscanf($stream, '%d %d'));
        $opp = Player::factory(...fscanf($stream, '%d %d %d'));

        fscanf($stream, '%d', $numTrees);
        $trees = [];
        for ($i = 0; $i < $numTrees; $i++) {
            $trees[] = Tree::factory(...fscanf($stream, '%d %d %d %d'));
        }

        $actions = [];
        fscanf($stream, '%d', $numActions);
        if ($numActions > 0) {
            $actions = [];
            for ($i = 0; $i < $numActions; $i++) {
                $actions[] = stream_get_line($stream, 32, "\n");
            }
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
        array $trees,
        array $actions
    ) {
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

    public function countSunIncome(?bool $isMine = true): int
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
        $cellsScore = [];
        foreach ($cells as $index) {
            $countSun = $shadow->countSun($game->trees, $index, $game->day, 6);
            $countCellScore = $game->countCellScore($this->field->byIndex($index));
            $cellsScore[] = [
                'index' => $index,
                'sun' => $countSun,
                'cell' => $countCellScore,
            ];
        }
        if ($cellsScore === []) {
            return null;
        }

        usort(
            $cellsScore,
            function (array $a, array $b) {
                $sort = $b['sun'] <=> $a['sun'];
                if ($sort === 0) {
                    $sort = $b['cell'] <=> $a['cell'];
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

        $bySize = [];
        foreach ($trees as $tree) {
            $bySize[$tree->size][$tree->index] = $tree;
        }

        $growCost = $game->countGrowCost();
        $bestSize = null;
        // count 2
        if (isset($bySize[1]) && ($growCost[1] + 1 <= 7)) {
            $bestSize = 1;
        } elseif (isset($bySize[0]) && $growCost[0] <= 2) {
            $bestSize = 0;
        } elseif (isset($bySize[2])) {
            $bestSize = 2;
        }
        if ($bestSize === null) {
            $sizeScore = [];
            foreach ($growCost as $size => $cost) {
                $sizeScore[] = [
                    'size' => $size,
                    'cost' => $cost,
                ];
            }
            uasort(
                $sizeScore,
                function (array $a, array $b) {
                    $sort = $a['cost'] <=> $b['cost'];
                    if ($sort === 0) {
                        $sort = $b['size'] <=> $a['size'];
                    }
                    return $sort;
                }
            );
            $bestSize = array_shift($sizeScore)['size'];
        }

        $oppGrowCost = $game->countGrowCost(false);
        $shadow = new Shadow($this->field);
        $treesScore = [];
        foreach ($bySize[$bestSize] as $tree) {
            /** @var \App\Tree $tree */
            $sun = $shadow->countSun($game->trees, $tree->index, $game->day);

            $shadowOut = $shadow->shadowOut($tree->index, $game->day + 1);
            $shadowOut = array_slice($shadowOut, 0, $tree->size + 1);
            foreach ($shadowOut as $index) {
                $inShadow = $game->tree($index);
                if ($inShadow === null) {
                    continue;
                }
                $size = $inShadow->size;
                // tree can grow
                if (!$inShadow->isDormant && $size < 3) {
                    $ghc = $inShadow->isMine ? $growCost[$size] : $oppGrowCost[$size];
                    $ghp = $inShadow->isMine ? $game->me->sun : $game->opp->sun;
                    if ($ghc <= $ghp) {
                        $size++;
                    }
                }
                if ($size <= $tree->size) {
                    $ghs = $shadow->countSun($game->trees, $inShadow->index, $game->day + 1);;
                    if ($tree->isMine) {
                        $sun -= $ghs;
                    } else {
                        $sun += $ghs * 0.5;
                    }
                }
            }

            $treesScore[] = [
                'tree' => $tree,
                'sun' => $sun,
            ];
        }
        if ($treesScore === []) {
            return null;
        }

        usort(
            $treesScore,
            function (array $a, array $b) {
                $sort = $b['sun'] <=> $a['sun'];
                return $sort;
            }
        );
        $best = array_shift($treesScore);

        return $this->factory($best['tree']->index);
    }

    public function isActive(Game $game): bool
    {
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

        $bySize = $game->countTreesBySize();
        if ($bySize[self::CHOP_SIZE] > count($trees)) {
            return null;
        }

        $shadow = new Shadow($this->field);
        $treesScore = [];
        foreach ($trees as $tree) {
            $sunScore = $shadow->countSun($game->trees, $tree->index, $game->day);
            $sunScore += $shadow->countShadows($game->trees, $tree->index, $game->day + 1);
            if ($sunScore > 3) {
                continue;
            }

            $cellScore = $game->countCellScore($tree->index);
            $treesScore[] = [
                'tree' => $tree,
                'sunScore' => $sunScore,
                'cellScore' => $cellScore,
            ];
        }
        if ($treesScore === []) {
            return null;
        }

        usort(
            $treesScore,
            function (array $a, array $b) use ($game) {
                $sort = $b['sunScore'] <=> $a['sunScore'];
                if ($sort === 0) {
                    $sort = $b['cellScore'] <=> $a['cellScore'];
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

        if ($game->countDaysRemaining() < 2) {
            return true;
        }

        if ($game->nutrients < 10) {
            return true;
        }

        if ($game->countTreesBySize(false)[self::CHOP_SIZE] > 3) {
            return true;
        }

        if ($game->countTreesBySize()[self::CHOP_SIZE] < 4) {
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

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';
$_ENV['VERBOSE'] = $_ENV['VERBOSE'] ?? false;

if ($_ENV['APP_ENV'] !== 'prod') {
    return;
}

$_ENV['VERBOSE'] = true;

$field = Field::fromStream(STDIN);
l($field->export());

Game::fromStream(STDIN);
echo Action::factory(Action::TYPE_WAIT, 'GL HF!');

$strategy = new CompositeStrategy(
    new ChopStrategy($field),
    new GrowStrategy($field),
    new SeedStrategy($field),
);

while (true) {
    $game = Game::fromStream(STDIN);
    l($game->export());

    $action = $strategy->action($game);
    if (!$action) {
        $action = Action::factory();
    }
    echo $action;
}
