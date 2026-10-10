<?php

namespace Filament\Launchpad\Livewire;

use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Launchpad\Support\LaunchpadUrl;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

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
    /**
     * Session key (suffixed with the panel id) holding the space the user last
     * chose. It breaks ties when one URL has a card in several spaces.
     */
    public const SESSION_KEY = 'launchpad.last_space';

    public bool $topbarOnly = false;

    public string $activeSpace = '';

    public string $activePage = '';

    public function mount(bool $topbarOnly = false): void
    {
        $this->topbarOnly = $topbarOnly;

        $spaceId = (string) request()->query('space');
        $pageId = (string) request()->query('page');

        $space = $this->findSpace($spaceId);

        if ($space instanceof LaunchpadSpace) {
            $this->rememberSpace($space->getId());
        } elseif (blank($spaceId)) {
            // Outside the launchpad (a page opened from a card): keep the space
            // whose card links here selected, on the page that holds that card.
            [$space, $matchedPageId] = $this->resolveSpaceFromCurrentPath();

            if ($space instanceof LaunchpadSpace) {
                $pageId = $matchedPageId;
            }
        }

        $space ??= $this->getPlugin()->getSpaces()[0] ?? null;

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
     * Finds the space whose card links to the page being served, for requests
     * that carry no `?space=`.
     *
     * Runs once per request, from mount(), over the spaces the plugin already
     * built and memoised for this user (so only spaces, pages, sections and
     * tiles the user may see take part, and no query is added). A tile matches
     * when its URL path equals the request path or is an ancestor of it
     * (`/store/documentos` matches `/store/documentos/5/edit`); the query string
     * is ignored. Tiles pointing at the panel root, or above it, never match,
     * or they would match every page.
     *
     * Per space, its longest matching tile path counts. Then:
     *   1. the space the user last chose wins whenever it has a matching tile
     *      (this keeps the space visible while the user works inside a page
     *      opened from one of its cards, even if another space also links
     *      to a deeper URL);
     *   2. otherwise the space with the longest matching path wins;
     *   3. ties go to a space other than the first one (the first is "Início",
     *      whose cards are mostly shortcuts to other spaces' pages), and only if
     *      none exists to the first.
     *
     * @return array{0: ?LaunchpadSpace, 1: string} the space and the id of the page that holds the matching tile
     */
    protected function resolveSpaceFromCurrentPath(): array
    {
        $current = rtrim('/'.request()->path(), '/');
        $root = $this->panelRootPath();

        // The host root, or the panel root itself: nothing can match.
        if ($current === '' || $current === $root) {
            return [null, ''];
        }

        $spaces = $this->getPlugin()->getSpaces();

        // space index => the longest matching tile path and the page holding it.
        $matches = [];

        foreach ($spaces as $index => $space) {
            foreach ($space->getPages() as $page) {
                foreach ($page->getSections() as $section) {
                    foreach ($section->getTiles() as $tile) {
                        $path = $this->tilePath($tile);

                        if ($path === null || str_starts_with($root.'/', $path.'/')) {
                            continue;
                        }

                        if ($current !== $path && ! str_starts_with($current, $path.'/')) {
                            continue;
                        }

                        if (strlen($path) > ($matches[$index]['length'] ?? 0)) {
                            $matches[$index] = ['length' => strlen($path), 'page' => $page->getId()];
                        }
                    }
                }
            }
        }

        if ($matches === []) {
            return [null, ''];
        }

        $remembered = $this->rememberedSpaceId();

        foreach ($matches as $index => $match) {
            if ($remembered !== null && $spaces[$index]->getId() === $remembered) {
                return [$spaces[$index], $match['page']];
            }
        }

        $longest = max(array_column($matches, 'length'));
        $tied = array_keys(array_filter($matches, fn (array $match): bool => $match['length'] === $longest));
        $index = collect($tied)->first(fn (int $index): bool => $index !== 0) ?? $tied[0];

        return [$spaces[$index], $matches[$index]['page']];
    }

    /**
     * The tile's URL path without query or trailing slash, or null when the
     * tile has no local path to compare (widgets, inert tiles, non-path URLs).
     */
    protected function tilePath(Tile $tile): ?string
    {
        if ($tile->isWidget()) {
            return null;
        }

        $url = $tile->getUrl();

        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/')) {
            return null;
        }

        $path = rtrim($path, '/');

        return $path === '' ? null : $path;
    }

    protected function panelRootPath(): string
    {
        return rtrim((string) parse_url(LaunchpadUrl::panelHome(), PHP_URL_PATH), '/');
    }

    protected function sessionKey(): string
    {
        return self::SESSION_KEY.'.'.(LaunchpadPanel::id() ?? 'default');
    }

    protected function rememberSpace(string $spaceId): void
    {
        try {
            session([$this->sessionKey() => $spaceId]);
        } catch (Throwable) {
            // No session available (e.g. a stateless request): nothing to remember.
        }
    }

    protected function rememberedSpaceId(): ?string
    {
        try {
            $spaceId = session($this->sessionKey());
        } catch (Throwable) {
            return null;
        }

        return is_string($spaceId) && $spaceId !== '' ? $spaceId : null;
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
        $this->rememberChosenSpace($spaceId);

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
        $this->rememberChosenSpace($spaceId);

        $this->dispatch('launchpad-page-selected', space: $spaceId, page: $pageId);
        $this->redirectToLaunchpadWhenNeeded();
    }

    /**
     * Only an id that names a space the user can see is remembered: the id
     * arrives from the browser.
     */
    protected function rememberChosenSpace(string $spaceId): void
    {
        if ($this->findSpace($spaceId) instanceof LaunchpadSpace) {
            $this->rememberSpace($spaceId);
        }
    }

    protected function redirectToLaunchpadWhenNeeded(): void
    {
        $url = $this->isDefaultLocation()
            ? LaunchpadUrl::panelHome()
            : LaunchpadUrl::panelHome([
                'space' => $this->activeSpace,
                'page' => $this->activePage,
            ]);

        if (url()->current() === strtok($url, '?')) {
            return;
        }

        $this->redirect($url);
    }

    /**
     * Whether the current space/page pair is the one `mount()` picks when the
     * URL carries no parameters at all — the first space and its first page.
     *
     * Clicking "Home" was landing the user on `/?space=3&page=5`: a canonical
     * address dressed up as a deep link. It is the panel's root, it is where an
     * unadorned `/` already goes, and spelling it out has real costs — the URL
     * a user copies or bookmarks pins today's space and page ids, so a
     * reordered launchpad (or a restored database with different ids) sends
     * them somewhere else or nowhere at all. The parameters now appear only
     * when they actually say something: a space or page other than the default.
     */
    protected function isDefaultLocation(): bool
    {
        $firstSpace = $this->getPlugin()->getSpaces()[0] ?? null;

        if (! $firstSpace instanceof LaunchpadSpace) {
            return false;
        }

        return $this->activeSpace === $firstSpace->getId()
            && $this->activePage === $this->firstPageId($firstSpace);
    }

    /**
     * Steps ONE level up the launchpad breadcrumb path, like walking back a
     * "Início / Space / Página" trail toward the root:
     *   1. On a space's non-first page → go up to that space's first page.
     *   2. On a space's first page (but not the root space) → go to the root
     *      space (the first one) and its first page.
     *   3. Already at the root (or mounted at the root, e.g. on a resource page
     *      reached from a tile) → fall back to the browser history so the "‹"
     *      always goes back somewhere instead of being a dead no-op.
     * Triggered by the topbar "‹" button via the `launchpad-back` Livewire
     * event.
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

            return;
        }

        // Nothing left to walk up to. Fall back to the browser's own history so
        // the "‹" still returns the user to where they came from (e.g. from a
        // resource opened via a tile back to the launchpad).
        $this->js('window.history.back()');
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
        // Two views, not one @if: Livewire needs a single root element, and a
        // conditional at the top of the template leaves only its marker there.
        return view($this->topbarOnly ? 'launchpad::livewire.launchpad-topbar' : 'launchpad::livewire.launchpad-bar', [
            'spaces' => $this->getSpacesData(),
        ]);
    }
}
