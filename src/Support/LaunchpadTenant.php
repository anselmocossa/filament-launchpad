<?php

namespace Filament\Launchpad\Support;

use Closure;
use Filament\Launchpad\LaunchpadPlugin;
use Throwable;

/**
 * Ambient tenant resolution, mirroring LaunchpadPanel::id().
 *
 * The plugin deliberately knows NOTHING about the host application's notion of
 * a tenant: no model, no trait, no global scope. The host injects a closure
 * (LaunchpadPlugin::tenantResolver()) and everything else here is plain
 * strings. An install that never sets a resolver stays exactly single-tenant —
 * every id is null, every `tenant_id` column stays null, and the queries
 * degrade to the pre-Phase H shape.
 *
 * `override()` exists for one caller: the parent's tenant selector in /admin,
 * which needs to read and write the launchpad AS a given tenant without the
 * request itself belonging to that tenant.
 */
class LaunchpadTenant
{
    protected static ?string $override = null;

    protected static bool $hasOverride = false;

    /**
     * Re-entrancy guard for resolved(); see the comment there.
     */
    protected static bool $resolving = false;

    /**
     * The tenant the launchpad should read/write right now: the explicit
     * override when one is set, otherwise whatever the host's resolver says.
     */
    public static function id(): ?string
    {
        if (static::$hasOverride) {
            return static::$override;
        }

        return static::resolved();
    }

    /**
     * The host's own answer, ignoring any override. Used to tell "who am I
     * really" from "who am I currently editing as".
     */
    public static function resolved(): ?string
    {
        try {
            $resolver = LaunchpadPlugin::get()->getTenantResolver();
        } catch (Throwable) {
            return null;
        }

        if (! $resolver instanceof Closure) {
            return null;
        }

        // A host resolver that reaches back into LaunchpadTenant::id() — easy to
        // write by accident when bridging another tenancy package — would
        // otherwise recurse until the process runs out of memory. Treat the
        // re-entrant call as "unknown" and let the outer one answer.
        if (static::$resolving) {
            return null;
        }

        static::$resolving = true;

        try {
            $tenantId = $resolver();
        } catch (Throwable) {
            return null;
        } finally {
            static::$resolving = false;
        }

        return blank($tenantId) ? null : (string) $tenantId;
    }

    /**
     * Whether this install is multi-tenant at all — i.e. whether a resolver was
     * ever wired up. Drives whether tenant-only UI (the tenant selector, the
     * EditHome layer switcher) is worth rendering.
     */
    public static function isEnabled(): bool
    {
        try {
            return LaunchpadPlugin::get()->getTenantResolver() instanceof Closure;
        } catch (Throwable) {
            return false;
        }
    }

    public static function setOverride(?string $tenantId): void
    {
        static::$override = blank($tenantId) ? null : (string) $tenantId;
        static::$hasOverride = true;
    }

    /**
     * Session key backing the parent's tenant selector across requests.
     */
    public const SESSION_KEY = 'launchpad.tenant_override';

    /**
     * Applies the selector's stored choice — but ONLY where the host resolves
     * no tenant of its own.
     *
     * That condition is the whole security of the feature: in a tenant panel
     * the host's resolver always answers, so a tenant user who managed to write
     * this session key still cannot be served another tenant's launchpad. The
     * override is reachable only from the parent panel, where "no tenant"
     * is the honest answer and choosing one is the point.
     */
    public static function applySelectorOverride(): void
    {
        if (filled(static::resolved())) {
            return;
        }

        try {
            $selected = session(static::SESSION_KEY);
        } catch (Throwable) {
            return;
        }

        if (blank($selected)) {
            return;
        }

        // Never honour an id the host does not vouch for.
        if (! array_key_exists((string) $selected, static::options())) {
            return;
        }

        static::setOverride((string) $selected);
    }

    public static function select(?string $tenantId): void
    {
        session([static::SESSION_KEY => blank($tenantId) ? null : (string) $tenantId]);

        blank($tenantId)
            ? static::clearOverride()
            : static::setOverride((string) $tenantId);
    }

    /**
     * The tenant the parent currently has selected, if any.
     */
    public static function selected(): ?string
    {
        try {
            $selected = session(static::SESSION_KEY);
        } catch (Throwable) {
            return null;
        }

        return blank($selected) ? null : (string) $selected;
    }

    public static function clearOverride(): void
    {
        static::$override = null;
        static::$hasOverride = false;
    }

    /**
     * Run $callback as if the current tenant were $tenantId, restoring whatever
     * override state was in place before — including "no override at all".
     */
    public static function actingAs(?string $tenantId, Closure $callback): mixed
    {
        $hadOverride = static::$hasOverride;
        $previous = static::$override;

        static::setOverride($tenantId);

        try {
            return $callback();
        } finally {
            static::$override = $previous;
            static::$hasOverride = $hadOverride;
        }
    }

    /**
     * @return array<string, string> tenant id => label, as declared by the host
     */
    public static function options(): array
    {
        try {
            $resolver = LaunchpadPlugin::get()->getTenantOptionsResolver();
        } catch (Throwable) {
            return [];
        }

        if (! $resolver instanceof Closure) {
            return [];
        }

        try {
            $options = $resolver();
        } catch (Throwable) {
            return [];
        }

        if (! is_array($options)) {
            return [];
        }

        $normalised = [];

        foreach ($options as $id => $label) {
            if (blank($id)) {
                continue;
            }

            $normalised[(string) $id] = (string) $label;
        }

        return $normalised;
    }
}
