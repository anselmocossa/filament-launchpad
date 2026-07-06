<?php

namespace Filament\Launchpad\Filament\Resources\PageResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('build')
                ->label('Construtor')
                ->icon('heroicon-o-squares-2x2')
                ->url(fn (): string => BuildLayout::getUrl(['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $page = $this->getRecord();

        return [
            SpaceResource::getUrl('index') => 'Spaces',
            SpaceResource::getUrl('edit', ['record' => $page->space]) => $page->space->label,
            $page->label,
        ];
    }
}
