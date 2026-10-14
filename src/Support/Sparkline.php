<?php

namespace Filament\Launchpad\Support;

/**
 * Geometry for the small chart a KPI tile can draw in place of its trend line
 * (KpiResult::sparkline()). Drawn as inline SVG on a fixed 100×28 viewBox that
 * the tile stretches to its width — no chart library, no JavaScript.
 *
 * The baseline is zero (or the lowest value, if any is negative), so a flat
 * series of zeros lies on the floor instead of floating mid-tile.
 */
final class Sparkline
{
    public const WIDTH = 100;

    public const HEIGHT = 28;

    public const TYPES = ['area', 'bar'];

    /**
     * @param  array<int|float|string>  $values
     * @return array{type: 'area', line: string, area: string}|array{type: 'bar', bars: list<array{x: float, y: float, w: float, h: float}>}|null
     */
    public static function geometry(array $values, string $type = 'area'): ?array
    {
        $values = array_values(array_map(
            fn (int|float|string $value): float => (float) $value,
            array_filter($values, 'is_numeric'),
        ));

        if ($values === []) {
            return null;
        }

        $floor = min(0.0, min($values));
        $range = (max($values) - $floor) ?: 1.0;

        // 1 unit of padding top and bottom so the stroke is never clipped.
        $y = fn (float $value): float => round(self::HEIGHT - 1 - (($value - $floor) / $range) * (self::HEIGHT - 2), 2);

        if ($type === 'bar') {
            $slot = self::WIDTH / count($values);
            $width = round($slot * 0.7, 2);
            $bars = [];

            foreach ($values as $i => $value) {
                // A zero still shows as a hairline, so the month is visibly there.
                $height = max(round(self::HEIGHT - 1 - $y($value), 2), 0.8);

                $bars[] = [
                    'x' => round($i * $slot + ($slot - $width) / 2, 2),
                    'y' => round(self::HEIGHT - 1 - $height, 2),
                    'w' => $width,
                    'h' => $height,
                ];
            }

            return ['type' => 'bar', 'bars' => $bars];
        }

        if (count($values) === 1) {
            $values[] = $values[0];
        }

        $last = count($values) - 1;
        $points = [];

        foreach ($values as $i => $value) {
            $points[] = round($i / $last * self::WIDTH, 2).' '.$y($value);
        }

        $line = 'M'.implode(' L', $points);

        return [
            'type' => 'area',
            'line' => $line,
            'area' => $line.' L'.self::WIDTH.' '.self::HEIGHT.' L0 '.self::HEIGHT.' Z',
        ];
    }
}
