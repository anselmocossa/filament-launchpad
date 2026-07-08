<?php

namespace Filament\Launchpad\Pages;

use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\Launchpad\TileGroup;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Support\LaunchpadPermission;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;

class Launchpad extends Page
{
    protected static ?string $slug = '/';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Shield-aware gate for the home page itself: absent spatie/laravel-permission
     * everyone keeps entering (today's behaviour). Present, only a user
     * holding the `View:Launchpad` permission (or the Shield `super_admin`
     * role) may open it — a role without it is denied the panel's home,
     * which IS the intended effect of wiring the launchpad's own page into
     * Shield's "Pages" permission tab.
     */
    public static function canAccess(): bool
    {
        return LaunchpadPermission::check(auth()->user(), 'View:Launchpad');
    }

    /**
     * Uses the DEFAULT Filament Page layout on purpose: the native panel
     * chrome (topbar + sidebar + breadcrumbs) stays intact, and the
     * launchpad only ADDS its sub-nav (space tabs) and tile grid inside the
     * page content area.
     */
    protected string $view = 'launchpad::pages.launchpad';

    public string $activeSpace = '';

    public string $activePage = '';

    public function mount(): void
    {
        $space = $this->findSpace((string) request()->query('space')) ?? ($this->getPlugin()->getSpaces()[0] ?? null);
        $pageId = (string) request()->query('page');

        $this->activeSpace = $space?->getId() ?? '';
        $this->activePage = $this->pageBelongsToSpace($space, $pageId) ? $pageId : $this->firstPageId($space);
    }

    public function getTitle(): string
    {
        return $this->getPlugin()->getBrandName();
    }

    /**
     * Empty heading so the launchpad does not render a duplicate page title
     * on top of the native topbar / breadcrumbs.
     */
    public function getHeading(): string
    {
        return '';
    }

    protected function getPlugin(): LaunchpadPlugin
    {
        return LaunchpadPlugin::get();
    }

    protected function findSpace(string $spaceId): ?LaunchpadSpace
    {
        foreach ($this->getPlugin()->getSpaces() as $space) {
            if ($space->getId() === $spaceId) {
                return $space;
            }
        }

        return null;
    }

    protected function firstPageId(?LaunchpadSpace $space): string
    {
        if (! $space instanceof LaunchpadSpace) {
            return '';
        }

        return ($space->getPages()[0] ?? null)?->getId() ?? '';
    }

    protected function pageBelongsToSpace(?LaunchpadSpace $space, string $pageId): bool
    {
        if (! $space instanceof LaunchpadSpace || blank($pageId)) {
            return false;
        }

        foreach ($space->getPages() as $page) {
            if ($page->getId() === $pageId) {
                return true;
            }
        }

        return false;
    }

    public function selectSpace(string $spaceId): void
    {
        $this->activeSpace = $spaceId;
        $this->activePage = $this->firstPageId($this->findSpace($spaceId));
    }

    public function selectPage(string $spaceId, string $pageId): void
    {
        $this->activeSpace = $spaceId;
        $this->activePage = $pageId;
    }

    /**
     * The launchpad sub-nav is a standalone `LaunchpadBar` Livewire component
     * (rendered full-width via a render hook — see LaunchpadPlugin::boot()).
     * It owns the space/page UI and dispatches this event; the page just
     * mirrors the selected space+page into its own state so the tile grid
     * switches sections.
     */
    #[On('launchpad-page-selected')]
    public function onPageSelected(string $space, string $page): void
    {
        $this->selectPage($space, $page);
    }

    public function open(int $groupIndex, int $tileIndex): void
    {
        $groups = $this->currentSections();
        $group = $groups[$groupIndex] ?? null;
        $tile = $group instanceof TileGroup ? ($group->getTiles()[$tileIndex] ?? null) : null;

        if (! $tile instanceof Tile) {
            return;
        }

        if ($tile->hasAction()) {
            call_user_func($tile->getAction());

            return;
        }

        $href = $tile->getUrl();

        if (filled($href)) {
            $this->redirect($href);

            return;
        }

        Notification::make()
            ->title('Abrir «'.$tile->getTitle().'»')
            ->send();
    }

    /**
     * Resolves the TileGroup sections of the active page inside the active
     * space. Degrades gracefully: an unknown active page (or a space with no
     * pages at all) falls back to the space's first page, and an unknown
     * active space falls back to the plugin's first space — never throwing.
     *
     * @return array<TileGroup>
     */
    protected function currentSections(): array
    {
        $space = $this->findSpace($this->activeSpace) ?? ($this->getPlugin()->getSpaces()[0] ?? null);

        if (! $space instanceof LaunchpadSpace) {
            return [];
        }

        foreach ($space->getPages() as $page) {
            if ($page->getId() === $this->activePage) {
                return $page->getSections();
            }
        }

        return ($space->getPages()[0] ?? null)?->getSections() ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGroupsData(): array
    {
        return array_map(
            fn (TileGroup $group): array => $group->toArray(),
            $this->currentSections(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getThemeData(): array
    {
        $plugin = $this->getPlugin();

        return [
            'accent' => $plugin->getAccentColor(),
            'tileW' => $plugin->getTileWidth(),
            'tileSizing' => $plugin->getTileSizing(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'theme' => $this->getThemeData(),
            'groups' => $this->getGroupsData(),
        ];
    }
}
