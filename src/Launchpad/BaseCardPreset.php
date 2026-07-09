<?php

namespace Filament\Launchpad\Launchpad;

use Illuminate\Support\Str;

/**
 * Optional base class for CardPreset implementations. Reduces boilerplate
 * for the common case: a stable, auto-derived key(), a sensible default
 * title, and every other field defaulting to "unset" — a concrete preset
 * then only has to override the typed properties it actually cares about
 * (à la BaseKpiSource, its Phase G counterpart for KPIs).
 *
 * The make:launchpad-card stub is expected to extend this class.
 */
abstract class BaseCardPreset implements CardPreset
{
    /**
     * Left blank on purpose: an unset title falls back to
     * defaultTitle() in toArray(). Override with a property initializer in
     * the concrete class, e.g. `protected string $title = 'Vendas Hoje';`.
     */
    protected string $title = '';

    protected ?string $subtitle = null;

    protected ?string $icon = null;

    /**
     * 'kpi' | 'shortcut' | 'widget'.
     */
    protected string $type = 'shortcut';

    protected ?string $kpiValue = null;

    protected ?string $unit = null;

    protected ?string $trend = null;

    /**
     * 'success' | 'danger' | 'warning' | 'gray'.
     */
    protected ?string $trendColor = null;

    protected ?string $badge = null;

    /**
     * 'none' | 'url' | 'resource' | 'page'.
     */
    protected string $targetType = 'none';

    protected ?string $targetValue = null;

    /**
     * key() of a registered KpiSource this preset's card should be wired to
     * (see make:launchpad-kpi). Only meaningful for type='kpi' cards.
     */
    protected ?string $kpiSource = null;

    /**
     * key() of a registered widget this preset's card should render (see
     * LaunchpadPlugin::widgets()). Only meaningful for type='widget' cards.
     */
    protected ?string $widgetKey = null;

    /**
     * Snake-cased class basename with a trailing "Card" stripped first, e.g.
     * SalesTodayCard => 'sales_today', ClientesResumo => 'clientes_resumo'.
     * Override when the persisted library_key must differ from this.
     */
    public static function key(): string
    {
        return Str::of(static::bareBasename())->snake()->toString();
    }

    /**
     * The array shape the Builder's card library / addCardFromLibrary()
     * consume. An unset $title (the base default) falls back to a
     * headline-cased version of the class basename with a trailing "Card"
     * stripped first, e.g. SalesTodayCard => 'Sales Today'.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => static::key(),
            'title' => $this->title !== '' ? $this->title : $this->defaultTitle(),
            'subtitle' => $this->subtitle,
            'icon' => $this->icon,
            'type' => $this->type,
            'kpi_value' => $this->kpiValue,
            'unit' => $this->unit,
            'trend' => $this->trend,
            'trend_color' => $this->trendColor,
            'badge' => $this->badge,
            'target_type' => $this->targetType,
            'target_value' => $this->targetValue,
            'kpi_source' => $this->kpiSource,
            'widget_key' => $this->widgetKey,
        ];
    }

    protected function defaultTitle(): string
    {
        return Str::of(static::bareBasename())->headline()->toString();
    }

    /**
     * The class basename with a single trailing "Card" removed, e.g.
     * SalesTodayCard => 'SalesToday'. A class literally named "Card" (or one
     * not ending in "Card" at all) is returned unchanged — key()/
     * defaultTitle() never derive from an empty string.
     */
    protected static function bareBasename(): string
    {
        $basename = class_basename(static::class);

        if ($basename !== 'Card' && Str::endsWith($basename, 'Card')) {
            return Str::of($basename)->beforeLast('Card')->toString();
        }

        return $basename;
    }
}
