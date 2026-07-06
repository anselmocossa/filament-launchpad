<?php

namespace Filament\Launchpad\Filament\Resources\SpaceResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Resources\Pages\ListRecords;

class ListSpaces extends ListRecords
{
    protected static string $resource = SpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pages')
                ->label(__('launchpad::launchpad.table_columns.paginas'))
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->url(fn (): string => PageResource::getUrl('index')),
            Action::make('cards')
                ->label(__('launchpad::launchpad.table_columns.cards'))
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(fn (): string => CardResource::getUrl('index')),
            CreateAction::make()
                ->label(__('launchpad::launchpad.buttons.novo_space')),
        ];
    }
}
