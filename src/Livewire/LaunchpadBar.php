<?php

namespace Filament\Launchpad\Livewire;

use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\LaunchpadPlugin;
use Illuminate\Contracts\View\View;
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
        $space = $this->getPlugin()->getSpaces()[0] ?? null;

        $this->activeSpace = $space?->getId() ?? '';
        $this->activePage = $this->firstPageId($space);
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
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSpacesData(): array
    {
        $plugin = $this->getPlugin();
        $accent = $plugin->getAccentColor();

        return array_map(function (LaunchpadSpace $space) use ($accent): array {
            $isActive = $space->getId() === $this->activeSpace;
            $pages = $space->getPages();

            return [
                'id' => $space->getId(),
                'label' => $space->getLabel(),
                'weight' => $isActive ? 600 : 500,
                'active' => $isActive,
                'border' => $isActive ? $accent : 'transparent',
                'hasDropdown' => count($pages) > 1,
                'pages' => array_map(fn (LaunchpadPage $page): array => [
                    'id' => $page->getId(),
                    'label' => $page->getLabel(),
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
