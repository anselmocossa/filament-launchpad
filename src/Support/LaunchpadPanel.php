<?php

namespace Filament\Launchpad\Support;

use Filament\Facades\Filament;

class LaunchpadPanel
{
    public static function id(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (\Throwable) {
            return null;
        }
    }
}
