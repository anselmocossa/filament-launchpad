<?php

namespace Filament\Launchpad\Filament\Resources\SpaceResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Models\Space;
use Filament\Resources\Pages\EditRecord;

class EditSpace extends EditRecord
{
    protected static string $resource = SpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (Space $record): bool => $record->is_default),
        ];
    }
}
