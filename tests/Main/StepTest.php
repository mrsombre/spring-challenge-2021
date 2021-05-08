<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Step;
use App\Tree;

use function Tests\streamFromString;

final class StepTest extends \PHPUnit\Framework\TestCase
{
    public function testFactory()
    {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/step.txt');

        $step = Step::fromStream(streamFromString($fixture));

        $this->assertSame(0, $step->day);
        $this->assertSame(20, $step->nutrients);
        $this->assertSame(18, $step->sun);
        $this->assertSame(1, $step->score);

        $this->assertSame(19, $step->oppSun);
        $this->assertSame(2, $step->oppScore);
        $this->assertSame(0, $step->oppIsWaiting);

        $this->assertSame(12, $step->numberOfTrees);
        $this->assertSame(7, $step->trees[7]->cellIndex);
        $this->assertSame(3, $step->trees[7]->size);
        $this->assertSame(0, $step->trees[7]->isMine);
        $this->assertSame(0, $step->trees[7]->isDormant);

        $this->assertSame(7, $step->numberOfActions);
        $this->assertSame('WAIT', $step->actions[0]);
    }

    public function dataGrowCost()
    {
        yield [
            [
                Tree::factory(0, 1),
            ],
            3,
        ];

        yield [
            [
                Tree::factory(0, 1),
                Tree::factory(1, 2),
            ],
            4,
        ];

        yield [
            [
                Tree::factory(0, 2),
            ],
            7,
        ];

        yield [
            [
                Tree::factory(0, 2),
                Tree::factory(1, 3),
            ],
            8,
        ];
    }

    /**
     * @dataProvider dataGrowCost
     */
    public function testGrowCost(array $trees, int $cost)
    {
        $step = new Step();
        $step->setTrees($trees);

        $this->assertSame($cost, $step->countGrowCost($step->trees->byIndex(0)->size));
    }
}
