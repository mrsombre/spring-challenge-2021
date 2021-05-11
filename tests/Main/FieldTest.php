<?php

declare(strict_types=1);

namespace Tests\Main;

use App\Field;

use function Tests\makeField;
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

        $this->assertSame([1, 2, 3, 4, 5, 6], $field->neighs(0));
        $this->assertSame(36, $field->neigh(7, Field::DIRECTION_BOTTOM_RIGHT));
        $this->assertSame(0, $field->neigh(1, Field::DIRECTION_LEFT));
    }

    public function testOppositeDirection()
    {
        $field = makeField();
        $directions = [0, 1, 2, 3, 4, 5];
        $expected = [3, 4, 5, 0, 1, 2];

        foreach ($directions as $key => $direction) {
            $this->assertSame($expected[$key], $field->oppositeDirection($direction));
        }
    }

    public function testVector()
    {
        $field = makeField();

        $vector = $field->vector(15, 4);
        $this->assertSame([1 => $field->byIndex(31)], $vector);

        $vector = $field->vector(0, 0);
        $this->assertSame([1 => $field->byIndex(1), 2 => $field->byIndex(7), 3 => $field->byIndex(19)], $vector);
    }
}
