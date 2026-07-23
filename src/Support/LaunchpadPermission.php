<?php

namespace Filament\Launchpad\Support;

use Filament\Launchpad\LaunchpadPlugin;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * SOFT-integrated authorization gate for the plugin's Filament Pages
 * (`Launchpad`, `EditHome`) and Resource models (Space/Page/Section/Card),
 * bridging into bezhansalleh/filament-shield + spatie/laravel-permission
 * WITHOUT ever requiring either package: absent spatie/laravel-permission,
 * every ability is granted — exactly today's (pre-Phase E.2) behaviour.
 *
 * Once spatie/laravel-permission is present and the ability exists in the
 * permissions table, an ability (e.g. `View:Space`, `View:Launchpad`) is
 * granted when:
 *   - the user holds the Shield `super_admin` role, or
 *   - the user's own `can()` (bridged by spatie/laravel-permission's own
 *     Gate::before, or by filament-shield's, when either is booted) grants
 *     the named permission.
 *
 * If the permission row has not been generated yet, access stays allowed.
 * This keeps upgrades safe until the host app regenerates Shield permissions.
 *
 * The super_admin check is duplicated here (rather than relying solely on
 * filament-shield's own Gate::before) because this class must also behave
 * correctly in the plugin's own test suite, which exercises
 * spatie/laravel-permission directly without filament-shield installed.
 *
 * A missing/guest user is granted too: a real Filament panel already sits
 * behind its own `auth` middleware, so `auth()->user()` is only ever null
 * here in contexts that never enforced login in the first place (e.g. the
 * plugin's own test harness) — denying those would be a regression against
 * today's (pre-Phase E.2) behaviour, not a new security boundary.
 */
class LaunchpadPermission
{
    /**
     * Whether the current user is "the main" — allowed to author the shared
     * template that every tenant inherits. The host's own predicate
     * (LaunchpadPlugin::primaryManager()) wins when wired, because a host's
     * super-admin may be team-scoped and a role check inside a tenant context
     * would misfire. Unset, it falls back to the `Manage:LaunchpadPrimary`
     * ability (super_admin included), and — absent spatie/permission — to
     * "allowed", the plugin's standard soft-gate default.
     */
    public static function managesPrimary(): bool
    {
        try {
            $resolver = LaunchpadPlugin::get()->getPrimaryManagerResolver();

            if ($resolver instanceof \Closure) {
                return (bool) $resolver();
            }
        } catch (Throwable) {
            // Fall through to the ability check.
        }

        return static::check(auth()->user(), 'Manage:LaunchpadPrimary');
    }

    public static function check(mixed $user, string $ability): bool
    {
        if (! LaunchpadVisibility::spatieAvailable()) {
            return true;
        }

        if (! is_object($user)) {
            return true;
        }

        if (static::isSuperAdmin($user)) {
            return true;
        }

        if (! method_exists($user, 'can')) {
            return true;
        }

        if (! static::permissionExists($ability)) {
            return true;
        }

        try {
            return (bool) $user->can($ability);
        } catch (Throwable) {
            // Never let a misconfigured guard/permission take the panel
            // down — degrade to "allowed", the same as if the ability were
            // never checked at all.
            return true;
        }
    }

    protected static function permissionExists(string $ability): bool
    {
        $permissionClass = config('permission.models.permission');

        if (! is_string($permissionClass) || ! class_exists($permissionClass) || ! method_exists($permissionClass, 'query')) {
            return false;
        }

        try {
            return (bool) $permissionClass::query()
                ->where('name', $ability)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected static function isSuperAdmin(mixed $user): bool
    {
        if (! $user instanceof Authenticatable && ! is_object($user)) {
            return false;
        }

        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');

        try {
            return (bool) $user->hasRole($superAdminRole);
        } catch (Throwable) {
            return false;
        }
    }
}
