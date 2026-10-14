<?php

namespace Tests\Unit;

use App\Support\LayoutElements;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LayoutElementsTest extends TestCase
{
    public function test_valid_elements_are_normalized(): void
    {
        $out = LayoutElements::sanitize([
            ['type' => 'area', 'kind' => 'green', 'points' => [[0, 0], ['10.456', 0], [10, 5]], 'label' => 'Parc', 'extra' => '<script>'],
            ['type' => 'label', 'at' => [1, 2], 'text' => ['ro' => 'Intrare', 'hu' => ''], 'rotation' => 45],
            ['type' => 'marker', 'at' => [3, 3], 'symbol' => 'wc'],
            ['type' => 'arrow', 'kind' => 'entrance', 'points' => [[0, 0], [5, 5]]],
        ]);

        $this->assertSame([10.46, 0.0], $out[0]['points'][1]);
        $this->assertSame(['ro' => 'Parc', 'hu' => 'Parc'], $out[0]['label']);
        $this->assertArrayNotHasKey('extra', $out[0], 'cheile necunoscute sunt eliminate');
        $this->assertSame(['ro' => 'Intrare', 'hu' => 'Intrare'], $out[1]['text'], 'limba lipsă preia textul celeilalte');
        $this->assertSame(1.0, $out[1]['size']);
        $this->assertSame(1.0, $out[2]['r']);
        $this->assertSame(0.3, $out[3]['width']);
    }

    public static function invalidElements(): array
    {
        return [
            'tip necunoscut' => [['type' => 'script', 'at' => [0, 0]]],
            'zonă necunoscută' => [['type' => 'area', 'kind' => 'lava', 'points' => [[0, 0], [1, 0], [1, 1]]]],
            'prea puține puncte' => [['type' => 'area', 'kind' => 'green', 'points' => [[0, 0], [1, 1]]]],
            'coordonată în afara limitelor' => [['type' => 'marker', 'symbol' => 'wc', 'at' => [9999, 0]]],
            'coordonată non-numerică' => [['type' => 'marker', 'symbol' => 'wc', 'at' => ['1;alert(1)', 0]]],
            'simbol necunoscut' => [['type' => 'marker', 'symbol' => '<img>', 'at' => [0, 0]]],
            'text gol' => [['type' => 'label', 'at' => [0, 0], 'text' => ['ro' => ' ', 'hu' => '']]],
            'text prea lung' => [['type' => 'label', 'at' => [0, 0], 'text' => str_repeat('a', 121)]],
            'element non-obiect' => ['abc'],
        ];
    }

    #[DataProvider('invalidElements')]
    public function test_invalid_elements_are_rejected(mixed $element): void
    {
        $this->expectException(InvalidArgumentException::class);
        LayoutElements::sanitize([$element]);
    }

    public function test_element_count_is_limited(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LayoutElements::sanitize(array_fill(0, LayoutElements::MAX_ELEMENTS + 1, ['type' => 'marker', 'symbol' => 'wc', 'at' => [0, 0]]));
    }
}
