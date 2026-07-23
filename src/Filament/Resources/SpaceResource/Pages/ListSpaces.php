<?php

namespace Filament\Launchpad\Filament\Resources\SpaceResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Launchpad\Filament\Concerns\HasLaunchpadTenantSelector;
use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Launchpad\Support\LaunchpadTenant;
use Filament\Resources\Pages\ListRecords;

class ListSpaces extends ListRecords
{
    use HasLaunchpadTenantSelector;

    protected static string $resource = SpaceResource::class;

    public function getSubheading(): ?string
    {
        return __('launchpad::launchpad.messages.spaces_intro');
    }

    /**
     * Lets the parent author another panel's launchpad — in practice, the tenant
     * panel's template, which is invisible from /admin because it carries
     * panel_id = 'tenant'. Hidden wherever a tenant resolves on its own, so it
     * never becomes a way for a tenant to browse the platform's own panel.
     */
    protected function panelSelectorAction(): Action
    {
        return Action::make('selectLaunchpadPanel')
            ->label(fn (): string => ucfirst(LaunchpadPanel::browsing() ?? ''))
            ->icon('heroicon-o-rectangle-group')
            ->color('gray')
            ->visible(fn (): bool => blank(LaunchpadTenant::resolved()) && count(LaunchpadPanel::options()) > 1)
            ->fillForm(fn (): array => ['panel_id' => LaunchpadPanel::browsing()])
            ->form([
                Select::make('panel_id')
                    ->label(__('launchpad::launchpad.messages.painel'))
                    ->options(fn (): array => LaunchpadPanel::options())
                    ->required(),
            ])
            ->action(function (array $data): void {
                LaunchpadPanel::selectBrowsing($data['panel_id'] ?? null);

                $this->redirect(SpaceResource::getUrl('index'));
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->panelSelectorAction(),
            $this->tenantSelectorAction(),
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
