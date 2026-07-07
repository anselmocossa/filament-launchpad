<?php

namespace Filament\Launchpad\Filament\Resources\CardResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Resources\Pages\ListRecords;

class ListCards extends ListRecords
{
    protected static string $resource = CardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Cards are a global catalog: creating one here does not need a
            // Section — placement happens later via the drag&drop Builder
            // or the CardsRelationManager's Attach action.
            CreateAction::make()
                ->label(__('launchpad::launchpad.buttons.novo_card'))
                ->slideOver()
                ->schema(fn (): array => CardResource::cardFormComponents()),
        ];
    }
}
