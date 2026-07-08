<?php

namespace Filament\Launchpad\Pages;

use Filament\Launchpad\Filament\Concerns\InteractsWithLaunchpadBuilder;
use Filament\Launchpad\Models\Page as PageModel;
use Filament\Launchpad\Models\Space as SpaceModel;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Launchpad\Support\LaunchpadPermission;
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
     * Edit Home is always the authenticated user's own layer, even for
     * super_admin users. Global home authoring stays in the Page builder
     * (/admin/pages/{record}/build); this shortcut must never let one user's
     * personal changes affect another user's Home.
     */
    protected function isPersonalMode(): bool
    {
        return true;
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
        $query = SpaceModel::query()->orderBy('sort');

        if (Schema::hasColumn('launchpad_spaces', 'panel_id') && filled($panelId = LaunchpadPanel::id())) {
            $query->forPanel($panelId);
        }

        $space = (clone $query)->where('is_default', true)->first()
            ?? $query->first();

        return $space
            ?->pages()
            ->orderBy('sort')
            ->first();
    }
}
