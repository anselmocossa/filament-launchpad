<?php

namespace Filament\Launchpad\Filament\Resources\SectionResource\Pages;

use Filament\Launchpad\Filament\Resources\Concerns\StampsLaunchpadTenant;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSection extends CreateRecord
{
    use StampsLaunchpadTenant;

    protected static string $resource = SectionResource::class;
}
