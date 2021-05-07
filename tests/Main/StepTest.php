<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Step;

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
}
