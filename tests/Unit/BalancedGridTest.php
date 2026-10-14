<?php

use Filament\Launchpad\Support\BalancedGrid;

it('spreads nine tiles over two even rows instead of leaving one alone', function () {
    // Room for eight: 8 + 1 becomes 5 + 4.
    expect(BalancedGrid::columns(9, 8))->toBe(5)
        ->and(BalancedGrid::columns(9, 9))->toBe(9)
        ->and(BalancedGrid::columns(9, 4))->toBe(3)
        ->and(BalancedGrid::columns(11, 8))->toBe(6)
        ->and(BalancedGrid::columns(8, 8))->toBe(8)
        ->and(BalancedGrid::columns(5, 3))->toBe(3)
        ->and(BalancedGrid::columns(3, 1))->toBe(1);
});

it('never asks for more columns than fit, nor more than there are tiles', function () {
    foreach (range(1, 20) as $count) {
        foreach (range(1, 12) as $fit) {
            $columns = BalancedGrid::columns($count, $fit);

            expect($columns)->toBeLessThanOrEqual(min($fit, $count))
                // the same number of rows as the plain grid would use
                ->and((int) ceil($count / $columns))->toBe((int) ceil($count / min($fit, $count)));
        }
    }
});

it('switches columns only at the widths where one more tile fits', function () {
    // 176px tiles, 14px gaps: 5 fit from 5*176 + 4*14 = 936px, 9 from 1696px.
    $css = BalancedGrid::css(9, 176);

    expect($css)
        ->toStartWith('.lp-fluid-9-176{grid-template-columns:repeat(1,minmax(0,1fr))}')
        ->toContain('@container (min-width:936px){.lp-fluid-9-176{grid-template-columns:repeat(5,minmax(0,1fr))}}')
        ->toContain('@container (min-width:1696px){.lp-fluid-9-176{grid-template-columns:repeat(9,minmax(0,1fr))}}')
        // 4 fit, but 9 tiles still take 3 rows: stays at 3 columns, no rule of its own
        ->not->toContain('repeat(4,');
});
