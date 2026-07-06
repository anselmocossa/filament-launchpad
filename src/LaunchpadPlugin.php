<?php

namespace Filament\Launchpad;

use Filament\Contracts\Plugin;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Pages\Launchpad;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Str;

class LaunchpadPlugin implements Plugin
{
    protected bool $enabled = true;

    protected string $brandName = 'Launchpad';

    protected ?string $brandLogo = null;

    protected ?string $brandInitials = null;

    protected string $accentColor = '#16a34a';

    protected bool $darkHeader = false;

    protected string $tileSize = 'normal';

    /**
     * @var array<LaunchpadSpace>
     */
    protected array $spaces = [];

    protected int $notificationCount = 0;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'launchpad';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            Launchpad::class,
        ]);
    }

    /**
     * Registers the launchpad sub-nav as a SECOND navbar glued directly under
     * the native Filament topbar: PanelsRenderHook::CONTENT_BEFORE renders
     * outside the padded/max-width <main> region (full width of the content
     * column, right of the sidebar), so it reads as a continuation of the
     * topbar instead of an indented block inside the tile grid. Scoped to
     * the Launchpad page only, so it never leaks onto other panel pages.
     */
    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_BEFORE,
            fn () => view('launchpad::hooks.launchpad-bar'),
            scopes: Launchpad::class,
        );
    }

    public function enabled(bool $enabled = true): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Sets the brand name/logo/initials. Still used for the page <title>
     * (see Launchpad::getTitle()), but NO LONGER rendered in the sub-nav —
     * the launchpad bar now holds only the app tabs, and branding is left to
     * the native Filament topbar. Accepted for backwards-compat so existing
     * configs calling ->brand(...) keep working without changes.
     *
     * @deprecated The sub-nav brand block was removed; this setter has no
     *             visual effect on the launchpad bar anymore.
     */
    public function brand(string $name, ?string $logo = null, ?string $initials = null): static
    {
        $this->brandName = $name;
        $this->brandLogo = $logo;
        $this->brandInitials = $initials;

        return $this;
    }

    public function accentColor(string $hex): static
    {
        $this->accentColor = $hex;

        return $this;
    }

    /**
     * @deprecated No-op since the launchpad no longer owns a custom header;
     *             light/dark theming is handled natively by Filament. Accepted
     *             for backwards-compat only.
     */
    public function darkHeader(bool $condition = true): static
    {
        $this->darkHeader = $condition;

        return $this;
    }

    public function tileSize(string $size): static
    {
        $this->tileSize = $size;

        return $this;
    }

    /**
     * @param  array<LaunchpadSpace>  $spaces
     */
    public function spaces(array $spaces): static
    {
        $this->spaces = $spaces;

        return $this;
    }

    /**
     * @deprecated Use spaces() instead. Every LaunchpadTab IS a LaunchpadSpace
     *             (its ->groups() sugar wraps the given sections in a single
     *             default LaunchpadPage), so legacy tab configs are stored
     *             and normalized exactly the same way as spaces() configs.
     *
     * @param  array<LaunchpadSpace>  $tabs
     */
    public function tabs(array $tabs): static
    {
        return $this->spaces($tabs);
    }

    /**
     * @deprecated No-op since the notification bell now lives in the native
     *             Filament topbar (use the panel's database notifications).
     *             Accepted for backwards-compat only.
     */
    public function notificationCount(int $count): static
    {
        $this->notificationCount = $count;

        return $this;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function getBrandLogo(): ?string
    {
        return $this->brandLogo;
    }

    public function getBrandInitials(): string
    {
        if (filled($this->brandInitials)) {
            return $this->brandInitials;
        }

        return Str::of($this->brandName)
            ->explode(' ')
            ->filter()
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }

    public function getAccentColor(): string
    {
        return $this->accentColor;
    }

    public function isDarkHeader(): bool
    {
        return $this->darkHeader;
    }

    public function getTileSize(): string
    {
        return $this->tileSize;
    }

    public function getTileWidth(): int
    {
        return $this->tileSize === 'compact' ? 150 : 176;
    }

    /**
     * Header/tab-bar palette derived from the darkHeader() setting, per the
     * design's theming rules (the page body always stays light).
     *
     * @return array{headerBg: string, headerBorder: string, headerText: string, headerMuted: string, searchBg: string}
     */
    public function getHeaderColors(): array
    {
        if ($this->darkHeader) {
            return [
                'headerBg' => '#1f2937',
                'headerBorder' => '#374151',
                'headerText' => '#f9fafb',
                'headerMuted' => '#9ca3af',
                'searchBg' => '#111827',
            ];
        }

        return [
            'headerBg' => '#ffffff',
            'headerBorder' => '#e5e7eb',
            'headerText' => '#111827',
            'headerMuted' => '#6b7280',
            'searchBg' => '#f9fafb',
        ];
    }

    /**
     * @return array<LaunchpadSpace>
     */
    public function getSpaces(): array
    {
        return $this->spaces;
    }

    /**
     * @deprecated Use getSpaces() instead. Kept for backwards-compat.
     *
     * @return array<LaunchpadSpace>
     */
    public function getTabs(): array
    {
        return $this->getSpaces();
    }

    public function getNotificationCount(): int
    {
        return $this->notificationCount;
    }
}
