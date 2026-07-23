<?php

namespace Filament\Launchpad\Filament\Resources\SpaceResource\Pages;

use Filament\Launchpad\Filament\Resources\Concerns\StampsLaunchpadTenant;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Resources\Pages\CreateRecord;

class CreateSpace extends CreateRecord
{
    use StampsLaunchpadTenant;

    protected static string $resource = SpaceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // StampsLaunchpadTenant adds tenant_id; panel_id is stamped on top so a
        // space a tenant creates carries both its panel and its tenant.
        $data = $this->stampLaunchpadTenant($data);

        if (filled($panelId = LaunchpadPanel::id())) {
            $data['panel_id'] = $panelId;
        }

        return $data;
    }
}
