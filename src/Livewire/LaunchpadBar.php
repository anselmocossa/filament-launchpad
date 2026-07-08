<?php

namespace Filament\Launchpad\Livewire;

use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Support\LaunchpadUrl;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Owns the launchpad sub-nav state (the active space + active page inside
 * it). Rendered full-width, edge-to-edge, via the Filament::CONTENT_BEFORE
 * render hook (registered in LaunchpadPlugin::boot()) so it sits OUTSIDE the
 * padded/max-width <main> region the tile cards live in — a second navbar
 * glued directly under the native topbar, holding the space tabs (each with
 * an optional pages dropdown when the space has more than one page).
 *
 * It is the single source of truth for activeSpace/activePage: it dispatches
 * the `launchpad-page-selected` Livewire event, which the Launchpad page
 * listens for to keep its own tile-grid state in sync.
 */
class LaunchpadBar extends Component
{
    public string $activeSpace = '';

    public string $activePage = '';

    public function mount(): void
    {
        $space = $this->findSpace((string) request()->query('space')) ?? ($this->getPlugin()->getSpaces()[0] ?? null);
        $pageId = (string) request()->query('page');

        $this->activeSpace = $space?->getId() ?? '';
        $this->activePage = $this->pageBelongsToSpace($space, $pageId) ? $pageId : $this->firstPageId($space);
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

    /**
     * Activates a space and its first page (the default entry point when a
     * space is chosen from the sub-nav directly, rather than via its
     * dropdown).
     */
    public function selectSpace(string $spaceId): void
    {
        $this->activeSpace = $spaceId;
        $this->activePage = $this->firstPageId($this->findSpace($spaceId));

        $this->dispatch('launchpad-page-selected', space: $this->activeSpace, page: $this->activePage);
        $this->redirectToLaunchpadWhenNeeded();
    }

    /**
     * Activates a specific page inside a space (chosen from the space's
     * pages dropdown).
     */
    public function selectPage(string $spaceId, string $pageId): void
    {
        $this->activeSpace = $spaceId;
        $this->activePage = $pageId;

        $this->dispatch('launchpad-page-selected', space: $spaceId, page: $pageId);
        $this->redirectToLaunchpadWhenNeeded();
    }

    protected function redirectToLaunchpadWhenNeeded(): void
    {
        $url = LaunchpadUrl::panelHome([
            'space' => $this->activeSpace,
            'page' => $this->activePage,
        ]);

        if (url()->current() === strtok($url, '?')) {
            return;
        }

        $this->redirect($url);
    }

    /**
     * Steps ONE level up the launchpad breadcrumb path, like walking back a
     * "Início / Space / Página" trail toward the root:
     *   1. On a space's non-first page → go up to that space's first page.
     *   2. On a space's first page (but not the root space) → go to the root
     *      space (the first one) and its first page.
     *   3. Already at the root → no-op.
     * Triggered by the topbar "‹" button via the `launchpad-back` Livewire
     * event (dispatched only when the launchpad bar is present on the page;
     * elsewhere the button falls back to the browser history).
     */
    #[On('launchpad-back')]
    public function goUp(): void
    {
        $space = $this->findSpace($this->activeSpace);

        if ($space instanceof LaunchpadSpace) {
            $firstPageId = $this->firstPageId($space);

            if ($this->activePage !== '' && $this->activePage !== $firstPageId) {
                $this->selectPage($this->activeSpace, $firstPageId);

                return;
            }
        }

        $rootSpace = $this->getPlugin()->getSpaces()[0] ?? null;

        if ($rootSpace instanceof LaunchpadSpace && $rootSpace->getId() !== $this->activeSpace) {
            $this->selectSpace($rootSpace->getId());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSpacesData(): array
    {
        $plugin = $this->getPlugin();

        return array_map(function (LaunchpadSpace $space): array {
            $isActive = $space->getId() === $this->activeSpace;
            $pages = $space->getPages();

            return [
                'id' => $space->getId(),
                'label' => $space->getLabel(),
                'icon' => $space->getIcon(),
                'active' => $isActive,
                'hasDropdown' => count($pages) > 1,
                'pages' => array_map(fn (LaunchpadPage $page): array => [
                    'id' => $page->getId(),
                    'label' => $page->getLabel(),
                    'icon' => $page->getIcon(),
                    'active' => $isActive && $page->getId() === $this->activePage,
                ], $pages),
            ];
        }, $plugin->getSpaces());
    }

    public function render(): View
    {
        return view('launchpad::livewire.launchpad-bar', [
            'spaces' => $this->getSpacesData(),
        ]);
    }
}
