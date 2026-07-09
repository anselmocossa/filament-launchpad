<?php

namespace Filament\Launchpad\Launchpad;

/**
 * Contract for a class-based card preset offered by the drag&drop Builder's
 * "Biblioteca de Cards" — a class-based replacement/complement for the
 * legacy LaunchpadPlugin::cardLibrary([...]) array entries (Phase H, mirrors
 * KpiSource's Phase G design for KPIs). A preset is pure: it never receives
 * the Section/Page it's dropped into, it only produces the array shape the
 * Builder already knows how to turn into a Card — see
 * Filament\Launchpad\Filament\Concerns\InteractsWithLaunchpadBuilder::addCardFromLibrary().
 *
 * key() is stable and is what gets persisted on launchpad_cards.library_key
 * — renaming a class is safe as long as key() keeps returning the same
 * string, moving the class file is always safe.
 */
interface CardPreset
{
    /**
     * Stable identifier for this preset in the Builder's "Biblioteca de
     * Cards" and on Card::$library_key. Called statically (the class is not
     * instantiated just to learn its key), so registration by class-string
     * stays lazy.
     */
    public static function key(): string;

    /**
     * The array shape the Builder's card library / addCardFromLibrary()
     * already consume: key, title, subtitle, icon, type, kpi_value, unit,
     * trend, trend_color, badge, target_type, target_value (plus
     * kpi_source/widget_key, carried through for a preset that wants to seed
     * a card already wired to a live KpiSource or a registered widget).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
