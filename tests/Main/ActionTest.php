<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Action;

use function Tests\streamFromString;

class ActionTest extends \PHPUnit\Framework\TestCase
{
    public function dataStream()
    {
        yield [
            Action::factory('WAIT'),
            'WAIT',
        ];

        yield [
            Action::factory('SEED', 0, 1),
            'SEED 0 1',
        ];
    }

    /**
     * @dataProvider dataStream
     */
    public function testStream(Action $expected, string $action)
    {
        $stream = streamFromString($action);
        $this->assertEquals($expected, Action::factory(...fscanf($stream, '%s %d %d')));
    }
}
