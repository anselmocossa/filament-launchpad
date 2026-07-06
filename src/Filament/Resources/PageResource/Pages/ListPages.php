<?php

namespace Filament\Launchpad\Filament\Resources\PageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('launchpad::launchpad.buttons.nova_pagina')),
        ];
    }
}
