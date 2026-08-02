<?php

namespace Filament\Launchpad\Support;

use Closure;
use Filament\Launchpad\LaunchpadPlugin;
use Throwable;

/**
 * Asks the host application whether Launchpad's own management resources
 * (Space/Page/Section/Card) are reachable in this installation.
 *
 * This is deliberately NOT a permission check — LaunchpadPermission and the
 * plugin's policies already answer "may this person manage spaces?". This
 * answers a different question the plugin cannot answer for itself: "does
 * this installation include Launchpad's management screens at all?" Hosts
 * that sell their panel in parts (licence tiers, feature flags, activated
 * modules) need these four resources to disappear along with the bundle they
 * belong to, and they cannot express that by editing files in vendor/.
 *
 * Soft by construction, like every other gate in this plugin:
 *   - no resolver wired  -> allowed (today's behaviour, unchanged on upgrade)
 *   - resolver throws    -> allowed (a broken host predicate must never take
 *                           the panel down)
 *
 * The predicate can only ever REMOVE access. It runs alongside the resource's
 * policy, never instead of it — see the `canAccess()` in
 * Concerns\GatedByResourceAccess.
 */
class LaunchpadResourceAccess
{
    public static function allows(string $resource): bool
    {
        try {
            $resolver = LaunchpadPlugin::get()->getResourceAccessResolver();
        } catch (Throwable) {
            // The plugin is not registered on the current panel (or is being
            // exercised outside one). Nothing to gate against.
            return true;
        }

        if (! $resolver instanceof Closure) {
            return true;
        }

        try {
            return (bool) $resolver($resource);
        } catch (Throwable) {
            return true;
        }
    }
}
