<?php

namespace Filament\Launchpad\Filament\Resources\SpaceResource\Pages;

use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Resources\Pages\CreateRecord;

class CreateSpace extends CreateRecord
{
    protected static string $resource = SpaceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($panelId = LaunchpadPanel::id())) {
            $data['panel_id'] = $panelId;
        }

        return $data;
    }
}
