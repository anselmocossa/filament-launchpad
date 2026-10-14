<?php

namespace Filament\Launchpad\Launchpad;

use Filament\Launchpad\Support\Sparkline;

/**
 * Rich value object returned by KpiSource::resolve(). A single source can
 * feed several tile fields (value/unit/trend/badge/sparkline) from a single query,
 * per the Phase G decision to keep "one class, one query, several fields"
 * instead of separate value()/badge_source()/trend_source() hooks.
 *
 * Every property is a plain scalar (no Closures) so instances are safely
 * cacheable via Illuminate\Support\Facades\Cache (see Support\KpiResolver).
 * The fluent setters mutate $this and return it (mirrors Tile/TileGroup's
 * existing builder style in this codebase) rather than being truly
 * immutable value objects.
 */
class KpiResult
{
    protected string|int $value;

    protected ?string $unit = null;

    protected ?string $trend = null;

    protected string $trendColor = 'gray';

    protected ?string $badge = null;

    protected string $badgeBg = '#f3f4f6';

    protected string $badgeColor = '#374151';

    /** @var list<int|float>|null */
    protected ?array $sparkline = null;

    protected string $sparklineType = 'area';

    protected ?string $sparklineColor = null;

    protected function __construct(string|int $value)
    {
        $this->value = $value;
    }

    public static function make(string|int $value): static
    {
        return new static($value);
    }

    public function unit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function trend(?string $text, string $color = 'gray'): static
    {
        $this->trend = $text;
        $this->trendColor = $color;

        return $this;
    }

    public function badge(?string $text, string $bg = '#f3f4f6', string $color = '#374151'): static
    {
        $this->badge = $text;
        $this->badgeBg = $bg;
        $this->badgeColor = $color;

        return $this;
    }

    /**
     * A small chart drawn in the tile in place of the trend line — an 'area'
     * (line with a filled area) or 'bar' series, oldest value first. The trend
     * text, if any, becomes the chart's tooltip. The color defaults to the
     * panel's accent color.
     *
     * @param  array<int|float>|null  $values
     */
    public function sparkline(?array $values, string $type = 'area', ?string $color = null): static
    {
        $this->sparkline = $values === null ? null : array_values($values);
        $this->sparklineType = in_array($type, Sparkline::TYPES, true) ? $type : 'area';
        $this->sparklineColor = $color;

        return $this;
    }

    public function getValue(): string|int
    {
        return $this->value;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function getTrend(): ?string
    {
        return $this->trend;
    }

    public function getTrendColor(): string
    {
        return $this->trendColor;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function getBadgeBg(): string
    {
        return $this->badgeBg;
    }

    public function getBadgeColor(): string
    {
        return $this->badgeColor;
    }

    /**
     * @return list<int|float>|null
     */
    public function getSparkline(): ?array
    {
        return $this->sparkline;
    }

    public function getSparklineType(): string
    {
        return $this->sparklineType;
    }

    public function getSparklineColor(): ?string
    {
        return $this->sparklineColor;
    }
}
