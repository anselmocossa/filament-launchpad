<?php

namespace Filament\Launchpad\Support;

use Filament\Facades\Filament;

class LaunchpadPanel
{
    public const SESSION_KEY = 'launchpad.panel_override';

    public static function id(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The panel whose launchpad is currently being AUTHORED, which is normally
     * the current one.
     *
     * The parent needs one exception: the tenant panel's launchpad has
     * panel_id = 'tenant', so from /admin it is invisible — and /admin is the
     * only console the parent has. Selecting a panel here opens that door.
     *
     * Deliberately refused wherever a tenant resolves on its own, so the door
     * exists only in the parent's console and a tenant can never use it to
     * browse another panel.
     */
    public static function browsing(): ?string
    {
        $current = static::id();

        if (blank($current) || filled(LaunchpadTenant::resolved())) {
            return $current;
        }

        try {
            $selected = session(static::SESSION_KEY);
        } catch (\Throwable) {
            return $current;
        }

        return blank($selected) ? $current : (string) $selected;
    }

    public static function selectBrowsing(?string $panelId): void
    {
        session([static::SESSION_KEY => blank($panelId) ? null : (string) $panelId]);
    }

    /**
     * Every panel registered in this application, as `id => label`.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        try {
            $panels = Filament::getPanels();
        } catch (\Throwable) {
            return [];
        }

        $options = [];

        foreach ($panels as $panel) {
            try {
                $options[$panel->getId()] = ucfirst($panel->getId());
            } catch (\Throwable) {
                continue;
            }
        }

        return $options;
    }
}
