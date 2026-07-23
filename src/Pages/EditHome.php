<?php

namespace Filament\Launchpad\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Launchpad\Filament\Concerns\InteractsWithLaunchpadBuilder;
use Filament\Launchpad\Models\Page as PageModel;
use Filament\Launchpad\Models\Space as SpaceModel;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Launchpad\Support\LaunchpadPermission;
use Filament\Launchpad\Support\LaunchpadScope;
use Filament\Launchpad\Support\LaunchpadTenant;
use Filament\Launchpad\Support\LaunchpadUrl;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * A standalone shortcut to the drag&drop Builder, always bound to the HOME
 * page (the first Page, by sort, of the first Space, by sort) — no Resource
 * context, no breadcrumb trail through Spaces/Pages, not even a sidebar
 * entry. This is the "Edit Home" user-menu destination: one click from
 * anywhere in the panel straight into customizing the home tiles, without
 * ever surfacing the management tree that BuildLayout (the Resource page)
 * lives under.
 *
 * All builder behaviour (drag&drop mutations, card library, edit-card modal)
 * is shared with BuildLayout via the InteractsWithLaunchpadBuilder trait —
 * this class only resolves WHICH Page record ("home") the trait operates on.
 */
class EditHome extends Page
{
    use InteractsWithLaunchpadBuilder;

    protected static ?string $slug = 'edit-home';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'launchpad::filament.resources.page-resource.pages.build-layout';

    /**
     * Shield-aware gate, mirroring Launchpad::canAccess(): absent
     * spatie/laravel-permission everyone keeps entering (today's
     * behaviour). Present, only a user holding the `View:EditHome`
     * permission (or the Shield `super_admin` role) may open this
     * shortcut into the home tile builder.
     */
    public static function canAccess(): bool
    {
        return LaunchpadPermission::check(auth()->user(), 'View:EditHome');
    }

    /**
     * Which layer this builder writes: 'user' (this person's own home) or
     * 'tenant' (the tenant's shared home, seen by every colleague). Live-bound
     * to the switcher in the toolbar.
     *
     * Phase H softened the old absolute rule — Edit Home used to be the
     * authenticated user's layer and nothing else — because a tenant owner had
     * no other way to shape the home their staff actually sees: global
     * authoring lives in /admin, which a tenant user cannot reach. The rule it
     * replaces still holds where it matters: the USER layer is still private,
     * and a user without permission to manage the tenant can never select the
     * tenant layer.
     */
    public string $layer = LaunchpadScope::USER;

    protected function isPersonalMode(): bool
    {
        return true;
    }

    protected function isTenantLayer(): bool
    {
        return $this->layer === LaunchpadScope::TENANT && $this->canManageTenantLayer();
    }

    /**
     * The tenant layer is only offered on a multi-tenant install (a resolver is
     * wired AND a tenant actually resolves) and only to a user allowed to
     * manage it — soft-gated like every other ability in the plugin, so an
     * install without spatie/laravel-permission keeps working.
     */
    public function canManageTenantLayer(): bool
    {
        return LaunchpadTenant::isEnabled()
            && filled(LaunchpadTenant::id())
            && LaunchpadPermission::check(auth()->user(), 'Manage:LaunchpadTenant');
    }

    /**
     * @return array<string, string>
     */
    public function getLayerOptions(): array
    {
        return [
            LaunchpadScope::TENANT => __('launchpad::launchpad.messages.camada_partilhada'),
            LaunchpadScope::USER => __('launchpad::launchpad.messages.camada_pessoal'),
        ];
    }

    public function mount(): void
    {
        if (! $this->resolveHomePage() instanceof PageModel) {
            $this->redirect(LaunchpadUrl::panelHome());

            return;
        }

        // A tenant manager lands on the tenant's home, because shaping what the
        // whole team sees is why they opened this page; personalising their own
        // is the deliberate second choice.
        if ($this->canManageTenantLayer()) {
            $this->layer = LaunchpadScope::TENANT;
        }
    }

    public function getTitle(): string
    {
        return __('launchpad::launchpad.nav.editar_inicio');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->layerSwitcherAction(),
            $this->restoreParentTemplateAction(),
        ];
    }

    /**
     * "A editar: [A home da tenant | A minha home]" — the control that makes the
     * tenant layer reachable at all for a tenant user, who has no /admin.
     */
    public function layerSwitcherAction(): Action
    {
        return Action::make('switchLaunchpadLayer')
            ->label(fn (): string => __('launchpad::launchpad.messages.a_editar')
                .': '.($this->getLayerOptions()[$this->layer] ?? ''))
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (): bool => $this->canManageTenantLayer())
            ->fillForm(fn (): array => ['layer' => $this->layer])
            ->form([
                Radio::make('layer')
                    ->hiddenLabel()
                    ->options(fn (): array => $this->getLayerOptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->layer = $data['layer'] ?? LaunchpadScope::USER;
            });
    }

    /**
     * No breadcrumb trail at all — this page is a direct shortcut, not part
     * of the Spaces/Pages management tree.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function builderPage(): PageModel
    {
        $home = $this->resolveHomePage();

        // mount() already redirects away when there is no home page yet, but
        // guard again here defensively (e.g. a Livewire action fired after
        // the underlying data changed mid-session).
        abort_if(! $home instanceof PageModel, 404);

        return $home;
    }

    /**
     * The home page: the first Page (by sort) of the first Space (by sort).
     * Null when the database has no Spaces/Pages seeded yet.
     */
    protected function resolveHomePage(): ?PageModel
    {
        $query = SpaceModel::query()->orderBy('sort');

        if (Schema::hasColumn('launchpad_spaces', 'panel_id') && filled($panelId = LaunchpadPanel::id())) {
            $query->forPanel($panelId);
        }

        // Never resolve "home" to a Space belonging to another tenant.
        if (Schema::hasColumn('launchpad_spaces', 'tenant_id')) {
            $query->forTenant(LaunchpadTenant::id());
        }

        $space = $query
            ->orderBy('id')
            ->first();

        $page = $space
            ?->pages()
            ->orderBy('sort')
            ->orderBy('id')
            ->first();

        if ($page instanceof PageModel) {
            return $page;
        }

        return $this->createDefaultHomePage();
    }

    protected function createDefaultHomePage(): PageModel
    {
        $spaceData = [
            'label' => 'Home',
            'icon' => 'heroicon-o-home',
            'is_default' => true,
            'sort' => 0,
        ];

        if (Schema::hasColumn('launchpad_spaces', 'panel_id') && filled($panelId = LaunchpadPanel::id())) {
            $spaceData['panel_id'] = $panelId;
        }

        $space = SpaceModel::query()->create($spaceData);

        $page = $space->pages()->create([
            'label' => 'Home',
            'icon' => 'heroicon-o-home',
            'sort' => 0,
        ]);

        $page->sections()->create([
            'title' => 'Home',
            'sort' => 0,
        ]);

        return $page;
    }
}
