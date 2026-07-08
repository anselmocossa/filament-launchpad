<?php

namespace Filament\Launchpad\Pages;

use Filament\Launchpad\Filament\Concerns\InteractsWithLaunchpadBuilder;
use Filament\Launchpad\Models\Page as PageModel;
use Filament\Launchpad\Models\Space as SpaceModel;
use Filament\Launchpad\Support\LaunchpadPermission;
use Filament\Pages\Page;

/**
 * A standalone shortcut to the drag&drop Builder, always bound to the HOME
 * page (the first Page, by sort, of the first Space, by sort) — no Resource
 * context, no breadcrumb trail through Spaces/Pages, not even a sidebar
 * entry. This is the "Editar Início" user-menu destination: one click from
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

    protected static ?string $slug = 'editar-inicio';

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
     * The mode is driven by PERMISSION, not by the page: a user allowed to
     * author the launchpad (super_admin, or holding the launchpad management
     * permission) gets the FULL builder here too — presets library, card
     * catalog, widgets, edit-on-click, pin/unpin, section management. Only a
     * user WITHOUT that permission gets the stripped personal view (add
     * available cards to their own home, reorder/remove their own). Absent
     * spatie/laravel-permission, everyone keeps the full builder (today's
     * behaviour).
     */
    protected function isPersonalMode(): bool
    {
        return ! LaunchpadPermission::check(auth()->user(), 'Update:Card');
    }

    public function mount(): void
    {
        if (! $this->resolveHomePage() instanceof PageModel) {
            $this->redirect(Launchpad::getUrl());
        }
    }

    public function getTitle(): string
    {
        return __('launchpad::launchpad.nav.editar_inicio');
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
        return SpaceModel::query()
            ->orderBy('sort')
            ->with(['pages' => fn ($query) => $query->orderBy('sort')])
            ->first()
            ?->pages
            ->first();
    }
}
