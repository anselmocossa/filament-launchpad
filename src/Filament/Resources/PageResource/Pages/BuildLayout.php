<?php

namespace Filament\Launchpad\Filament\Resources\PageResource\Pages;

use Filament\Launchpad\Filament\Concerns\InteractsWithLaunchpadBuilder;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Models\Page as PageModel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * The drag&drop "Construtor de Layout" bound to a Page record via the route
 * (/admin/pages/{record}/build), breadcrumbed through the Spaces resource
 * tree. All the builder behaviour itself lives in the shared
 * InteractsWithLaunchpadBuilder trait — this class only wires it to the
 * route-resolved record and the Resource-context breadcrumbs/title.
 */
class BuildLayout extends Page
{
    use InteractsWithLaunchpadBuilder;
    use InteractsWithRecord;

    protected static string $resource = PageResource::class;

    protected string $view = 'launchpad::filament.resources.page-resource.pages.build-layout';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return __('launchpad::launchpad.nav.construtor').' · '.$this->getRecord()->label;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $page = $this->getRecord();

        return [
            SpaceResource::getUrl('index') => __('launchpad::launchpad.nav.spaces'),
            SpaceResource::getUrl('edit', ['record' => $page->space]) => $page->space->label,
            PageResource::getUrl('edit', ['record' => $page]) => $page->label,
            __('launchpad::launchpad.nav.construtor'),
        ];
    }

    protected function builderPage(): PageModel
    {
        return $this->getRecord();
    }
}
