<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Field;

use function Tests\streamFromString;

final class FieldTest extends \PHPUnit\Framework\TestCase
{
    public function testFactory()
    {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/field.txt');
        $stream = streamFromString($fixture);
        $field = Field::fromStream($stream);

        $this->assertCount(37, $field->cells);

        $this->assertSame(0, $field->byIndex(0)->index);
        $this->assertSame(3, $field->byIndex(0)->richness);

        $this->assertSame([1, 2, 3, 4, 5, 6], $field->byIndex(0)->neighs);
    }
}
