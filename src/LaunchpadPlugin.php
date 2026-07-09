<?php

namespace Filament\Launchpad;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Launchpad\KpiResult;
use Filament\Launchpad\Launchpad\KpiSource;
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
use Filament\Launchpad\Support\KpiResolver;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Launchpad\Support\LaunchpadUrl;
use Filament\Launchpad\Support\LaunchpadVisibility;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\WidgetConfiguration;
use FilesystemIterator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;

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
     * Named, developer-registered KPI sources (legacy path). Each entry is a
     * callable (usually a Closure) invoked with no eval and no
     * user-controlled code — only whatever the developer registered via
     * kpiSources(). Cards in the admin merely reference a source by name.
     *
     * Kept as its own array (rather than folded entirely into
     * $kpiSourceRegistry) so getKpiSource()/getKpiSources() keep returning
     * exactly what was registered via kpiSources(), byte-for-byte, and so
     * mapCardToDto() can tell "this key is a legacy closure" apart from "this
     * key is a Phase G class-based source" without inspecting the registry's
     * entry type. A key registered afterwards via kpis()/discoverKpis() is
     * removed from here (see registerKpiClass()), keeping "last registered
     * wins" true across both mechanisms.
     *
     * @var array<string, callable>
     */
    protected array $kpiSources = [];

    /**
     * Unified KPI source registry (Phase G): key => class-string<KpiSource>
     * for kpis()/discoverKpis() registrations, or an already-wrapped
     * anonymous KpiSource instance for kpiSources() legacy registrations.
     * Class-strings are resolved lazily (only instantiated when a rendered
     * card actually references that key) via getRegisteredKpiSource().
     *
     * @var array<string, class-string<KpiSource>|KpiSource>
     */
    protected array $kpiSourceRegistry = [];

    protected ?KpiResolver $kpiResolver = null;

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

    protected bool $registerResources = true;

    /**
     * When true (the default), register() scans
     * config('launchpad.generators.path') for concrete KpiSource classes and
     * registers each one automatically — no discoverKpis() call needed in
     * the host app. Turned off automatically the moment the developer
     * registers KPIs manually via kpis() or discoverKpis(), so the manual
     * registration always wins and nothing is ever registered twice; can
     * also be toggled explicitly.
     */
    protected bool $autoDiscoverKpis = true;

    public function autoRegisterResources(bool $condition = true): static
    {
        $this->registerResources = $condition;

        return $this;
    }

    /**
     * Toggles the automatic scan of config('launchpad.generators.path') for
     * KpiSource classes performed in register(). Calling kpis() or
     * discoverKpis() already turns this off implicitly; call this
     * explicitly to force it back on/off regardless of that.
     */
    public function autoDiscoverKpis(bool $enabled = true): static
    {
        $this->autoDiscoverKpis = $enabled;

        return $this;
    }

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
        if ($this->autoDiscoverKpis) {
            $this->scanForKpiClasses(
                (string) (config('launchpad.generators.path') ?? app_path('Filament/Launchpad')),
                (string) (config('launchpad.generators.namespace') ?? 'App\\Filament\\Launchpad'),
            );
        }

        $panel->pages([
            Launchpad::class,
            EditHome::class,
        ]);

        if ($this->registerResources) {
            $panel->resources([
                SpaceResource::class,
                PageResource::class,
                SectionResource::class,
                CardResource::class,
            ]);
        }

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

        return LaunchpadUrl::panelHome();
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

        $authId = auth()->id();
        $userId = $authId === null ? null : (string) $authId;

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
     *
     * KPI resolution (Phase G): a card's kpi_source is resolved at most once
     * here, then feeds value/unit/trend/badge. Both registration mechanisms
     * now share the SAME precedence — the LIVE SOURCE WINS, the card's static
     * value is the fallback — so an admin's fixed value only shows through
     * when there's no source (or the source returns null for that field,
     * which lets a source author selectively defer a field to the static
     * value). The two paths differ only in their degradation shape, which is
     * intrinsic to how each carries its value:
     *   - Legacy kpiSources() closure: handed to Tile as a Closure, so a
     *     throwing closure defers and degrades to "—" at render time
     *     (Tile::resolveKpi()); see tests/Feature/KpiSourceTest.php.
     *   - New kpis()/discoverKpis() class-based source: resolved eagerly here
     *     into a KpiResult, so authorize()=false or a throwing resolve()
     *     yields a null result → the card's static value (if any) shows,
     *     otherwise the value is simply absent — see Support\KpiResolver.
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

        $legacyKpiSource = filled($card->kpi_source) ? $this->getKpiSource($card->kpi_source) : null;

        $kpiResult = ($legacyKpiSource === null && filled($card->kpi_source))
            ? $this->resolveKpiResult($card->kpi_source)
            : null;

        // The live source wins; the card's static value is the fallback. A
        // field the source leaves null (e.g. getUnit() === null) falls
        // through to the card's static value, giving the source author
        // per-field control.
        if ($card->type === 'kpi') {
            if ($legacyKpiSource !== null) {
                $tile->kpi($legacyKpiSource);
            } elseif ($kpiResult !== null && filled($kpiResult->getValue())) {
                $tile->kpi((string) $kpiResult->getValue());
            } elseif (filled($card->kpi_value)) {
                $tile->kpi($card->kpi_value);
            }

            if ($kpiResult !== null && filled($kpiResult->getUnit())) {
                $tile->unit($kpiResult->getUnit());
            } elseif (filled($card->unit)) {
                $tile->unit($card->unit);
            }
        } elseif (filled($card->note)) {
            $tile->note($card->note);
        }

        // Trend and badge apply to ANY card type — a "shortcut" tile can show
        // a live badge/trend ("3 pendentes") without ever becoming a
        // KPI-variant tile. Same source-wins-with-static-fallback rule.
        $sourceTrend = $kpiResult?->getTrend();

        if (filled($sourceTrend)) {
            $tile->trend($sourceTrend, $kpiResult->getTrendColor());
        } elseif (filled($card->trend)) {
            $tile->trend($card->trend, $card->trend_color ?? 'gray');
        }

        $sourceBadge = $kpiResult?->getBadge();

        if (filled($sourceBadge)) {
            $tile->badge($sourceBadge, $kpiResult->getBadgeBg(), $kpiResult->getBadgeColor());
        } elseif (filled($card->badge)) {
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
     * Registers named KPI sources (legacy path, kept for backwards-compat —
     * see RF-03's "via legada" in the Phase G spec). Callable multiple
     * times — later calls are merged with (not replace) previously
     * registered sources. Each closure is ALSO wrapped into an anonymous
     * KpiSource (label = the given name, no caching, always authorized) and
     * folded into the unified registry, so it shows up in
     * getKpiSourceOptions() alongside class-based sources.
     *
     * @param  array<string, callable>  $sources
     */
    public function kpiSources(array $sources): static
    {
        $this->kpiSources = array_merge($this->kpiSources, $sources);

        foreach ($sources as $name => $callback) {
            $this->kpiSourceRegistry[$name] = $this->wrapLegacyKpiSource($name, $callback);
        }

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
     * Registers class-based KPI sources by class-string (Phase G, RF-03 "via
     * explícita"). Purely lazy: only key() (a static method) is called here
     * — the class itself is never instantiated until a rendered card
     * actually references its key (see getRegisteredKpiSource()).
     *
     * @param  array<int, class-string<KpiSource>>  $classStrings
     */
    public function kpis(array $classStrings): static
    {
        $this->autoDiscoverKpis = false;

        foreach ($classStrings as $class) {
            $this->registerKpiClass($class);
        }

        return $this;
    }

    /**
     * Scans a directory (recursively) for concrete KpiSource classes and
     * registers each one, à la Filament's own discoverWidgets(). $for is the
     * base namespace matching $in (e.g. discoverKpis(in: app_path('Filament/Store/Modules/POS/Kpis'),
     * for: 'App\\Filament\\Store\\Modules\\POS\\Kpis')). Safe to call more
     * than once (idempotent) and silently no-ops when $in doesn't exist yet.
     * Calling this (like kpis()) turns register()'s automatic discovery off
     * — see $autoDiscoverKpis.
     */
    public function discoverKpis(string $in, string $for): static
    {
        $this->autoDiscoverKpis = false;

        $this->scanForKpiClasses($in, $for);

        return $this;
    }

    /**
     * The actual directory scan behind discoverKpis(), factored out so
     * register()'s automatic discovery can reuse it WITHOUT flipping
     * $autoDiscoverKpis off (that flag only reacts to an explicit,
     * developer-initiated kpis()/discoverKpis() call).
     */
    protected function scanForKpiClasses(string $in, string $for): void
    {
        if (! is_dir($in)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($in, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativeClass = Str::of($file->getPathname())
                ->after(rtrim($in, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
                ->beforeLast('.php')
                ->replace(DIRECTORY_SEPARATOR, '\\')
                ->toString();

            $this->registerKpiClass(rtrim($for, '\\').'\\'.$relativeClass);
        }
    }

    /**
     * Registers a single class-string in the unified registry, after
     * validating (via reflection, without instantiating) that it's a
     * concrete class implementing KpiSource. Silently ignores anything that
     * doesn't qualify (missing class, interface, abstract class, or a class
     * not implementing KpiSource) — a generator stub left half-written, or a
     * stray non-KPI class in a discovered folder, never breaks discovery.
     *
     * A class-based registration always wins over a same-keyed legacy
     * closure, regardless of call order — the legacy entry is dropped so
     * getKpiSource()/getKpiSources() stay in sync with "last registered
     * wins" (see the $kpiSources docblock).
     */
    protected function registerKpiClass(string $class): void
    {
        if (! class_exists($class)) {
            return;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        if (! $reflection->implementsInterface(KpiSource::class)) {
            return;
        }

        /** @var class-string<KpiSource> $class */
        $key = $class::key();

        $this->kpiSourceRegistry[$key] = $class;

        unset($this->kpiSources[$key]);
    }

    /**
     * Wraps a legacy kpiSources() closure into an anonymous KpiSource, so it
     * can be folded into the unified registry (and thus appear in
     * getKpiSourceOptions()) without changing its resolution behaviour
     * elsewhere (mapCardToDto() still special-cases legacy keys via
     * getKpiSource()).
     */
    protected function wrapLegacyKpiSource(string $name, callable $callback): KpiSource
    {
        return new class($name, $callback) implements KpiSource
        {
            /**
             * @param  callable  $callback
             */
            public function __construct(
                protected string $name,
                protected $callback,
            ) {}

            public static function key(): string
            {
                // Never consulted: the unified registry is already keyed by
                // $name at registration time (see kpiSources()).
                return 'legacy';
            }

            public function label(): string
            {
                return $this->name;
            }

            public function resolve(): KpiResult
            {
                return KpiResult::make(($this->callback)());
            }

            public function cacheFor(): ?int
            {
                return null;
            }

            public function authorize(?Authenticatable $user): bool
            {
                return true;
            }

            public function panels(): array
            {
                // Legacy closures never carry panel restrictions — always
                // visible on every panel, exactly like before panels()
                // existed.
                return [];
            }
        };
    }

    /**
     * Resolves a key against the unified registry into a live KpiSource
     * instance — a class-string entry is instantiated (via the container,
     * for constructor DI) lazily, right here; an already-wrapped legacy
     * instance is returned as-is. Returns null when the key isn't
     * registered at all, OR when it's registered but restricted (via
     * panels()) to a set of panels that doesn't include the current one —
     * from the caller's perspective that's indistinguishable from "not
     * registered".
     */
    public function getRegisteredKpiSource(string $key): ?KpiSource
    {
        $entry = $this->kpiSourceRegistry[$key] ?? null;

        $source = match (true) {
            $entry instanceof KpiSource => $entry,
            is_string($entry) => $this->instantiateKpiSourceClass($entry),
            default => null,
        };

        if ($source === null || ! $this->isKpiSourceAvailableOnCurrentPanel($source)) {
            return null;
        }

        return $source;
    }

    /**
     * An empty panels() (the default) means "every panel". A non-empty
     * panels() only matches when the current panel's id
     * (Support\LaunchpadPanel::id()) is in that list — including when the
     * current panel can't be determined at all (e.g. outside an HTTP
     * request), which conservatively hides the source rather than showing
     * it everywhere.
     */
    protected function isKpiSourceAvailableOnCurrentPanel(KpiSource $source): bool
    {
        $panels = $source->panels();

        if (blank($panels)) {
            return true;
        }

        $currentPanel = LaunchpadPanel::id();

        return $currentPanel !== null && in_array($currentPanel, $panels, true);
    }

    protected function instantiateKpiSourceClass(string $class): ?KpiSource
    {
        if (! class_exists($class)) {
            return null;
        }

        try {
            /** @var KpiSource $instance */
            $instance = app($class);

            return $instance;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * key() => label() for every registered source (legacy closures included),
     * for the admin's kpi_source Select (HasCardForm). Instantiating a
     * class-based source here to read its label is acceptable — this is
     * admin/build-time context, never the hot render path.
     *
     * @return array<string, string>
     */
    public function getKpiSourceOptions(): array
    {
        $options = [];

        foreach (array_keys($this->kpiSourceRegistry) as $key) {
            $source = $this->getRegisteredKpiSource($key);

            if ($source === null) {
                continue;
            }

            try {
                $options[$key] = $source->label();
            } catch (Throwable) {
                $options[$key] = $key;
            }
        }

        return $options;
    }

    /**
     * Resolves a card's kpi_source key through the NEW class-based engine
     * (lazy + memoized + cached + authorized + degrading — see
     * Support\KpiResolver). Never called for a key that resolves to a legacy
     * closure (see mapCardToDto()) — legacy keys keep flowing through
     * getKpiSource() exactly as before.
     */
    protected function resolveKpiResult(string $key): ?KpiResult
    {
        return $this->kpiResolver()->resolve($key, fn (): ?KpiSource => $this->getRegisteredKpiSource($key));
    }

    protected function kpiResolver(): KpiResolver
    {
        return $this->kpiResolver ??= new KpiResolver;
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
        } catch (Throwable) {
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
