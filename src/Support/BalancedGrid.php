<?php

namespace Filament\Launchpad\Support;

/**
 * Even rows for the 'fluid' tile grid. A plain auto-fit grid fills each row to
 * the brim and leaves the rest alone on the last one — nine tiles on a screen
 * that fits eight become 8 + 1. This spreads them instead (5 + 4): it works out,
 * for each container width, how many tiles fit, how many rows that takes, and
 * then the fewest columns that still need only that many rows.
 *
 * Pure CSS — one container query per width step — so there is no flicker and
 * no script; the grid's wrapper must be the query container
 * (container-type:inline-size).
 */
class BalancedGrid
{
    /** The class name for a grid of $count tiles at least $tileWidth px wide. */
    public static function className(int $count, int $tileWidth): string
    {
        return "lp-fluid-{$count}-{$tileWidth}";
    }

    /**
     * Columns to use when the container fits at most $fit tiles side by side.
     */
    public static function columns(int $count, int $fit): int
    {
        $fit = max(1, min($fit, $count));
        $rows = (int) ceil($count / $fit);

        return (int) ceil($count / $rows);
    }

    /** The stylesheet for className($count, $tileWidth). */
    public static function css(int $count, int $tileWidth, int $gap = 14): string
    {
        $class = '.'.static::className($count, $tileWidth);
        $css = "{$class}{grid-template-columns:repeat(1,minmax(0,1fr))}";
        $previous = 1;

        for ($fit = 2; $fit <= $count; $fit++) {
            $columns = static::columns($count, $fit);

            if ($columns === $previous) {
                continue;
            }

            // $fit tiles of $tileWidth px and the gaps between them.
            $minWidth = $fit * $tileWidth + ($fit - 1) * $gap;
            $css .= "@container (min-width:{$minWidth}px){{$class}{grid-template-columns:repeat({$columns},minmax(0,1fr))}}";
            $previous = $columns;
        }

        return $css;
    }
}
