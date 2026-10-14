<?php

namespace Filament\Launchpad\Livewire;

use Filament\Facades\Filament;
use Filament\Launchpad\Support\LaunchpadBookmarks;
use Filament\Notifications\Notification;
use Filament\Pages\Page as FilamentPage;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * "Save to Home" button in a page header (LaunchpadPlugin::bookmarks()). The
 * page's path is captured when the header first renders — Livewire's later
 * requests go to /livewire/update, so it cannot be read again then.
 */
class BookmarkButton extends Component
{
    #[Locked]
    public string $path = '';

    public bool $saved = false;

    public function mount(string $path): void
    {
        $this->path = $path;
        $this->saved = filled($this->userId()) && LaunchpadBookmarks::isSaved($this->userId(), $path);
    }

    /**
     * @param  string  $documentTitle  the browser tab title ("Suppliers - Portal"),
     *                                 the only title every Filament page — resource or custom — exposes alike
     */
    public function toggle(string $documentTitle = ''): void
    {
        $userId = $this->userId();
        $panel = Filament::getCurrentPanel();

        if (blank($userId) || $panel === null) {
            return;
        }

        if ($this->saved) {
            LaunchpadBookmarks::remove($userId, $this->path);
            $this->saved = false;

            Notification::make()->title(__('launchpad::launchpad.bookmarks.removed'))->success()->send();

            return;
        }

        $this->saved = LaunchpadBookmarks::save($userId, $panel->getId(), $this->path, $this->title($documentTitle), $this->icon());

        Notification::make()
            ->title(__($this->saved ? 'launchpad::launchpad.bookmarks.saved' : 'launchpad::launchpad.bookmarks.no_home'))
            ->{$this->saved ? 'success' : 'warning'}()
            ->send();
    }

    public function render(): View
    {
        return view('launchpad::livewire.bookmark-button');
    }

    protected function userId(): ?string
    {
        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }

    /** The tab title without the " - <brand>" suffix Filament appends. */
    protected function title(string $documentTitle): string
    {
        $brand = (string) Filament::getBrandName();
        $title = trim($documentTitle);

        if ($brand !== '' && Str::endsWith($title, ' - '.$brand)) {
            $title = Str::beforeLast($title, ' - '.$brand);
        }

        return Str::limit($title !== '' ? $title : $this->path, 60);
    }

    /** The icon of the page (or its resource) the path routes to; a bookmark otherwise. */
    protected function icon(): string
    {
        try {
            $route = app('router')->getRoutes()->match(Request::create($this->path));
            $class = $route->getControllerClass();

            if ($class !== null && is_subclass_of($class, ResourcePage::class)) {
                $icon = $class::getResource()::getNavigationIcon();
            } elseif ($class !== null && is_subclass_of($class, FilamentPage::class)) {
                $icon = $class::getNavigationIcon();
            }
        } catch (Throwable) {
            $icon = null;
        }

        return is_string($icon ?? null) && filled($icon) ? $icon : 'heroicon-o-bookmark';
    }
}
