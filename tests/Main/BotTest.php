<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Bot;

final class BotTest extends \PHPUnit\Framework\TestCase
{
    public function testMove()
    {
        $bot = new Bot([]);
        $move = $bot->move();

        $this->assertSame('WAIT', $move);
    }
}
