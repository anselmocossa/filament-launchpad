<?php

namespace Filament\Launchpad\Filament\Resources\SpaceResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Launchpad\Filament\Resources\Concerns\ForksLaunchpadRecordOnEdit;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadOverride;
use Filament\Resources\Pages\EditRecord;

class EditSpace extends EditRecord
{
    use ForksLaunchpadRecordOnEdit;

    protected static string $resource = SpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // The protected default home is never deleted. In a tenant
                // context a delete HIDES the inherited record for that tenant
                // (a tombstone) rather than destroying the shared template.
                ->hidden(fn (Space $record): bool => (bool) $record->is_default)
                ->using(fn (Space $record) => LaunchpadOverride::deleteOrHide($record)),
        ];
    }
}
