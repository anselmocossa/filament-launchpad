<?php

namespace Filament\Launchpad\Filament\Resources\SectionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Resources\Pages\EditRecord;

class EditSection extends EditRecord
{
    protected static string $resource = SectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $section = $this->getRecord();
        $page = $section->page;
        $space = $page->space;

        return [
            SpaceResource::getUrl('index') => 'Spaces',
            SpaceResource::getUrl('edit', ['record' => $space]) => $space->label,
            PageResource::getUrl('edit', ['record' => $page]) => $page->label,
            $section->title,
        ];
    }
}
