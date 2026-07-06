<?php

namespace Filament\Launchpad\Filament\Resources\SectionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Resources\Pages\ListRecords;

class ListSections extends ListRecords
{
    protected static string $resource = SectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('launchpad::launchpad.buttons.nova_secao')),
        ];
    }
}
