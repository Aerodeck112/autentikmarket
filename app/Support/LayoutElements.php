<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decorul vectorial al unei hărți (coordonate în METRI, ca standurile).
 *
 * Tipuri acceptate (orice altceva este respins):
 *  - area:   {type, kind, points:[[x,y],…], smooth?, label?:{ro,hu}, label_at?:[x,y], label_size?}
 *  - label:  {type, at:[x,y], text:{ro,hu}, size?, rotation?, tone?}
 *  - marker: {type, at:[x,y], symbol, r?, label?:{ro,hu}}
 *  - arrow:  {type, kind, points:[[x,y],…], smooth?, width?}
 *
 * Doar numere și texte scurte: nimic nu ajunge în SVG ca markup brut (textele sunt escapate în browser).
 */
class LayoutElements
{
    public const AREA_KINDS = ['ground', 'asphalt', 'paved', 'green', 'water', 'building', 'landmark', 'road', 'path'];

    public const MARKER_SYMBOLS = ['wc', 'info', 'church', 'statue', 'parking', 'entrance', 'water', 'power'];

    public const ARROW_KINDS = ['entrance', 'route'];

    public const LABEL_TONES = ['dark', 'muted', 'light', 'accent'];

    public const MAX_ELEMENTS = 300;

    public const MAX_POINTS = 400;

    /**
     * @param  array<int, mixed>  $elements
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    public static function sanitize(array $elements): array
    {
        if (count($elements) > self::MAX_ELEMENTS) {
            throw new InvalidArgumentException(__('Prea multe elemente de decor (maxim :n).', ['n' => self::MAX_ELEMENTS]));
        }

        $out = [];
        foreach (array_values($elements) as $i => $el) {
            if (! is_array($el)) {
                throw self::fail($i, 'format');
            }
            $out[] = match ($el['type'] ?? null) {
                'area' => self::area($el, $i),
                'label' => self::label($el, $i),
                'marker' => self::marker($el, $i),
                'arrow' => self::arrow($el, $i),
                default => throw self::fail($i, 'type'),
            };
        }

        return $out;
    }

    protected static function area(array $el, int $i): array
    {
        $kind = self::enum($el['kind'] ?? null, self::AREA_KINDS, $i, 'kind');
        $item = ['type' => 'area', 'kind' => $kind, 'points' => self::points($el['points'] ?? null, 3, $i)];
        if (! empty($el['smooth'])) {
            $item['smooth'] = true;
        }
        if (isset($el['label'])) {
            $item['label'] = self::text($el['label'], $i);
            if (isset($el['label_at'])) {
                $item['label_at'] = self::point($el['label_at'], $i);
            }
            if (isset($el['label_size'])) {
                $item['label_size'] = self::num($el['label_size'], 0.3, 10, $i);
            }
        }

        return $item;
    }

    protected static function label(array $el, int $i): array
    {
        $item = ['type' => 'label', 'at' => self::point($el['at'] ?? null, $i), 'text' => self::text($el['text'] ?? null, $i)];
        $item['size'] = isset($el['size']) ? self::num($el['size'], 0.3, 10, $i) : 1.0;
        if (isset($el['rotation'])) {
            $item['rotation'] = self::num($el['rotation'], -360, 360, $i);
        }
        if (isset($el['tone'])) {
            $item['tone'] = self::enum($el['tone'], self::LABEL_TONES, $i, 'tone');
        }

        return $item;
    }

    protected static function marker(array $el, int $i): array
    {
        $item = [
            'type' => 'marker',
            'at' => self::point($el['at'] ?? null, $i),
            'symbol' => self::enum($el['symbol'] ?? null, self::MARKER_SYMBOLS, $i, 'symbol'),
            'r' => isset($el['r']) ? self::num($el['r'], 0.3, 5, $i) : 1.0,
        ];
        if (isset($el['label'])) {
            $item['label'] = self::text($el['label'], $i);
        }

        return $item;
    }

    protected static function arrow(array $el, int $i): array
    {
        $item = [
            'type' => 'arrow',
            'kind' => self::enum($el['kind'] ?? 'route', self::ARROW_KINDS, $i, 'kind'),
            'points' => self::points($el['points'] ?? null, 2, $i, 20),
            'width' => isset($el['width']) ? self::num($el['width'], 0.05, 5, $i) : 0.3,
        ];
        if (! empty($el['smooth'])) {
            $item['smooth'] = true;
        }

        return $item;
    }

    protected static function points(mixed $points, int $min, int $i, int $max = self::MAX_POINTS): array
    {
        if (! is_array($points) || count($points) < $min || count($points) > $max) {
            throw self::fail($i, 'points');
        }

        return array_map(fn ($p) => self::point($p, $i), array_values($points));
    }

    protected static function point(mixed $p, int $i): array
    {
        if (! is_array($p) || count($p) !== 2) {
            throw self::fail($i, 'point');
        }
        [$x, $y] = array_values($p);

        return [self::num($x, -50, 550, $i), self::num($y, -50, 550, $i)];
    }

    protected static function num(mixed $v, float $min, float $max, int $i): float
    {
        if (! is_int($v) && ! is_float($v) && ! (is_string($v) && is_numeric($v))) {
            throw self::fail($i, 'number');
        }
        $f = (float) $v;
        if (! is_finite($f) || $f < $min || $f > $max) {
            throw self::fail($i, 'range');
        }

        return round($f, 2);
    }

    /** @return array{ro: string, hu: string} */
    protected static function text(mixed $t, int $i): array
    {
        if (is_string($t)) {
            $t = ['ro' => $t, 'hu' => $t];
        }
        if (! is_array($t)) {
            throw self::fail($i, 'text');
        }
        $ro = trim((string) ($t['ro'] ?? ''));
        $hu = trim((string) ($t['hu'] ?? ''));
        if (($ro === '' && $hu === '') || mb_strlen($ro) > 120 || mb_strlen($hu) > 120) {
            throw self::fail($i, 'text');
        }

        return ['ro' => $ro !== '' ? $ro : $hu, 'hu' => $hu !== '' ? $hu : $ro];
    }

    protected static function enum(mixed $v, array $allowed, int $i, string $field): string
    {
        if (! is_string($v) || ! in_array($v, $allowed, true)) {
            throw self::fail($i, $field);
        }

        return $v;
    }

    protected static function fail(int $i, string $what): InvalidArgumentException
    {
        return new InvalidArgumentException(__('Element de decor invalid (#:i, :what).', ['i' => $i + 1, 'what' => $what]));
    }
}
