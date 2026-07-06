<?php

namespace Filament\Launchpad\Launchpad;

use Closure;
use Filament\Pages\Page;
use Throwable;

class Tile
{
    protected string $title;

    protected ?string $subtitle = null;

    protected ?string $icon = null;

    protected ?string $badge = null;

    protected string $badgeBg = '#f3f4f6';

    protected string $badgeColor = '#374151';

    protected string|Closure|null $kpi = null;

    protected string $unit = '';

    protected ?string $trendText = null;

    protected string $trendColor = 'gray';

    protected ?string $note = null;

    protected string|Closure|null $url = null;

    protected ?string $resource = null;

    protected ?string $page = null;

    protected ?Closure $action = null;

    protected bool $isWidget = false;

    protected ?string $widgetClass = null;

    protected string|int $widgetColumnSpan = 'full';

    /**
     * Logical trend color name => hex, per the design.
     *
     * @var array<string, string>
     */
    public const TREND_COLORS = [
        'success' => '#16a34a',
        'danger' => '#dc2626',
        'warning' => '#d97706',
        'gray' => '#6b7280',
    ];

    protected function __construct(string $title)
    {
        $this->title = $title;
    }

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function badge(?string $text, string $bg = '#f3f4f6', string $color = '#374151'): static
    {
        $this->badge = $text;
        $this->badgeBg = $bg;
        $this->badgeColor = $color;

        return $this;
    }

    public function kpi(string|Closure $kpi): static
    {
        $this->kpi = $kpi;

        return $this;
    }

    public function unit(string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function trend(string $text, string $color = 'gray'): static
    {
        $this->trendText = $text;
        $this->trendColor = $color;

        return $this;
    }

    public function note(string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function url(string|Closure $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function resource(string $resourceClass): static
    {
        $this->resource = $resourceClass;

        return $this;
    }

    public function page(string $pageClass): static
    {
        $this->page = $pageClass;

        return $this;
    }

    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Marks this Tile as a native Filament widget block instead of a regular
     * KPI/shortcut tile. $class MUST be a widget class already registered by
     * the developer via LaunchpadPlugin::widgets() — callers (e.g.
     * LaunchpadPlugin::mapCardToDto()) are responsible for that check; this
     * DTO itself does no validation, it only carries the value through to
     * the renderer.
     */
    public function asWidget(string $class, string|int|null $columnSpan = 'full'): static
    {
        $this->isWidget = true;
        $this->widgetClass = $class;
        $this->widgetColumnSpan = $columnSpan ?? 'full';

        return $this;
    }

    public function isWidget(): bool
    {
        return $this->isWidget;
    }

    public function getWidgetClass(): ?string
    {
        return $this->widgetClass;
    }

    public function getWidgetColumnSpan(): string|int
    {
        return $this->widgetColumnSpan;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle ?? '';
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function hasKpi(): bool
    {
        return $this->kpi !== null;
    }

    public function hasAction(): bool
    {
        return $this->action !== null;
    }

    public function getAction(): ?Closure
    {
        return $this->action;
    }

    /**
     * Resolve the tile's navigation target, if any.
     */
    public function getUrl(): ?string
    {
        if ($this->resource !== null) {
            /** @var class-string $resource */
            $resource = $this->resource;

            if (method_exists($resource, 'getUrl')) {
                return $resource::getUrl('index');
            }

            return null;
        }

        if ($this->page !== null) {
            /** @var class-string<Page> $page */
            $page = $this->page;

            if (method_exists($page, 'getUrl')) {
                return $page::getUrl();
            }

            return null;
        }

        if ($this->url !== null) {
            return $this->url instanceof Closure ? call_user_func($this->url) : $this->url;
        }

        return null;
    }

    protected function resolveKpi(): string
    {
        if ($this->kpi === null) {
            return '';
        }

        if (! ($this->kpi instanceof Closure)) {
            return (string) $this->kpi;
        }

        try {
            return (string) call_user_func($this->kpi);
        } catch (Throwable) {
            return '—';
        }
    }

    protected function resolveTrendColor(): string
    {
        return self::TREND_COLORS[$this->trendColor] ?? self::TREND_COLORS['gray'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            't' => $this->getTitle(),
            's' => $this->getSubtitle(),
            'icon' => $this->icon,
            'badge' => $this->badge,
            'badgeBg' => $this->badgeBg,
            'badgeColor' => $this->badgeColor,
            'hasKpi' => $this->hasKpi(),
            'kpi' => $this->hasKpi() ? $this->resolveKpi() : null,
            'unit' => $this->unit,
            'trend' => $this->trendText,
            'trendColor' => $this->resolveTrendColor(),
            'nota' => $this->note,
            'href' => $this->getUrl(),
            'isWidget' => $this->isWidget,
            'widgetClass' => $this->widgetClass,
            'widgetColumnSpan' => $this->widgetColumnSpan,
        ];
    }
}
