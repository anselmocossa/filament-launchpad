<?php

namespace Filament\Launchpad\Tests\Support\Cards;

use Filament\Launchpad\Launchpad\BaseCardPreset;

/**
 * Named literally "Card" (nothing left after stripping the "Card" suffix)
 * — edge case proving BaseCardPreset::key()/defaultTitle() fall back to the
 * untouched basename instead of deriving from an empty string.
 */
class Card extends BaseCardPreset
{
    //
}
