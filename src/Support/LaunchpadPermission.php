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
 * A permission that exists but that nobody has been given yet is a separate
 * case, and it is opt-in per call (`$tolerateUnconfigured`). `shield:generate`
 * creates the row AND hands it to `super_admin` in one go, so "generated" and
 * "configured" are not the same moment: in between, every other role holds
 * nothing. For the panel's own home page that is fatal — a 403 at the front
 * door locks every user of the host app out at once, before they can reach
 * anything. So `Launchpad::canAccess()` opts in: a permission nobody (bar the
 * catch-all super_admin) holds counts as still unconfigured, and the door
 * stays open until someone actually decides who should hold it.
 *
 * `EditHome::canAccess()` opts in too, and for the same reason: despite
 * driving the same builder, it is an END-USER destination — this person
 * customizing THEIR OWN home, reached from the user menu — not an
 * administrative one.
 *
 * The management abilities (Space/Page/Section/Card, and the builder reached
 * through PageResource/BuildLayout) deliberately do NOT opt in. There, "nobody
 * was granted it" has to keep meaning "nobody gets in" — falling open would
 * hand the launchpad's own configuration to every authenticated user the
 * moment the permissions are regenerated.
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

    /**
     * @param  bool  $tolerateUnconfigured  Whether a permission that exists but
     *      nobody holds should be treated as still unconfigured (and therefore
     *      allowed). Reserved for the panel's home page — see the class docblock.
     *      Management abilities leave it off: for those, "nobody was granted it"
     *      must keep meaning "nobody gets in", which is the safe default.
     */
    public static function check(mixed $user, string $ability, bool $tolerateUnconfigured = false): bool
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

        if ($tolerateUnconfigured && ! static::permissionIsConfigured($ability)) {
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

    /**
     * Whether anyone has actually been given this permission — i.e. whether a
     * human ever decided who should hold it, as opposed to it merely existing
     * because a generator created the row.
     *
     * `super_admin` is excluded on purpose: Shield grants it every permission
     * automatically, so counting it would make every freshly generated
     * permission look configured, which is exactly the case this guards.
     */
    protected static function permissionIsConfigured(string $ability): bool
    {
        $permissionClass = config('permission.models.permission');

        if (! is_string($permissionClass) || ! class_exists($permissionClass) || ! method_exists($permissionClass, 'query')) {
            return false;
        }

        try {
            $permission = $permissionClass::query()->where('name', $ability)->first();

            if ($permission === null) {
                return false;
            }

            $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');

            if ($permission->roles()->where('name', '!=', $superAdminRole)->exists()) {
                return true;
            }

            return $permission->users()->exists();
        } catch (Throwable) {
            // Same posture as everywhere else in this class: an unreadable
            // pivot must not decide who gets in. Treat it as configured so the
            // caller falls through to the user's own can(), rather than
            // silently opening the page to everyone.
            return true;
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
