<?php

namespace Filament\Launchpad\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\UserCard;
use Filament\Launchpad\Support\LaunchpadScope;
use Filament\Launchpad\Support\LaunchpadTenant;

/**
 * The parent's "which store am I looking at?" control.
 *
 * Selecting a store makes the builder read and write that store's layer, so the
 * parent can shape one shop's home without leaving /admin — and selecting
 * nothing means authoring the shared template every store inherits.
 *
 * Only rendered where no tenant resolves on its own (i.e. the parent panel);
 * see LaunchpadTenant::applySelectorOverride() for why that is a boundary
 * rather than a convenience.
 */
trait HasLaunchpadTenantSelector
{
    public function tenantSelectorAction(): Action
    {
        return Action::make('selectLaunchpadTenant')
            ->label(fn (): string => $this->tenantSelectorLabel())
            ->icon('heroicon-o-building-storefront')
            ->color('gray')
            ->visible(fn (): bool => $this->showsTenantSelector())
            ->fillForm(fn (): array => ['tenant_id' => LaunchpadTenant::selected()])
            ->form([
                Select::make('tenant_id')
                    ->label(__('launchpad::launchpad.models.space'))
                    ->options(fn (): array => $this->tenantSelectorOptions())
                    ->searchable()
                    ->placeholder(__('launchpad::launchpad.messages.loja_template_global')),
            ])
            ->action(function (array $data): void {
                LaunchpadTenant::select($data['tenant_id'] ?? null);

                $this->redirect(request()->header('Referer') ?? url()->current());
            });
    }

    /**
     * Whether a store selector makes sense here: the host declared at least one
     * store, and this panel is not itself a store's.
     *
     * Deliberately keyed on ->tenants() rather than ->tenantResolver(), so the
     * parent panel — which by definition resolves no tenant — enables the
     * selector by declaring the list alone, with no null-returning resolver
     * just to satisfy a flag.
     */
    protected function showsTenantSelector(): bool
    {
        return blank(LaunchpadTenant::resolved())
            && LaunchpadTenant::options() !== [];
    }

    protected function tenantSelectorLabel(): string
    {
        $selected = LaunchpadTenant::selected();

        if (blank($selected)) {
            return __('launchpad::launchpad.messages.loja_template_global');
        }

        return LaunchpadTenant::options()[$selected] ?? $selected;
    }

    /**
     * Every store, annotated with how far it has drifted from the template —
     * the answer to "what did my stores change?" without opening each one.
     *
     * @return array<string, string>
     */
    protected function tenantSelectorOptions(): array
    {
        $counts = $this->tenantChangeCounts();

        $options = ['' => __('launchpad::launchpad.messages.loja_template_global')];

        foreach (LaunchpadTenant::options() as $id => $label) {
            $count = $counts[$id] ?? 0;

            $options[$id] = $label.' — '.($count === 0
                ? __('launchpad::launchpad.messages.loja_sem_alteracoes')
                : __('launchpad::launchpad.messages.loja_alteracoes', ['count' => $count]));
        }

        return $options;
    }

    /**
     * Deviations per store: overlay rows (additions and tombstones alike) plus
     * sections the store authored itself.
     *
     * @return array<string, int>
     */
    protected function tenantChangeCounts(): array
    {
        $counts = [];

        foreach (LaunchpadTenant::options() as $id => $label) {
            $counts[(string) $id] = UserCard::query()
                ->where('scope_key', LaunchpadScope::key((string) $id, null))
                ->count()
                + Section::query()->where('tenant_id', (string) $id)->count();
        }

        return $counts;
    }
}
