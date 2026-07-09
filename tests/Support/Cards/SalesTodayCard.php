<?php

namespace Filament\Launchpad\Tests\Support\Cards;

use Filament\Launchpad\Launchpad\BaseCardPreset;

/**
 * Named with the generator's "Card" suffix on purpose — proves
 * BaseCardPreset::key()/defaultTitle() strip a trailing "Card" before
 * deriving, e.g. SalesTodayCard => key 'sales_today', default title
 * 'Sales Today'. Leaves every property at its base default (no override),
 * so toArray()'s full default shape can be asserted against.
 */
class SalesTodayCard extends BaseCardPreset
{
    //
}
