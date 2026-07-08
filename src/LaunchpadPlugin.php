<?php

namespace Filament\Launchpad;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\Launchpad\TileGroup;
use Filament\Launchpad\Models\Card as CardModel;
use Filament\Launchpad\Models\Page as PageModel;
use Filament\Launchpad\Models\Section as SectionModel;
use Filament\Launchpad\Models\Space as SpaceModel;
use Filament\Launchpad\Pages\EditHome;
use Filament\Launchpad\Pages\Launchpad;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Launchpad\Support\LaunchpadVisibility;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\Schema;
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
     * KPI/shortcut tile grid layout: 'fixed' keeps each tile at the
     * configured square size; 'fluid' stretches tiles equally to fill
     * the row width via auto-fit minmax.
     */
    protected string $tileSizing = 'fixed';

    /**
     * @var array<LaunchpadSpace>
     */
    protected array $spaces = [];

    protected int $notificationCount = 0;

    /**
     * Card presets offered by the drag&drop Builder's "Biblioteca de Cards".
     * Each entry is an array with keys: key,title,icon,type,subtitle,
     * kpi_value,unit,trend,badge,target_type,target_value. Dragging a preset
     * onto a Section creates a Card seeded with these values (no live data —
     * developers wire kpi_source or edit the value afterwards).
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $cardLibrary = [];

    /**
     * Named, developer-registered KPI sources. Each entry is a callable
     * (usually a Closure) invoked with no eval and no user-controlled code —
     * only whatever the developer registered via kpiSources(). Cards in the
     * admin merely reference a source by name.
     *
     * @var array<string, callable>
     */
    protected array $kpiSources = [];

    /**
     * Extra native Filament widgets (StatsOverviewWidget, ChartWidget,
     * custom...) exposed in the drag&drop Builder's "Widgets" library group.
     * The plugin also auto-reads widgets already registered on the current
     * Filament panel via Filament::getWidgets(); this array is only for
     * overrides/additions. Each entry: key,class,label,icon,columnSpan. The DB
     * only ever stores the `key` on a Card (see Models/Card::widget_key) —
     * never the class — so rendering only ever resolves registered classes,
     * never an arbitrary string coming from the database.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $widgets = [];

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
            EditHome::class,
        ]);

        $panel->resources([
            SpaceResource::class,
            PageResource::class,
            SectionResource::class,
            CardResource::class,
        ]);

        // An "Edit Home" entry in the account/user menu: a one-click shortcut
        // to customize just the home page (the first space's first page) in the
        // drag&drop builder, without hunting through the Spaces resource tree.
        $panel->userMenuItems([
            'launchpad-edit-home' => MenuItem::make()
                ->label(__('launchpad::launchpad.nav.editar_inicio'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => $this->getHomeBuilderUrl()),
        ]);
    }

    /**
     * URL of the standalone "Edit Home" builder page, which itself
     * resolves and guards against the home page (the first space's first
     * page) not existing yet — this just needs the tables to exist so the
     * route/URL can be generated at all.
     */
    protected function getHomeBuilderUrl(): string
    {
        if (Schema::hasTable('launchpad_pages') && Schema::hasTable('launchpad_spaces')) {
            return EditHome::getUrl();
        }

        return Launchpad::getUrl();
    }

    /**
     * Registers the launchpad sub-nav as a SECOND navbar glued directly under
     * the native Filament topbar: PanelsRenderHook::CONTENT_BEFORE renders
     * outside the padded/max-width <main> region (full width of the content
     * column, right of the sidebar), so it reads as a continuation of the
     * topbar instead of an indented block inside the tile grid. It is global
     * inside the panel so the launchpad navigation stays available after a
     * user drills into resources or custom pages.
     */
    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_BEFORE,
            fn () => view('launchpad::hooks.launchpad-bar'),
        );

        // A "‹" back control placed right before the brand in the native
        // Filament topbar (TOPBAR_LOGO_BEFORE). Returns to the previous page via
        // the browser history — handy after drilling into a resource/page from a
        // launchpad tile. Unscoped: shows on every panel page next to the brand.
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_LOGO_BEFORE,
            fn () => view('launchpad::hooks.back-button'),
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
     * Controls tile grid layout: 'fixed' keeps tiles at their configured
     * square size; 'fluid' stretches them equally to fill the row width.
     *
     * Prefer: LaunchpadPlugin::make()->tileSizing('fixed')
     */
    public function tileSizing(string $sizing): static
    {
        $this->tileSizing = in_array($sizing, ['fixed', 'fluid'], true) ? $sizing : 'fixed';

        return $this;
    }

    public function getTileSizing(): string
    {
        return $this->tileSizing;
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
     * Config wins when set via ->spaces([...]); otherwise the launchpad is
     * database-driven and spaces are built from the Models and mapped to the
     * DTOs the renderer already consumes.
     *
     * @return array<LaunchpadSpace>
     */
    public function getSpaces(): array
    {
        if (filled($this->spaces)) {
            return $this->spaces;
        }

        return $this->getSpacesFromDatabase();
    }

    /**
     * @return array<LaunchpadSpace>
     */
    protected function getSpacesFromDatabase(): array
    {
        if (! Schema::hasTable('launchpad_spaces')) {
            return [];
        }

        $userId = auth()->id();

        $query = SpaceModel::query()->orderBy('sort');

        if (Schema::hasColumn('launchpad_spaces', 'panel_id') && filled($panelId = LaunchpadPanel::id())) {
            $query->forPanel($panelId);
        }

        return $query
            ->with([
                'pages.sections' => fn ($query) => $query
                    ->where(function ($query) use ($userId) {
                        $query->whereNull('user_id')
                            ->when($userId !== null, fn ($query) => $query->orWhere('user_id', $userId));
                    })
                    ->orderByRaw('case when user_id is null then 0 else 1 end')
                    ->orderBy('sort')
                    ->with([
                        'cards',
                        // Only the viewing user's own personalisation rows are
                        // loaded, so each user's launchpad renders their own
                        // added cards.
                        'userCards' => fn ($query) => $query
                            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->whereRaw('1 = 0'))
                            ->with('card'),
                    ]),
            ])
            ->get()
            ->map(fn (SpaceModel $space): ?LaunchpadSpace => $this->mapSpaceToDto($space))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Gated in cascade: a restricted Space is omitted outright; one that
     * originally had pages but ends up with none visible (every page either
     * restricted or itself emptied out by the same cascade) disappears too
     * — an empty Space is never shown. A Space with no pages at all (never
     * had any to begin with) still renders, unchanged from today.
     */
    protected function mapSpaceToDto(SpaceModel $space): ?LaunchpadSpace
    {
        if (! LaunchpadVisibility::canView($space)) {
            return null;
        }

        $pages = $space->pages
            ->map(fn (PageModel $page): ?LaunchpadPage => $this->mapPageToDto($page))
            ->filter()
            ->values()
            ->all();

        if ($space->pages->isNotEmpty() && blank($pages)) {
            return null;
        }

        return LaunchpadSpace::make($space->label, id: (string) $space->id)
            ->icon($space->icon)
            ->pages($pages);
    }

    /**
     * Same cascade rule as mapSpaceToDto(), one level down: a restricted
     * Page is omitted; a Page that had sections but none survive the
     * cascade disappears too; a Page with no sections at all still renders.
     */
    protected function mapPageToDto(PageModel $page): ?LaunchpadPage
    {
        if (! LaunchpadVisibility::canView($page)) {
            return null;
        }

        $sections = $page->sections
            ->map(fn (SectionModel $section): ?TileGroup => $this->mapSectionToDto($section))
            ->filter()
            ->values()
            ->all();

        if ($page->sections->isNotEmpty() && blank($sections)) {
            return null;
        }

        return LaunchpadPage::make($page->label, id: (string) $page->id)
            ->icon($page->icon)
            ->sections($sections);
    }

    /**
     * Same cascade rule one level further down, PLUS an authentic-Fiori rule
     * of its own: a restricted Section is omitted; a Section whose cards end
     * up with zero VISIBLE tiles is omitted too — whether that's because it
     * never referenced any card, every referenced card was gated out by
     * role, or a widget card's key no longer resolves. An empty section is
     * only ever meaningful in the Builder (where it's still a drop target);
     * on the rendered launchpad it simply does not exist.
     */
    protected function mapSectionToDto(SectionModel $section): ?TileGroup
    {
        if (! LaunchpadVisibility::canView($section)) {
            return null;
        }

        // A section renders, for the viewing user, the admin's PINNED cards
        // (fixed, shown to everyone, in the admin's order) followed by the
        // user's OWN added cards (from launchpad_user_cards, in their order).
        $pinned = $section->cards
            ->filter(fn (CardModel $card): bool => (bool) ($card->pivot->is_pinned ?? true));

        $userCards = $section->userCards
            ->sortBy('sort')
            ->map(fn ($userCard): ?CardModel => $userCard->card)
            ->filter();

        $tiles = $pinned->concat($userCards)
            ->map(fn (CardModel $card): ?Tile => $this->mapCardToDto($card))
            ->filter()
            ->values()
            ->all();

        if (blank($tiles)) {
            return null;
        }

        return TileGroup::make($section->title)->tiles($tiles);
    }

    /**
     * Cards of type "widget" are mapped separately (see mapWidgetCardToDto())
     * and may resolve to null (omitted) when their widget_key is not/no
     * longer registered — hence the nullable return type here.
     */
    protected function mapCardToDto(CardModel $card): ?Tile
    {
        if (! LaunchpadVisibility::canView($card)) {
            return null;
        }

        if ($card->type === 'widget') {
            return $this->mapWidgetCardToDto($card);
        }

        $tile = Tile::make($card->title);

        if (filled($card->subtitle)) {
            $tile->subtitle($card->subtitle);
        }

        if (filled($card->icon)) {
            $tile->icon($card->icon);
        }

        if ($card->type === 'kpi') {
            if (filled($card->kpi_source) && $this->getKpiSource($card->kpi_source) !== null) {
                $tile->kpi($this->getKpiSource($card->kpi_source));
            } elseif (filled($card->kpi_value)) {
                $tile->kpi($card->kpi_value);
            }

            if (filled($card->unit)) {
                $tile->unit($card->unit);
            }

            if (filled($card->trend)) {
                $tile->trend($card->trend, $card->trend_color ?? 'gray');
            }
        } elseif (filled($card->note)) {
            $tile->note($card->note);
        }

        // Badge applies to both variants (KPI and shortcut tiles).
        if (filled($card->badge)) {
            $tile->badge(
                $card->badge,
                $card->badge_bg ?? '#f3f4f6',
                $card->badge_color ?? '#374151',
            );
        }

        match ($card->target_type) {
            'url' => filled($card->target_value) ? $tile->url($card->target_value) : null,
            'resource' => filled($card->target_value) ? $tile->resource($card->target_value) : null,
            'page' => filled($card->target_value) ? $tile->page($card->target_value) : null,
            default => null,
        };

        return $tile;
    }

    /**
     * Maps a widget-type Card to a widget Tile, resolving its widget_key
     * against the developer-registered widgets(). Returns null (meaning: the
     * caller omits this tile entirely) when the key is blank or no longer
     * resolves to a registered widget — this can legitimately happen if a
     * widget was removed from the developer's registration after cards
     * referencing it were already saved. Never renders a class straight from
     * the database.
     */
    protected function mapWidgetCardToDto(CardModel $card): ?Tile
    {
        if (blank($card->widget_key)) {
            return null;
        }

        $widget = $this->getWidget($card->widget_key);

        if ($widget === null || blank($widget['class'] ?? null) || ! class_exists($widget['class'])) {
            return null;
        }

        return Tile::make($card->title ?: ($widget['label'] ?? $widget['class']))
            ->asWidget($widget['class'], $card->widget_column_span ?: ($widget['columnSpan'] ?? 'full'));
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

    /**
     * Registers named KPI sources. Callable multiple times — later calls are
     * merged with (not replace) previously registered sources.
     *
     * @param  array<string, callable>  $sources
     */
    public function kpiSources(array $sources): static
    {
        $this->kpiSources = array_merge($this->kpiSources, $sources);

        return $this;
    }

    /**
     * @return array<string, callable>
     */
    public function getKpiSources(): array
    {
        return $this->kpiSources;
    }

    public function getKpiSource(string $name): ?Closure
    {
        if (! array_key_exists($name, $this->kpiSources)) {
            return null;
        }

        return Closure::fromCallable($this->kpiSources[$name]);
    }

    /**
     * Registers card presets for the drag&drop Builder's library. Callable
     * multiple times — later calls are merged with (not replace) previously
     * registered presets.
     *
     * @param  array<int, array<string, mixed>>  $presets
     */
    public function cardLibrary(array $presets): static
    {
        $this->cardLibrary = [...$this->cardLibrary, ...$presets];

        return $this;
    }

    /**
     * Adds/overrides native Filament widgets available in the drag&drop
     * Builder's "Widgets" library group. Widgets already registered on the
     * panel via Filament's native widgets()/discoverWidgets() are auto-loaded;
     * this method is only needed for a custom label/icon/columnSpan or for a
     * widget not registered on the panel. Callable multiple times — later
     * calls are merged with (not replace) previously registered widgets. Each
     * entry: key,class,label,icon,columnSpan ('full' or an int).
     *
     * @param  array<int, array<string, mixed>>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = [...$this->widgets, ...$widgets];

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWidgets(): array
    {
        return collect($this->getPanelWidgets())
            ->merge($this->widgets)
            ->filter(fn (array $widget): bool => filled($widget['key'] ?? null) && filled($widget['class'] ?? null))
            ->keyBy('key')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWidget(string $key): ?array
    {
        foreach ($this->getWidgets() as $widget) {
            if (($widget['key'] ?? null) === $key) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getPanelWidgets(): array
    {
        try {
            return collect(Filament::getWidgets())
                ->map(fn (string|WidgetConfiguration $widget): ?array => $this->normalizePanelWidget($widget))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalizePanelWidget(string|WidgetConfiguration $widget): ?array
    {
        $class = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;

        if (blank($class) || ! class_exists($class)) {
            return null;
        }

        $properties = $widget instanceof WidgetConfiguration ? $widget->getProperties() : [];

        return [
            'key' => $properties['key'] ?? Str::of(class_basename($class))->kebab()->toString(),
            'class' => $class,
            'label' => $properties['label'] ?? Str::of(class_basename($class))->headline()->toString(),
            'icon' => $properties['icon'] ?? 'heroicon-o-squares-2x2',
            'columnSpan' => $properties['columnSpan'] ?? '6',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCardLibrary(): array
    {
        if (filled($this->cardLibrary)) {
            return $this->cardLibrary;
        }

        return [
            [
                'key' => 'kpi',
                'title' => 'KPI',
                'icon' => 'heroicon-o-chart-bar',
                'type' => 'kpi',
                'subtitle' => null,
                'kpi_value' => null,
                'unit' => null,
                'trend' => null,
                'badge' => null,
                'target_type' => 'none',
                'target_value' => null,
            ],
            [
                'key' => 'atalho',
                'title' => __('launchpad::launchpad.card_types.atalho'),
                'icon' => 'heroicon-o-squares-2x2',
                'type' => 'shortcut',
                'subtitle' => null,
                'kpi_value' => null,
                'unit' => null,
                'trend' => null,
                'badge' => null,
                'target_type' => 'none',
                'target_value' => null,
            ],
        ];
    }
}
