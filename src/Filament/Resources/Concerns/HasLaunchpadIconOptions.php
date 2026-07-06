<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

/**
 * Shared list of heroicons offered across the launchpad management UI
 * (Space icon, Card icon). Kept as a single source of truth so the icon
 * picker options never drift between resources.
 */
trait HasLaunchpadIconOptions
{
    /**
     * @return array<string, string>
     */
    protected static function launchpadIconOptions(): array
    {
        return [
            'heroicon-o-home' => __('launchpad::launchpad.icons.home'),
            'heroicon-o-squares-2x2' => __('launchpad::launchpad.icons.grid'),
            'heroicon-o-banknotes' => __('launchpad::launchpad.icons.notes'),
            'heroicon-o-chart-bar' => __('launchpad::launchpad.icons.chart'),
            'heroicon-o-shopping-cart' => __('launchpad::launchpad.icons.cart'),
            'heroicon-o-cube' => __('launchpad::launchpad.icons.cube'),
            'heroicon-o-folder' => __('launchpad::launchpad.icons.folder'),
            'heroicon-o-adjustments-vertical' => __('launchpad::launchpad.icons.settings'),
            'heroicon-o-users' => __('launchpad::launchpad.icons.users'),
            'heroicon-o-credit-card' => __('launchpad::launchpad.icons.card'),
            'heroicon-o-computer-desktop' => __('launchpad::launchpad.icons.terminal'),
            'heroicon-o-document' => __('launchpad::launchpad.icons.document'),
            'heroicon-o-archive-box' => __('launchpad::launchpad.icons.archive'),
            'heroicon-o-book-open' => __('launchpad::launchpad.icons.book'),
            'heroicon-o-exclamation-triangle' => __('launchpad::launchpad.icons.alert'),
        ];
    }
}
