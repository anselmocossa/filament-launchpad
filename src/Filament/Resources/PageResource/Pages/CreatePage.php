<?php

namespace Filament\Launchpad\Filament\Resources\PageResource\Pages;

use Filament\Launchpad\Filament\Resources\Concerns\StampsLaunchpadTenant;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use StampsLaunchpadTenant;

    protected static string $resource = PageResource::class;
}
